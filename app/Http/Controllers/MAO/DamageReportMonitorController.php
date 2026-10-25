<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Concerns\SyncsDisasterLinks;
use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Models\User;
use App\Services\RecordListExport;
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
        /* By farmer, or flat (revision section 11). Ordering only - the
           same rows either way. Grouped puts a farmer's reports together so
           the page prints the name once above them; flat is newest first,
           which is what the office wants when the question is "what came in
           today" or "what is still unassigned".

           Grouped is ordered by who reported last, not alphabetically: see
           farmerLatestReport(). The farmer_id is a tie-breaker only, for the
           rare case of two farmers whose newest report carries the same
           timestamp - without it their rows could interleave and break both
           blocks. */
        $view = $request->query('view') === 'flat' ? 'flat' : 'grouped';

        // Export Report. Handled here rather than on a route of its own so
        // that the file is built from exactly the query the page is showing,
        // filters and all, with no chance of the two drifting apart.
        if ($request->filled('export')) {
            return $this->export($request);
        }

        $limited = \App\Support\LatestPerFarmer::applies($request, $view);

        $reports = $this->baseQuery($request)
            ->when($limited, fn ($query) => \App\Support\LatestPerFarmer::damageReports($query))
            ->when($view === 'grouped', fn ($query) => $query
                ->orderByDesc(self::farmerLatestReport())
                ->orderBy('damage_reports.farmer_id'))
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
            /* 'affected_area' was here and is gone (revision sections 5 and
               14, Oct 2026).

               It summed damaged_area_hectares across every damage report
               ever filed, and the Crop Damage tab showed it as "Total
               Affected Area". Two things wrong with that:

               The figure double-counted. One field reported in two separate
               typhoons was added twice, so the card read as "this much
               farmland in Tanza is damaged" while being a number that does
               not describe any area of land. Section 14: "Avoid adding
               repeated historical farm areas in ways that incorrectly
               represent unique farmland."

               And it was the fourth card on a tab whose two siblings have
               three, which section 5 asks to be consistent.

               Removed rather than left unused, because it was an unfiltered
               SUM over the whole child table on every load of this page,
               paying for a query nothing reads. Affected area is still
               reported per period, without the double-count, in Reports &
               Analytics - see MonthlyReportBuilder, which is where a total
               belongs. */
        ];

        // List + detail panel (Sept 2026): "View" loads the report inline
        // in the right-hand panel via ?selected=<id>, instead of navigating
        // to the separate details page.
        $selected = $request->filled('selected')
            ? DamageReport::query()->with(self::detailRelations())->find($request->selected)
            : null;

        \App\Support\Seen::mark(Auth::user(), 'damage_report', $selected?->id);

        return view('mao.damage-reports.index', [
            'view'    => $view,
            'reports'      => $reports,
            'farmerTotals' => $limited ? \App\Support\LatestPerFarmer::damageTotals() : collect(),
            'selected'     => $selected,
            'history'      => $selected ? $this->farmerHistory($selected) : collect(),
            'availableDisasters' => $selected ? Disaster::active()->orderByDesc('date_start')->orderBy('name')->get() : collect(),
            'canEditDisasters'   => $selected ? in_array($selected->status, self::DISASTER_EDITABLE_STATUSES, true) : false,
            'summary'      => $summary,
            'statuses'     => self::STATUSES,
            'associations' => Association::orderBy('name')->get(),
            'barangays'    => Barangay::orderBy('name')->get(),
            'disasters'    => Disaster::orderByDesc('date_start')->get(),
            // Section 9's "assign a technician directly from here" - the
            // assignment form itself still posts to ValidationMonitorController
            // @assign (mao.validations.assign) rather than duplicating that
            // logic, this page just needs the technician list to render it.
            'technicians'  => User::where('role', 'technician')->where('status', 'active')
                ->orderBy('full_name')->orderBy('username')->get(),
        ]);
    }

    /**
     * Export Report: the Crop Damage list as it is currently filtered, as
     * Excel or PDF.
     *
     * Every matching report, not just the page on screen - see
     * RecordListExport. The rows are built here rather than in the service
     * because only this controller knows what a damage report is worth
     * putting in a column.
     *
     * One row per report, with the crops listed in a cell, which is how the
     * table reads on screen. A row per crop would be a different document
     * and would make the report count disagree with the dashboard.
     */
    private function export(Request $request)
    {
        $reports = $this->baseQuery($request)->latest()->get();

        $rows = $reports->map(fn ($report) => [
            $report->full_reference,
            $report->farmer?->full_name ?? 'Unknown',
            $report->farmer?->user?->phone_number ?: '-',
            $report->farmer?->barangay?->name ?? '-',
            $report->farmer?->association?->name ?? '-',
            $report->created_at?->format('d M Y g:i A') ?? '-',
            DamageReport::CAUSES[$report->damage_cause] ?? ucwords(str_replace('_', ' ', (string) $report->damage_cause)),
            $report->disasters->map(fn ($disaster) => $disaster->name)->implode('; ') ?: '-',
            $report->crops->map(fn ($crop) => $crop->crop_specify ?: $crop->crop?->name)->filter()->implode('; ') ?: '-',
            round((float) ($report->crops_sum_damaged_area_hectares ?? 0), 2),
            $report->crops->max('estimated_damage_percent') !== null
                ? (float) $report->crops->max('estimated_damage_percent')
                : '',
            round((float) $report->crops->sum('production_cost'), 2),
            $report->crops->max('farmgate_price_per_kg') !== null
                ? round((float) $report->crops->max('farmgate_price_per_kg'), 2)
                : '',
            round((float) $report->crops->sum('total_damage_cost'), 2),
            DamageReport::STATUSES[$report->status] ?? ucwords(str_replace('_', ' ', (string) $report->status)),
            $report->assigned_at?->format('d M Y') ?? '-',
            $report->assignedTechnician?->full_name ?: ($report->assignedTechnician?->username ?? '-'),
            $report->validation?->assessed_damage_percent !== null
                ? (float) $report->validation->assessed_damage_percent
                : '',
            \App\Models\Validation::severityLabel($report->validation?->severity) ?? '-',
            $report->validation?->validated_at?->format('d M Y') ?? '-',
            (string) ($report->description ?: '-'),
        ])->all();

        return RecordListExport::download(
            $request->query('export') === 'pdf' ? 'pdf' : 'xlsx',
            'Crop Damage Reports',
            $this->exportFilters($request),
            [
                'Report ID', 'Farmer', 'Contact', 'Barangay', 'Association', 'Date Reported',
                'Cause', 'Disaster Event', 'Crops', 'Damaged Area (ha)',
                'Farmer Estimate (%)', 'Production Cost', 'Farmgate Price/kg',
                'Estimated Damage Cost', 'Status', 'Date Assigned', 'Technician',
                'Assessed Damage (%)', 'Severity', 'Date Inspected', 'Farmer Remarks',
            ],
            $rows,
            'crop-damage-reports'
        );
    }

    /**
     * The filters in force, in words, for the file's own header. Ids would
     * be useless to the person reading the file, so each one is resolved to
     * the name the office knows it by.
     */
    private function exportFilters(Request $request): array
    {
        return [
            'Status'      => $request->filled('status')
                ? (DamageReport::STATUSES[$request->status] ?? $request->status)
                : null,
            'Association' => $request->filled('association_id')
                ? Association::find($request->association_id)?->name
                : null,
            'Barangay'    => $request->filled('barangay_id')
                ? Barangay::find($request->barangay_id)?->name
                : null,
            'Disaster'    => $request->filled('disaster_id')
                ? Disaster::find($request->disaster_id)?->name
                : null,
            'Technician'  => $request->filled('technician_id')
                ? ($request->technician_id === 'unassigned'
                    ? 'Unassigned'
                    : (User::find($request->technician_id)?->full_name ?? 'Assigned'))
                : null,
            'Search'      => $request->query('search'),
        ];
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
                'Your damage report ' . $damageReport->full_reference . ' has been approved by the Municipal '
                    . 'Agriculture Office. It may now be considered for assistance allocation.',
            ],
            'flagged' => [
                'Report Needs a Second Look',
                'Your damage report ' . $damageReport->full_reference . ' has been flagged for a second look by the '
                    . 'Municipal Agriculture Office. Additional information may be requested - please watch for '
                    . 'a follow-up, or check with your association or the office.',
            ],
            'rejected' => [
                'Report Rejected',
                'Your damage report ' . $damageReport->full_reference . ' has been reviewed and was not approved by '
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
            'action'       => 'Archived damage report ' . $damageReport->full_reference,
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
            'action'       => 'Restored damage report ' . $damageReport->full_reference,
            'target_table' => 'damage_reports',
            'target_id'    => $damageReport->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Damage report restored.');
    }

    /**
     * A farmer's name as a correlated subquery, for ordering a report list
     * by member without joining (a join would multiply rows against the
     * crops and disasters this query already eager-loads).
     *
     * Shared with the Validation Monitoring page, which groups the same way.
     */
    public static function farmerColumn(string $column)
    {
        return Farmer::select('farmers.' . $column)
            ->whereColumn('farmers.id', 'damage_reports.farmer_id')
            ->limit(1);
    }

    /**
     * When this row's farmer last filed a report.
     *
     * The grouped view used to list farmers alphabetically, so a report that
     * came in this morning could sit three screens down behind every member
     * whose surname begins with A. The office's question on opening this page
     * is "what is new", so the blocks are now ordered by how recently each
     * farmer filed: whoever reported last is at the top, with their own
     * newest report first inside their block.
     *
     * A correlated subquery rather than a join, for the same reason
     * farmerColumn() above is one: a join would multiply this row against the
     * crops and disasters already being eager-loaded.
     *
     * It returns one value per farmer, so every row belonging to the same
     * farmer sorts to the same place and their block stays in one piece.
     * Deleted and archived reports are left out, so a report the office has
     * retired cannot keep a farmer floating at the top.
     *
     * $inspectedOnly is for the Validation Monitoring page, which only lists
     * reports a technician has finished. Without it, a farmer could be pushed
     * to the top of that page by a report that is not on it.
     */
    public static function farmerLatestReport(bool $inspectedOnly = false)
    {
        return DB::table('damage_reports as latest_of_farmer')
            ->selectRaw('MAX(latest_of_farmer.created_at)')
            ->whereColumn('latest_of_farmer.farmer_id', 'damage_reports.farmer_id')
            ->whereNull('latest_of_farmer.deleted_at')
            ->whereNull('latest_of_farmer.archived_at')
            ->when($inspectedOnly, fn ($query) => $query->whereExists(
                fn ($exists) => $exists->select(DB::raw(1))
                    ->from('validations')
                    ->whereColumn('validations.damage_report_id', 'latest_of_farmer.id')
                    ->whereNotNull('validations.validated_at')
            ));
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

    /**
     * Every report this farmer has filed, newest first, for the View panel.
     * The list keeps only the latest few per farmer; the full history is here.
     */
    private function farmerHistory(DamageReport $report)
    {
        return DamageReport::query()
            ->notDeleted()
            ->where('farmer_id', $report->farmer_id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'farmer_id', 'farmer_report_no', 'status', 'created_at']);
    }
}
