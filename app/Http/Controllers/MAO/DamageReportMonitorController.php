<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Concerns\SyncsDisasterLinks;
use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\NotificationBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Read-only monitoring of crop damage reports.
 *
 * Farmers create these reports; the MAO watches them here and opens one report
 * to see everything attached to it in a single details page.
 */
class DamageReportMonitorController extends Controller
{
    use SyncsDisasterLinks;

    /**
     * Statuses a report must already be in before its disaster links can be
     * corrected from this screen. Everything before "verified" is still open
     * in the technician's own inspection form (SyncsDisasterLinks), so this
     * is only meant to fill the gap after that door has closed - a disaster
     * the office declares days after the report was already signed off, or
     * a correction the office needs to make once damage assessment is
     * effectively done. It stays open through 'flagged' and 'approved' since
     * assistance can still be allocated or re-evaluated for either state; a
     * 'rejected' report has no allocation path left, so it stays untouched.
     */
    private const DISASTER_EDITABLE_STATUSES = ['verified', 'flagged', 'approved'];

    public const STATUSES = [
        'pending', 'assigned', 'under_verification', 'verified', 'flagged', 'approved', 'rejected',
    ];

    public function index(Request $request)
    {
        $reports = $this->baseQuery($request)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statusCounts = DamageReport::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = [
            'total'              => (int) $statusCounts->sum(),
            'pending'            => (int) $statusCounts->get('pending', 0),
            'under_verification' => (int) $statusCounts->get('under_verification', 0),
            'verified'           => (int) $statusCounts->get('verified', 0),
            'flagged'            => (int) $statusCounts->get('flagged', 0),
            'affected_area'      => (float) DB::table('damage_report_crops')->sum('damaged_area_hectares'),
        ];

        // List + detail panel (Sept 2026): "View" loads the report inline
        // in the right-hand panel via ?selected=<id>, instead of navigating
        // to the separate details page.
        $selected = $request->filled('selected')
            ? DamageReport::query()->with(self::detailRelations())->find($request->selected)
            : null;

        return view('mao.damage-reports.index', [
            'reports'      => $reports,
            'selected'     => $selected,
            'availableDisasters' => $selected ? Disaster::active()->orderByDesc('date_start')->orderBy('name')->get() : collect(),
            'canEditDisasters'   => $selected ? in_array($selected->status, self::DISASTER_EDITABLE_STATUSES, true) : false,
            'summary'      => $summary,
            'statuses'     => self::STATUSES,
            'associations' => Association::orderBy('name')->get(),
            'barangays'    => Barangay::orderBy('name')->get(),
            'disasters'    => Disaster::orderByDesc('date_start')->get(),
        ]);
    }

    public function show(DamageReport $damageReport)
    {
        $damageReport->load(self::detailRelations());

        return view('mao.damage-reports.show', [
            'damageReport'      => $damageReport,
            // Correcting disaster links only ever offers currently active
            // events, same rule as the technician's own inspection form -
            // an archived event stays visible if it is already linked
            // (loaded via ->disasters above), it just is not offered as a
            // NEW choice going forward.
            'availableDisasters' => Disaster::active()->orderByDesc('date_start')->orderBy('name')->get(),
            'canEditDisasters'   => in_array($damageReport->status, self::DISASTER_EDITABLE_STATUSES, true),
        ]);
    }

    /**
     * Public + static so ValidationMonitorController (which already reuses
     * baseQuery() above) can load the same eager-load set for its own
     * inline "View Details" panel, instead of duplicating this list.
     */
    public static function detailRelations(): array
    {
        return [
            'farmer.user',
            'farmer.association',
            'farmer.barangay',
            'farmer.mainCrops.crop',
            'crops.crop',
            'disasters',
            'photos',
            'assignedTechnician',
            'approvedBy',
            'validation.technician',
            'validation.photos',
        ];
    }

