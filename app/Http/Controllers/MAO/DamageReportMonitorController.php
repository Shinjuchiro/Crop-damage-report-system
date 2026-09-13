<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Concerns\SyncsDisasterLinks;
use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\Disaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        return view('mao.damage-reports.index', [
            'reports'      => $reports,
            'summary'      => $summary,
            'statuses'     => self::STATUSES,
            'associations' => Association::orderBy('name')->get(),
            'barangays'    => Barangay::orderBy('name')->get(),
            'disasters'    => Disaster::orderByDesc('date_start')->get(),
        ]);
    }

    public function show(DamageReport $damageReport)
    {
        $damageReport->load([
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
        ]);

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

        return back()->with('status', 'Report marked as ' . $data['decision'] . '.');
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
     * Shared filtering, reused by the Validation Monitoring page.
     */
    public static function baseQuery(Request $request)
    {
        return DamageReport::query()
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