    /**
     * The MAO's decision on a report a technician has already verified.
     * Approve, flag for a second look, or reject.
     */
    public function decide(Request $request, DamageReport $damageReport)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'flagged', 'rejected'])],
            'remarks'  => ['nullable', 'string', 'max:1000'],
        ]);

        if (! in_array($damageReport->status, ['verified', 'flagged'], true)) {
            return back()->withErrors([
                'decision' => 'Only a report a technician has verified can be approved, flagged or rejected.',
            ]);
        }

        DB::transaction(function () use ($damageReport, $data) {
            $approved = $data['decision'] === 'approved';

            $damageReport->update([
                'status'      => $data['decision'],
                'approved_by' => $approved ? Auth::id() : null,
                'approved_at' => $approved ? now() : null,
            ]);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => ucfirst($data['decision']) . ' damage report DR-'
                    . str_pad($damageReport->id, 4, '0', STR_PAD_LEFT)
                    . (! empty($data['remarks']) ? ': ' . $data['remarks'] : ''),
                'target_table' => 'damage_reports',
                'target_id'    => $damageReport->id,
                'created_at'   => now(),
            ]);
        });

        $this->notifyFarmerOfDecision($damageReport, $data['decision']);

        return back()->with('status', 'Report marked as ' . $data['decision'] . '.');
    }

    /**
     * Proposal section 66: the farmer should be told about "validation
     * results" once MAO decides. This also stands in for the "requests for
     * additional information" notification the spec calls for: this system
     * has no separate additional-info workflow, so per the Sept 2026
     * notification-system rule a 'flagged' decision is worded as MAO asking
     * for a second look, which is the closest existing status to that.
     * Runs after the transaction above has already committed, and never
     * throws - a notification failure must never make an otherwise-
     * successful decision appear to fail.
     *
     * 'important' priority (in-app only): a decision on a farmer's own
     * report is more than routine, but not an emergency (section 68 reserves
     * SMS for urgent/critical matters). link_type/link_id let the farmer's
     * bell open this exact report (NotificationBroadcast::linkUrl()).
     */
    private function notifyFarmerOfDecision(DamageReport $damageReport, string $decision): void
    {
        [$title, $message] = match ($decision) {
            'approved' => [
                'Report Approved',
                'Your damage report ' . $damageReport->reference . ' has been approved by the Municipal '
                    . 'Agriculture Office. It may now be considered for assistance allocation.',
            ],
            'flagged' => [
                'Report Needs a Second Look',
                'Your damage report ' . $damageReport->reference . ' has been flagged for a second look by the '
                    . 'Municipal Agriculture Office. Additional information may be requested - please watch for '
                    . 'a follow-up, or check with your association or the office.',
            ],
            'rejected' => [
                'Report Rejected',
                'Your damage report ' . $damageReport->reference . ' has been reviewed and was not approved by '
                    . 'the Municipal Agriculture Office. Please contact the office if you have questions.',
            ],
            default => [null, null],
        };

        if (! $title) {
            return;
        }

        try {
            $alert = NotificationBroadcast::create([
                'title'       => $title,
                'message'     => $message,
                'category'    => 'system',
                'priority'    => 'important',
                'target_type' => 'specific_farmer',
                'target_id'   => $damageReport->farmer_id,
                'link_type'   => 'damage_report',
                'link_id'     => $damageReport->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify farmer of decision on report ' . $damageReport->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Fixes the gap SyncsDisasterLinks documents: the technician can only
     * correct a report's disaster links while its inspection is still open,
     * so a disaster declared - or a mistake noticed - only after verification
     * would otherwise be stuck forever. MAO already manages disaster records
     * and allocates assistance per disaster, so this screen is the natural
     * place to let them fix the link once the technician's own door has
     * closed. Proposal 91.6-style review happens client-side (the checklist
     * is reviewed before this PUT fires); what matters here is that the
     * report is actually past inspection before MAO can touch it.
     */
    public function updateDisasters(Request $request, DamageReport $damageReport)
    {
        if (! in_array($damageReport->status, self::DISASTER_EDITABLE_STATUSES, true)) {
            return back()->withErrors([
                'disasters' => 'Disaster events can only be corrected here once a report has been verified.',
            ]);
        }

        $data = $request->validate([
            'disasters'   => ['nullable', 'array'],
            'disasters.*' => [Rule::exists('disasters', 'id')->whereNull('archived_at')],
        ]);

        $this->syncDisasterLinks($damageReport, $data['disasters'] ?? [], 'mao');

        return back()->with('status', 'Disaster events updated.');
    }

    /**
     * Sept 2026: Archive joined this page, independent of the STATUSES
     * pipeline above - the developer asked for it to be available on any
     * report regardless of status, unlike Edit, which does not exist here
     * at all (a farmer's report is corrected via the farmer's own report, or
     * via updateDisasters() above for the one field MAO itself can fix).
     */
    public function archive(DamageReport $damageReport)
    {
        $damageReport->archive(Auth::id());

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Archived damage report ' . $damageReport->reference,
            'target_table' => 'damage_reports',
            'target_id'    => $damageReport->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Damage report archived.');
    }

    public function restore(DamageReport $damageReport)
    {
        if ($damageReport->is_deleted) {
            return back()->withErrors(['damageReport' => 'This report was permanently deleted and can no longer be restored.']);
        }

        $damageReport->unarchive();

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Restored damage report ' . $damageReport->reference,
            'target_table' => 'damage_reports',
            'target_id'    => $damageReport->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Damage report restored.');
    }

    public function destroy(DamageReport $damageReport)
    {
        $damageReport->markDeleted(Auth::id());

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Deleted damage report ' . $damageReport->reference,
            'target_table' => 'damage_reports',
            'target_id'    => $damageReport->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Damage report deleted. Its data is kept for audit purposes.');
    }

    /**
     * Shared filtering, reused by the Validation Monitoring page.
     */
    public static function baseQuery(Request $request)
    {
        return DamageReport::query()
            ->notDeleted()
            ->whereNull('archived_at')
            ->with([
                'farmer.user',
                'farmer.association',
                'farmer.barangay',
                'disasters',
                'crops.crop',
                'validation',
                'assignedTechnician',
            ])
            ->withSum('crops', 'damaged_area_hectares')
            ->when($request->filled('status'),
                fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('disaster_id'), fn ($query) => $query
                ->whereHas('disasters', fn ($disaster) => $disaster->where('disasters.id', $request->disaster_id)))
            ->when($request->filled('technician_id'), function ($query) use ($request) {
                $request->technician_id === 'unassigned'
                    ? $query->whereNull('assigned_technician_id')
                    : $query->where('assigned_technician_id', $request->technician_id);
            })
            ->whereHas('farmer', function ($farmer) use ($request) {
                $farmer
                    ->when($request->filled('association_id'),
                        fn ($query) => $query->where('association_id', $request->association_id))
                    ->when($request->filled('barangay_id'),
                        fn ($query) => $query->where('barangay_id', $request->barangay_id))
                    ->when($request->filled('search'), function ($query) use ($request) {
                        $term = '%' . $request->search . '%';

                        $query->where(fn ($sub) => $sub
                            ->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                    });
            });
    }
}
