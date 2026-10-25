<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\AssistanceAllocation;
use App\Models\AssistanceAllocationBeneficiary;
use App\Models\AssistanceAllocationDraft;
use App\Models\AssistanceAllocationDocument;
use App\Models\AssistanceDecision;
use App\Models\AssistanceDistribution;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Models\Validation;
use App\Support\AssistanceCase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MAO decides which farmers are valid for a batch of assistance, records
 * handing it over, and the farmer separately confirms whether they actually
 * received it.
 *
 * AN ALLOCATION IS A BATCH OF FARMERS (Oct 2026, client decision)
 *
 * It used to be a parcel addressed to one Farmers' Association. Assistance
 * arrives at the office from time to time, and the office decides who is
 * valid for it; those farmers can belong to any association. So an
 * allocation now names farmers directly, chosen from every verified report
 * in the municipality, and association_id is nullable and no longer what an
 * allocation is addressed to (migration 2024_01_22_000001).
 *
 * The associations are still told, and that is the point rather than a
 * courtesy: the office does not hand assistance out on a schedule, so there
 * is no date to give a farmer. The association is told which of its members
 * were designated and tells them when to come and collect. Both are
 * notified when an allocation is created - see notifyDesignated().
 *
 * NO NAME, NO TYPE, NO FIGURE (Oct 2026, client decision)
 *
 * An allocation says assistance is being given and nothing else. It is not
 * cash or in-kind, it names no catalogue item, and it carries no amount or
 * quantity. The figures went for privacy; the rest went because the office
 * does not work that way. See the notes on App\Models\Assistance and
 * App\Models\AssistanceAllocation. This departs from the master prompt's
 * sections 63, 65 and 75, at the client's request.
 *
 * THE OFFICE HANDS OVER NOW, NOT THE ASSOCIATION (Oct 2026, client decision)
 *
 * The association used to record each hand-out on its own Distribute
 * screen. The office does it here instead, and the association monitors.
 * That is closer to the master prompt than what it replaced: section 61
 * only ever gave the association monitoring, and the Distribute screen was
 * added later because otherwise nothing could write an
 * assistance_distributions row at all.
 *
 * NO AMOUNTS, NO CASH OR IN-KIND (Oct 2026, client decision)
 *
 * An allocation is a name and a list of designated farmers. It carries no
 * figure and is not classified. The reason is privacy: how much a named
 * farmer received is a financial detail about an identifiable person, and
 * the people who work these screens have no need to see it. See the note on
 * App\Models\Assistance; this departs from the master prompt's sections 63,
 * 65 and 75, at the client's request.
 *
 * This controller covers three screens plus one JSON endpoint:
 *
 *   index()                the working "Assistance Allocation" screen -
 *                           who is eligible right now, who still needs an
 *                           allocation, and the shortcut to create one
 *   history()               every allocation ever made, searchable
 *   distributionTracking()  every association -> farmer hand-out, office-wide
 *
 * eligibleBeneficiaries() was removed in Oct 2026. It was a JSON endpoint
 * that filled the modal's checklist once an association had been chosen.
 * The checklist is not scoped to an association any more, so there is
 * nothing to wait for and the whole eligible list is rendered with the
 * page.
 *
 * disputes() closes a gap flagged after the Association module was built
 * (see BUILD-STATUS.md section 12): a farmer marking a distribution
 * "not received" was recorded and visible to the association, but MAO had
 * no screen that ever surfaced it. Read-only on purpose - MAO follows up by
 * contacting the association directly, the same way every other
 * cross-office coordination in this system works.
 */
class AssistanceAllocationController extends Controller
{
    public const STATUSES = ['pending', 'allocated', 'distributed', 'completed', 'cancelled'];

    /**
     * Reports still somewhere in the inspection pipeline: filed, assigned,
     * being inspected, or flagged for another look. Nobody in here is
     * eligible for assistance yet, because no technician has finished with
     * them.
     *
     * THIS USED TO BE 'verified' (changed Oct 2026)
     *
     * Eligibility now starts at verified rather than at approved, which is
     * the client's decision: a technician's completed inspection is enough,
     * and waiting for the office's separate approval of the report was
     * holding up assistance the office had already made its mind up about.
     *
     * That change forced this one. Section 31 fixed a bug where the first
     * two stat cards counted the same reports, and the fix was to keep the
     * two stages disjoint: stage one was 'verified', stage two 'approved'.
     * Adding 'verified' to stage two would have put the overlap straight
     * back. So stage one moved one step earlier, to everything BEFORE a
     * completed inspection, and the two are disjoint again by construction:
     * a report is either still being inspected or it is eligible, never
     * both.
     */
    private const AWAITING_INSPECTION_STATUSES = ['pending', 'assigned', 'under_verification', 'flagged'];

    /**
     * A damage report counts toward assistance eligibility once a
     * technician has completed the inspection. 'approved' stays in the list
     * because the office approving a report obviously does not make the
     * farmer less eligible.
     *
     * Kept as one constant so the allocation modal, the stat cards and the
     * export can never disagree about who qualifies.
     */
    private const QUALIFYING_REPORT_STATUSES = ['verified', 'approved'];

    /* =====================================================================
     | Assistance Allocation (the working screen)
     ===================================================================== */

    public function index(Request $request)
    {
        $overview = $this->buildOverview($request);

        // List + detail panel (Sept 2026, matching every other MAO list):
        // "View" on either the overview table or Recent Allocations loads
        // the allocation inline via ?selected=<id>, using the exact same
        // relations as show() and history() so the panel content can be
        // shared between them - see detailRelations().
        $selected = $request->filled('selected')
            ? AssistanceAllocation::query()->with(self::detailRelations())->find($request->selected)
            : null;

        return view('mao.assistance-allocations.index', $overview + [
            'selected'     => $selected,
            'associations' => Association::active()->orderBy('name')->get(),
            'disputeCount' => AssistanceDistribution::where('receipt_status', 'not_received')->count(),
        ]);
    }

    /**
     * Everything the index page (and the CSV export, so the two can never
     * drift apart) needs: the four stat cards, the step tracker, the
     * per-association overview table and the recent activity panel.
     */
    private function buildOverview(Request $request): array
    {
        $disasters = Disaster::active()->orderByDesc('date_start')->get();

        // Defaults to "All Disasters": a verified/approved report qualifies
        // on its own (decision 11/25 - a disaster event is optional
        // everywhere, including here), so most reports have no disaster
        // link at all and scoping to one by default would hide almost
        // everyone. Picking a specific disaster from the filter narrows the
        // view to only the reports that DO cite that event; it is a way to
        // look at one disaster's affected farmers on request, never a
        // requirement for a farmer or association to show up at all.
        $disasterId = $request->filled('disaster_id') ? (int) $request->input('disaster_id') : null;

        $associationId = $request->filled('association_id') ? (int) $request->input('association_id') : null;
        /* The assistance filter went with the assistance name (Oct 2026):
           an allocation no longer names an item, so there is nothing to
           filter by. */
        $rowStatus     = $request->filled('status') ? $request->input('status') : null;

        $selectedDisaster = $disasterId ? $disasters->firstWhere('id', $disasterId) : null;

        /*
        |--------------------------------------------------------------------
        | Two disjoint pipeline stages (section 31)
        |--------------------------------------------------------------------
        | "Awaiting Inspection" = the report is filed, assigned, being
        | inspected or flagged, so nobody in it is eligible yet (see
        | self::AWAITING_INSPECTION_STATUSES). "Qualified Beneficiaries" = a
        | technician has completed the inspection AND the report is not
        | already spoken for by another still-active allocation (see
        | self::QUALIFYING_REPORT_STATUSES and the whereDoesntHave() clause
        | inside qualifiedReports()). A report only ever counts toward one of
        | these two, never both, which is section 31's rule kept intact
        | across the Oct 2026 change to when eligibility starts. A farmer
        | drops out of "Qualified Beneficiaries" the moment the office
        | includes them in a new allocation.
        */
        $awaitingInspection = $this->awaitingInspectionReports($disasterId);
        $qualifiedReports = $this->qualifiedReports($disasterId)
            ->filter(fn ($report) => $report->farmer->association_id !== null)
            ->values();
        $qualifiedByAssociation = $qualifiedReports->groupBy(fn ($report) => $report->farmer->association_id);

        /* WHICH ASSOCIATIONS AN ALLOCATION CONCERNS (Oct 2026)

           This used to be one groupBy on the allocation's own
           association_id. That column is not what an allocation is
           addressed to any more (migration 2024_01_22_000001), so the
           answer comes from the designated farmers instead: an allocation
           concerns every association that has a member on it, and it can
           concern several at once, which the column could never say.

           Deliberately NOT filtered by disaster, unlike the qualified
           reports above. New allocations carry no disaster_id at all, so
           filtering them by one would empty this table the moment anybody
           picked an event from the filter, and the table would look broken
           rather than filtered. */
        $allocationsInScope = AssistanceAllocation::query()
            ->where('status', '!=', 'cancelled')
            ->with('beneficiaries.farmer:id,association_id')
            ->orderByDesc('allocated_at')
            ->get();

        $allocationsByAssociation = collect();

        foreach ($allocationsInScope as $allocation) {
            $associationIds = $allocation->beneficiaries
                ->pluck('farmer.association_id')
                ->filter()
                ->unique();

            foreach ($associationIds as $id) {
                $allocationsByAssociation[$id] = ($allocationsByAssociation[$id] ?? collect())->push($allocation);
            }
        }

        $stats = [
            'awaiting_inspection'        => $awaitingInspection->count(),
            'awaiting_from_associations' => $awaitingInspection->pluck('farmer.association_id')->filter()->unique()->count(),
            'qualified_beneficiaries' => $qualifiedReports->count(),
            'pending_allocation'      => $qualifiedByAssociation->keys()->diff($allocationsByAssociation->keys())->count(),
            'total_associations'      => $allocationsByAssociation->keys()->count(),
        ];

        /*
        |--------------------------------------------------------------------
        | Association Allocation Overview (paginated, filterable by status)
        |--------------------------------------------------------------------
        */
        $rows = Association::active()->orderBy('name')->get()->map(function ($association) use ($qualifiedByAssociation, $allocationsByAssociation) {
            $qualifiedCount = $qualifiedByAssociation->get($association->id, collect())->count();
            $allocation     = $allocationsByAssociation->get($association->id)?->first();

            return (object) [
                'association'     => $association,
                'qualified_count' => $qualifiedCount,
                'allocation'      => $allocation,
                'status'          => $qualifiedCount === 0 ? 'not_eligible' : ($allocation ? 'allocated' : 'pending'),
            ];
        });

        if ($rowStatus) {
            $rows = $rows->filter(fn ($row) => $row->status === $rowStatus)->values();
        }

        $perPage = 10;
        $page    = LengthAwarePaginator::resolveCurrentPage('page');

        $overviewRows = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page'), 'pageName' => 'page']
        );

        /*
        |--------------------------------------------------------------------
        | Recent Allocations - a short activity feed, independent of filters
        |--------------------------------------------------------------------
        */
        $recentAllocations = AssistanceAllocation::with(['beneficiaries.farmer.association'])
            ->withCount('beneficiaries')
            ->latest('allocated_at')
            ->take(5)
            ->get();

        return [
            'stats'              => $stats,
            'overviewRows'       => $overviewRows,
            /* The cases still owed an answer. Built here rather than in
               index() so exportOverview(), which shares this method, can
               carry the same figure if it ever needs to. */
            'awaitingDecision'   => $this->casesAwaitingDecision($disasterId),
            // The same rows, unpaginated - exportOverview() downloads all of
            // them, not just the page currently on screen.
            'allOverviewRows'    => $rows,
            'recentAllocations'  => $recentAllocations,
            'disasters'          => $disasters,
            'selectedDisaster'   => $selectedDisaster,
            'filters'            => [
                'disaster_id'    => $request->has('disaster_id') ? $request->input('disaster_id') : $disasterId,
                'association_id' => $associationId,
                'status'         => $rowStatus,
            ],
        ];
    }

    /**
     * The Export List button: the Association Allocation Overview table,
     * exactly as currently filtered, as a CSV.
     */
    public function exportOverview(Request $request)
    {
        $overview = $this->buildOverview($request);

        $filename = 'assistance-allocation-overview-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($overview) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Association', 'Qualified Beneficiaries', 'Allocation', 'Status']);

            // The full filtered list, not just the page currently on screen -
            // a paginated CSV would be a strange thing to hand someone.
            foreach ($overview['allOverviewRows'] as $row) {
                fputcsv($out, [
                    $row->association->name,
                    $row->qualified_count,
                    // The reference, not a name: an allocation has no name.
                    $row->allocation ? 'AA-' . str_pad($row->allocation->id, 3, '0', STR_PAD_LEFT) : '-',
                    ucwords(str_replace('_', ' ', $row->status)),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Everyone eligible right now, as rows the checklist can render.
     *
     * Not scoped to an association, which is the change the office asked
     * for: assistance arrives from time to time and the office picks who is
     * valid for it from across the municipality, not from one association's
     * membership. A farmer with no association at all is included, because
     * nothing about being unaffiliated makes their crop damage less real;
     * the association notice simply has nobody to go to for them.
     *
     * Eligible means a technician has completed the inspection
     * (self::QUALIFYING_REPORT_STATUSES) and the farmer is not already
     * designated on another live allocation. Both rules live in
     * qualifiedReports(), so this list and the server-side re-check in
     * store() can never disagree about who qualifies.
     */
    private function eligibleForChecklist()
    {
        return $this->qualifiedReports(null)
            ->map(fn ($report) => [
                'farmer_id'        => $report->farmer_id,
                'damage_report_id' => $report->id,
                'name'             => $report->farmer->full_name,
                'barangay'         => $report->farmer->barangay?->name ?? '-',
                // Both the id and the name: the name is shown, and the id is
                // what the overview table's per-association Allocate button
                // ticks with.
                'association_id'   => $report->farmer->association_id,
                'association'      => $report->farmer->association?->name ?? 'No association',
                'reference'        => $report->full_reference,
                'severity_label'   => Validation::severityLabel($report->validation?->severity) ?? '-',
            ])
            ->values();
    }

    /**
     * One row per farmer: their most recent report matching $statuses.
     * Restricting to a disaster and/or one association is optional, so the
     * same method backs both the office-wide stat cards and the modal's
     * per-association checklist. Shared by qualifiedReports() and
     * awaitingInspectionReports() so the two stages never quietly drift
     * apart - see section 31, and the note on
     * self::AWAITING_INSPECTION_STATUSES for how that rule survived
     * eligibility moving earlier.
     *
     * A disaster link was never required on the farmer's own report
     * (decision 11, section 8) and an allocation is no longer required to
     * name one either (decision 25, section 28) - so this must not require
     * one to decide eligibility. Passing a $disasterId narrows the result to
     * reports that cite that specific event (MAO targeting aid at one
     * disaster on purpose); passing null returns every matching report
     * regardless of whether it names a disaster at all, which is the normal
     * case now. The disaster alert/notification system is a separate,
     * informational channel (section 27) and must never gate this.
     *
     * $excludeAlreadyAllocated additionally drops any report that has
     * already made its farmer a beneficiary of a still-active (not
     * cancelled) allocation - once MAO includes a farmer in an allocation,
     * they stop showing up as "still needing one" (section 31).
     */
    private function reportsByStatus(array $statuses, ?int $disasterId, ?int $associationId = null, bool $excludeAlreadyAllocated = false): Collection
    {
        return DamageReport::query()
            ->whereIn('status', $statuses)
            ->when($disasterId, fn ($q) => $q
                ->whereHas('disasters', fn ($d) => $d->where('disasters.id', $disasterId)))
            ->when($excludeAlreadyAllocated, fn ($q) => $q
                ->whereDoesntHave('allocationBeneficiaries', fn ($b) => $b
                    ->whereHas('allocation', fn ($a) => $a->where('status', '!=', 'cancelled'))))
            ->whereHas('farmer', function ($f) use ($associationId) {
                if ($associationId) {
                    $f->where('association_id', $associationId);
                }
            })
            ->with(['farmer.barangay', 'farmer.association', 'validation'])
            ->latest('created_at')
            ->get()
            ->unique('farmer_id')
            ->values();
    }

    /**
     * The cases the office still owes an answer on (section 11.2).
     *
     * A report a technician has finished with, that the office has not
     * designated and has not refused: no decision row, and not already a
     * beneficiary of a live allocation. These are the rows that can be
     * marked Not Designated, which is the one of the four statuses that had
     * no way of being recorded before.
     *
     * Both verified AND approved reports are here on purpose. Approving a
     * report says the damage is real; it does not say the farmer gets
     * assistance (section 11.2), so an approved report with nothing
     * allocated against it is still a case awaiting an answer.
     */
    private function casesAwaitingDecision(?int $disasterId): Collection
    {
        return DamageReport::query()
            ->whereIn('status', ['verified', 'approved'])
            ->whereDoesntHave('assistanceDecision')
            ->whereDoesntHave('allocationBeneficiaries', fn ($b) => $b
                ->whereHas('allocation', fn ($a) => $a->where('status', '!=', 'cancelled')))
            ->when($disasterId, fn ($q) => $q
                ->whereHas('disasters', fn ($d) => $d->where('disasters.id', $disasterId)))
            ->with(['farmer.barangay', 'farmer.association', 'validation'])
            ->latest('created_at')
            ->get();
    }

    /**
     * Farmers whose report has not been inspected yet, or is being
     * inspected, or was flagged. Nobody here is eligible, and that is the
     * point of the first stat card: it is the queue feeding the second one.
     *
     * Was pendingReviewReports(), which meant something different - see the
     * note on self::AWAITING_INSPECTION_STATUSES.
     */
    private function awaitingInspectionReports(?int $disasterId, ?int $associationId = null): Collection
    {
        return $this->reportsByStatus(self::AWAITING_INSPECTION_STATUSES, $disasterId, $associationId);
    }

    /**
     * Farmers who qualify for assistance right now: a technician has
     * completed the inspection and the report is not already covered by
     * another active allocation. Feeds "Qualified Beneficiaries", the
     * per-association overview and the Allocate Assistance modal.
     */
    private function qualifiedReports(?int $disasterId, ?int $associationId = null): Collection
    {
        return $this->reportsByStatus(self::QUALIFYING_REPORT_STATUSES, $disasterId, $associationId, excludeAlreadyAllocated: true);
    }

    /* =====================================================================
     | Allocation History
     ===================================================================== */

    public function history(Request $request)
    {
        /* The association and assistance filters here both changed in Oct
           2026. An allocation is not addressed to an association any more,
           so filtering by one means "allocations with a member of that
           association on them", which is a whereHas through the designated
           farmers. And it names no assistance item, so that filter and the
           search that went with it are gone: there is no name to search
           for. */
        $allocations = AssistanceAllocation::query()
            ->with(['allocatedBy', 'beneficiaries.farmer.association'])
            // No withCount: the list is four columns now and prints neither
            // figure. The detail panel counts for itself, through
            // detailRelations(), on its own query.
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('association_id'), fn ($q) => $q
                ->whereHas('beneficiaries.farmer',
                    fn ($f) => $f->where('association_id', $request->association_id)))
            ->latest('allocated_at')
            ->paginate(15)
            ->withQueryString();

        $statusCounts = AssistanceAllocation::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // List + detail panel (Sept 2026): "View Details" loads the
        // allocation inline via ?selected=<id> using the same relations as
        // show(), instead of navigating to a separate page.
        $selected = $request->filled('selected')
            ? AssistanceAllocation::query()->with(self::detailRelations())->find($request->selected)
            : null;

        return view('mao.assistance-allocations.history', [
            'allocations'  => $allocations,
            'selected'     => $selected,
            'statuses'     => self::STATUSES,
            'associations' => Association::orderBy('name')->get(),
            // No 'assistances': the history filter by assistance item went
            // with the item itself (Oct 2026).
            'summary'      => [
                'pending'  => (int) $statusCounts->get('pending', 0),
                'approved' => (int) $statusCounts->get('allocated', 0),
                'released' => (int) $statusCounts->get('distributed', 0) + (int) $statusCounts->get('completed', 0),
            ],
        ]);
    }

    /* =====================================================================
     | Distribution Tracking
     ===================================================================== */

    /**
     * Every hand-over the office has recorded, office-wide, and whether the
     * farmer confirms receiving it. The Disputes list is the "not received"
     * slice of exactly this same table.
     *
     * WHICH ASSOCIATION A DISTRIBUTION BELONGS TO (fixed Oct 2026)
     *
     * This filtered on `allocation.association_id`, and so did disputes()
     * below. That column stopped being what an allocation is addressed to
     * in migration 2024_01_22_000001 and store() has left it NULL ever
     * since, so picking an association from the filter matched nothing at
     * all: every allocation made after the change has a null there, and
     * `where('association_id', 4)` cannot match a null. The page did not
     * error, it just came back empty, which reads as "this association has
     * received nothing" - the most misleading possible answer.
     *
     * The same mistake had already been found and fixed on Allocation
     * History; these were the two sibling methods it was missed in.
     *
     * A distribution names one farmer, so its association is that farmer's
     * association, which is both correct and more precise than the
     * allocation's: an allocation now spans several associations, while a
     * hand-over is to one person.
     */
    public function distributionTracking(Request $request)
    {
        $distributions = AssistanceDistribution::query()
            ->with(['farmer.association', 'allocation', 'distributedBy'])
            ->when($request->filled('association_id'), fn ($q) => $q
                ->whereHas('farmer', fn ($f) => $f->where('association_id', $request->association_id)))
            ->when($request->filled('distribution_status'),
                fn ($q) => $q->where('distribution_status', $request->distribution_status))
            ->when($request->filled('receipt_status'),
                fn ($q) => $q->where('receipt_status', $request->receipt_status))
            ->latest('distributed_at')
            ->paginate(15)
            ->withQueryString();

        // List + detail panel (Sept 2026, matching every other MAO list):
        // "View" loads the distribution inline via ?selected=<id> instead
        // of jumping straight to the allocation's own show page.
        $selected = $request->filled('selected')
            ? AssistanceDistribution::with(['farmer.association', 'allocation', 'distributedBy'])
                ->find($request->selected)
            : null;

        return view('mao.assistance-allocations.distribution-tracking', [
            'distributions' => $distributions,
            'selected'      => $selected,
            'associations'  => Association::orderBy('name')->get(),
            'filters'       => $request->only(['association_id', 'distribution_status', 'receipt_status']),
        ]);
    }

    /* =====================================================================
     | Disputes (unchanged - see the class docblock)
     ===================================================================== */

    public function disputes(Request $request)
    {
        $disputes = AssistanceDistribution::query()
            ->where('receipt_status', 'not_received')
            ->with([
                'farmer.association',
                'allocation',
                'damageReport',
                'distributedBy',
            ])
            /* Through the farmer, not allocation.association_id - see the
               note on distributionTracking() above for why that column
               cannot answer this any more. */
            ->when($request->filled('association_id'), fn ($query) => $query
                ->whereHas('farmer', fn ($f) => $f->where('association_id', $request->association_id)))
            ->orderByDesc('receipt_confirmed_at')
            ->paginate(15)
            ->withQueryString();

        // List + detail panel (Sept 2026, matching every other MAO list):
        // "View" loads the disputed distribution inline via ?selected=<id>.
        $selected = $request->filled('selected')
            ? AssistanceDistribution::with([
                    'farmer.association', 'allocation', 'damageReport', 'distributedBy',
                ])
                ->where('receipt_status', 'not_received')
                ->find($request->selected)
            : null;

        return view('mao.assistance-allocations.disputes', [
            'disputes'     => $disputes,
            'selected'     => $selected,
            'associations' => Association::orderBy('name')->get(),
            'filters'      => $request->only(['association_id']),
        ]);
    }

    /* =====================================================================
     | Create / store
     ===================================================================== */

    /**
     * Create Assistance Distribution (Oct 2026 revision): the full page that
     * replaced the Allocate Assistance pop-up. One form, two sections (basic
     * information, then beneficiaries), a review screen, and a confirmed
     * finalize. ?draft=<id> reopens a saved draft.
     */
    public function create(Request $request)
    {
        $draft = $request->filled('draft')
            ? AssistanceAllocationDraft::where('user_id', Auth::id())->find($request->query('draft'))
            : null;

        return view('mao.assistance-allocations.create', [
            'farmers'      => $this->wizardFarmers(),
            'associations' => Association::active()->orderBy('name')->get(['id', 'name']),
            'draft'        => $draft,
            'drafts'       => AssistanceAllocationDraft::where('user_id', Auth::id())
                ->latest('updated_at')->get(),
        ]);
    }

    /**
     * One row per farmer for the beneficiary table: their most recent
     * verified or approved report, and whether that report is already
     * spoken for by a live allocation.
     *
     * Already-designated farmers are LISTED, marked, and cannot be ticked
     * (the client's choice), so the office can see why somebody is missing
     * rather than wondering. The server re-checks this on finalize.
     *
     * Severity is grouped into the three the office uses: Slight, Moderate,
     * Total. A retired "Partial" inspection groups under Total for the
     * filter, but its badge keeps the word the technician recorded.
     */
    private function wizardFarmers(): Collection
    {
        return DamageReport::query()
            ->whereIn('status', self::QUALIFYING_REPORT_STATUSES)
            ->with(['farmer.barangay', 'farmer.association', 'validation',
                    'allocationBeneficiaries.allocation'])
            ->latest('created_at')
            ->get()
            ->unique('farmer_id')
            ->filter(fn ($report) => $report->farmer)
            ->map(function ($report) {
                $designation = $report->allocationBeneficiaries
                    ->first(fn ($b) => $b->allocation && $b->allocation->status !== 'cancelled');

                $severity = $report->validation?->severity;
                $group    = match ($severity) {
                    'slight'            => 'slight',
                    'moderate'          => 'moderate',
                    'total', 'partial'  => 'total',
                    default             => null,
                };

                return [
                    'farmer_id'        => $report->farmer_id,
                    'code'             => 'FARM-' . str_pad((string) $report->farmer_id, 3, '0', STR_PAD_LEFT),
                    'name'             => $report->farmer->full_name,
                    'barangay'         => $report->farmer->barangay?->name ?? '-',
                    'association_id'   => $report->farmer->association_id,
                    'association'      => $report->farmer->association?->name ?? 'No association',
                    'reference'        => $report->full_reference,
                    'severity_group'   => $group,
                    'severity_label'   => Validation::severityLabel($severity),
                    'severity_color'   => Validation::severityColor($severity),
                    'assessed'         => $report->validation?->assessed_damage_percent !== null
                        ? (float) $report->validation->assessed_damage_percent : null,
                    'verified'         => true,
                    'designated'       => (bool) $designation,
                    'designated_batch' => $designation?->allocation?->batch_name
                        ?: ($designation ? 'AA-' . str_pad($designation->allocation->id, 3, '0', STR_PAD_LEFT) : null),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Save Draft: keeps the form so far. Nothing is validated hard (it is
     * unfinished by definition), nobody is notified, and the draft is not
     * counted anywhere until it is finalized.
     */
    public function saveDraft(Request $request)
    {
        $data = $request->validate([
            'draft_id'          => ['nullable', 'integer'],
            'batch_name'        => ['nullable', 'string', 'max:150'],
            'details'           => ['nullable', 'string', 'max:500'],
            'distribution_date' => ['nullable', 'date'],
            'venue'             => ['nullable', 'string', 'max:255'],
            'beneficiary_ids'   => ['nullable', 'array'],
            'beneficiary_ids.*' => ['integer'],
        ]);

        $fields = [
            'user_id'           => Auth::id(),
            'batch_name'        => $data['batch_name'] ?? null,
            'details'           => $data['details'] ?? null,
            'distribution_date' => $data['distribution_date'] ?? null,
            'venue'             => $data['venue'] ?? null,
            'farmer_ids'        => array_values(array_map('intval', $data['beneficiary_ids'] ?? [])),
        ];

        $draft = ! empty($data['draft_id'])
            ? AssistanceAllocationDraft::where('user_id', Auth::id())->find($data['draft_id'])
            : null;

        $draft ? $draft->update($fields) : ($draft = AssistanceAllocationDraft::create($fields));

        return redirect()
            ->route('mao.assistance-allocations.create', ['draft' => $draft->id])
            ->with('status', 'Draft saved. Nothing has been sent to anyone.');
    }

    public function destroyDraft(AssistanceAllocationDraft $draft)
    {
        abort_unless($draft->user_id === Auth::id(), 403);

        $draft->delete();

        return redirect()
            ->route('mao.assistance-allocations.create')
            ->with('status', 'Draft deleted.');
    }

    /* OTHER_ASSISTANCE is gone, and OTHER_IN_KIND before it. Both were the
       sentinel the assistance dropdown used for "not one of these, let me
       type it". There is no assistance dropdown: an allocation does not
       name what is being given (Oct 2026, client decision). */

    public function store(Request $request)
    {
        /* ONE QUESTION: WHO (Oct 2026, client decision)

           This form used to ask which association, which assistance, cash
           or in-kind, how much, for which disaster, for which crop and from
           what date. The office does not work that way. Assistance arrives
           from time to time, the office decides which farmers are valid for
           it, and that is the whole decision.

           So what is validated here is a list of farmers and nothing else
           that identifies the assistance. The allocation is dated the day
           it is made, carries no name, no figure and no association, and
           the associations concerned are worked out from the farmers
           chosen. */
        $data = $request->validate([
            /* Required, unlike before. An allocation with nobody on it used
               to be meaningful - it was a pool sent to an association to
               divide up. It is meaningless now: an allocation IS its list
               of farmers, so an empty one would be a record of nothing, and
               nobody would be told anything. */
            'beneficiary_ids'   => ['required', 'array', 'min:1'],
            'beneficiary_ids.*' => ['integer', 'exists:farmers,id'],

            // Create Assistance Distribution (Oct 2026 revision)
            'batch_name'        => ['required', 'string', 'max:150'],
            'details'           => ['required', 'string', 'max:500'],
            'distribution_date' => ['nullable', 'date'],
            'venue'             => ['nullable', 'string', 'max:255'],
            'draft_id'          => ['nullable', 'integer'],

            'remarks'     => ['nullable', 'string', 'max:1000'],
            'documents'   => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'beneficiary_ids.required' => 'Please select at least one farmer to receive this assistance.',
            'beneficiary_ids.min'      => 'Please select at least one farmer to receive this assistance.',
            'batch_name.required'      => 'Please give this distribution batch a name.',
            'details.required'         => 'Please enter the assistance details.',
            'details.max'              => 'Assistance details can be up to 500 characters.',
            'documents.*.max'          => 'Each file must be 10 MB or smaller.',
            'documents.*.mimes'        => 'Only PDF, JPG or PNG files are accepted.',
        ]);

        $allocation = DB::transaction(function () use ($data, $request) {

            /* association_id, assistance_id, disaster_id, crop_id and the
               date are all left out. The first four no longer describe an
               allocation (see the class docblock) and the date is simply
               today: the office allocates when the assistance arrives, so
               there is no separate start date to record, and promising one
               would be promising the farmer a collection date the office
               cannot keep. */
            $allocation = AssistanceAllocation::create(Arr::only($data, ['remarks', 'batch_name', 'details', 'distribution_date', 'venue']) + [
                'allocated_by' => Auth::id(),
                'allocated_at' => now(),
                'status'       => 'allocated',
            ]);

            /* Re-checked against the same eligibility rule that built the
               checklist, because ids a form posted back are a claim, not a
               fact. Anything no longer eligible by the time this runs -
               designated on another allocation a minute ago, say - is
               silently dropped rather than written, which is why
               $designated below is counted from what was actually created
               and not from what was submitted. */
            $eligible = $this->qualifiedReports(null);

            $designated = 0;

            // The finished form replaces the draft it came from.
            if (! empty($data['draft_id'])) {
                AssistanceAllocationDraft::where('user_id', Auth::id())->where('id', $data['draft_id'])->delete();
            }

            foreach ($eligible->whereIn('farmer_id', $data['beneficiary_ids']) as $report) {
                AssistanceAllocationBeneficiary::create([
                    'assistance_allocation_id' => $allocation->id,
                    'farmer_id'                => $report->farmer_id,
                    'damage_report_id'         => $report->id,
                    'created_at'               => now(),
                ]);

                $designated++;
            }

            // Everyone ticked turned out to be designated elsewhere in the
            // meantime. Throwing inside the transaction rolls the empty
            // batch back instead of leaving a record of nothing.
            if ($designated === 0) {
                throw ValidationException::withMessages([
                    'beneficiary_ids' => 'None of the selected farmers are eligible any more. They may have just been designated in another batch. Please reselect.',
                ]);
            }

            // Supporting documents (the MAO endorsement memo, a beneficiary
            // list PDF, etc.) - all optional.
            foreach ($request->file('documents', []) as $file) {
                // Private disk, office-only route (revision section 3.4).
                // See AttachmentController::allocationDocument().
                $path = $file->store('assistance-allocation-documents', 'local');

                AssistanceAllocationDocument::create([
                    'assistance_allocation_id' => $allocation->id,
                    'file_path'   => $path,
                    'file_name'   => $file->getClientOriginalName(),
                    'file_size'   => $file->getSize(),
                    'mime_type'   => $file->getClientMimeType(),
                    'uploaded_by' => Auth::id(),
                    'created_at'  => now(),
                ]);
            }

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Finalized distribution batch "' . $allocation->batch_name . '" (AA-' . str_pad($allocation->id, 3, '0', STR_PAD_LEFT) . ')'
                    . ' for ' . $designated . ' ' . ($designated === 1 ? 'farmer' : 'farmers'),
                'target_table' => 'assistance_allocations',
                'target_id'    => $allocation->id,
                'created_at'   => now(),
            ]);

            return $allocation;
        });

        $this->notifyDesignated($allocation->fresh(['beneficiaries.farmer.association']));

        return redirect()->route('mao.assistance-allocations.show', $allocation)
            ->with('status', 'Distribution batch finalized. The farmers and their associations have been notified.');
    }

    /**
     * Tell the designated farmers, and tell their associations.
     *
     * WHY BOTH, AND WHY THEY SAY DIFFERENT THINGS (Oct 2026, client
     * decision)
     *
     * The office does not hand assistance out on a schedule. Assistance
     * arrives, the office decides who is valid for it, and the collection
     * day is arranged afterwards by the association that the farmer
     * actually knows and can walk to. So there are two notices with two
     * jobs.
     *
     * The farmer is told they have been designated and, explicitly, that
     * there is no date yet and their association will tell them when to
     * come. Saying that plainly is the point: a farmer told only that
     * assistance is coming will turn up at the office asking when, which is
     * the phone call this notice exists to prevent.
     *
     * The association is told which of its members were designated, by
     * name, and that telling them when to collect is its job. That is real
     * work landing on the officer, which is why this one is 'important'
     * while the farmer's is 'normal' - section 68 keeps SMS for urgent and
     * critical only, so both are in-app either way.
     *
     * A farmer with no association gets their notice and nobody gets the
     * other one. They are not dropped from the allocation for it; the
     * office will have to reach them directly, and the audit log and the
     * allocation's own page both show they were designated.
     *
     * Runs after the transaction has committed and never throws. The
     * allocation is real by the time this is called, and a notification
     * that could not be sent must not make it look otherwise.
     */
    private function notifyDesignated(AssistanceAllocation $allocation): void
    {
        $reference = 'AA-' . str_pad($allocation->id, 3, '0', STR_PAD_LEFT);

        foreach ($allocation->beneficiaries as $beneficiary) {
            if (! $beneficiary->farmer) {
                continue;
            }

            $this->sendAlert(
                'You Have Been Selected for Assistance',
                'The Municipal Agriculture Office has selected you to receive assistance'
                    . ($allocation->batch_name ? ' (' . $allocation->batch_name . ')' : '') . '. '
                    . $this->farmerScheduleText($allocation),
                'normal',
                'specific_farmer',
                $beneficiary->farmer_id,
                $allocation->id,
                'farmer ' . $beneficiary->farmer_id
            );
        }

        /* One notice per association, listing its own members and nobody
           else's. Grouping first is what keeps it to one: an officer whose
           association has nine designated members should get one message
           naming nine people, not nine messages. */
        $byAssociation = $allocation->beneficiaries
            ->filter(fn ($beneficiary) => $beneficiary->farmer?->association_id)
            ->groupBy(fn ($beneficiary) => $beneficiary->farmer->association_id);

        foreach ($byAssociation as $associationId => $members) {
            $names = $members
                ->map(fn ($beneficiary) => $beneficiary->farmer->full_name)
                ->sort()
                ->join(', ', ' and ');

            $this->sendAlert(
                'Your Members Have Been Selected for Assistance',
                'The Municipal Agriculture Office has selected ' . $members->count() . ' of your '
                    . ($members->count() === 1 ? 'members' : 'members') . ' to receive assistance ('
                    . $reference . ($allocation->batch_name ? ', ' . $allocation->batch_name : '') . '): ' . $names . '. '
                    . $this->associationScheduleText($allocation),
                'important',
                'specific_association',
                (int) $associationId,
                $allocation->id,
                'association ' . $associationId
            );
        }
    }

    private function whenWhere(AssistanceAllocation $allocation): string
    {
        return $allocation->distribution_date->format('F j, Y')
            . ($allocation->venue ? ' at ' . $allocation->venue : '');
    }

    private function farmerScheduleText(AssistanceAllocation $allocation): string
    {
        if ($allocation->distribution_date) {
            return 'Distribution is on ' . $this->whenWhere($allocation) . '. '
                . 'Ang pamamahagi po ay sa ' . $this->whenWhere($allocation) . '.';
        }

        return 'There is no collection date yet. Your Farmers\' Association will tell you when and '
            . 'where to collect it, so please wait for them to contact you. '
            . 'Wala pa pong takdang petsa. Ang inyong asosasyon po ang magsasabi kung kailan '
            . 'at saan ito kukunin.';
    }

    private function associationScheduleText(AssistanceAllocation $allocation): string
    {
        return $allocation->distribution_date
            ? 'Distribution is scheduled for ' . $this->whenWhere($allocation) . '. Please remind them.'
            : 'There is no collection date set. Please tell them when and where to collect it.';
    }

    /**
     * Set or change the distribution date and venue of a finalized batch.
     *
     * The date is optional when the batch is created because the office often
     * does not know it yet. Once it is known, this tells everyone concerned.
     */
    public function schedule(Request $request, AssistanceAllocation $allocation)
    {
        abort_if($allocation->status === 'cancelled', 404);

        $data = $request->validate([
            'distribution_date' => ['required', 'date'],
            'venue'             => ['nullable', 'string', 'max:255'],
        ], [
            'distribution_date.required' => 'Please choose the distribution date.',
        ]);

        $allocation->update($data);

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Set distribution schedule for AA-' . str_pad($allocation->id, 3, '0', STR_PAD_LEFT)
                . ': ' . $this->whenWhere($allocation->fresh()),
            'target_table' => 'assistance_allocations',
            'target_id'    => $allocation->id,
            'created_at'   => now(),
        ]);

        $fresh = $allocation->fresh(['beneficiaries.farmer.association']);

        foreach ($fresh->beneficiaries as $beneficiary) {
            if (! $beneficiary->farmer) {
                continue;
            }

            $this->sendAlert(
                'Distribution Schedule Set',
                ($fresh->batch_name ? $fresh->batch_name . ': ' : '') . $this->farmerScheduleText($fresh),
                'important',
                'specific_farmer',
                $beneficiary->farmer_id,
                $fresh->id,
                'farmer ' . $beneficiary->farmer_id
            );
        }

        $fresh->beneficiaries
            ->filter(fn ($b) => $b->farmer?->association_id)
            ->groupBy(fn ($b) => $b->farmer->association_id)
            ->each(function ($members, $associationId) use ($fresh) {
                $this->sendAlert(
                    'Distribution Schedule Set',
                    ($fresh->batch_name ? $fresh->batch_name . ': ' : '') . $this->associationScheduleText($fresh),
                    'important',
                    'specific_association',
                    (int) $associationId,
                    $fresh->id,
                    'association ' . $associationId
                );
            });

        return back()->with('status', 'Distribution schedule saved. The farmers and their associations have been notified.');
    }

    /**
     * One in-app alert, through the broadcast pipeline the notifications
     * table requires (notification_broadcast_id is NOT NULL, so writing to
     * notifications directly is not an option).
     *
     * link_type/link_id let the recipient's bell open this exact allocation
     * (NotificationBroadcast::linkUrl()).
     */
    private function sendAlert(
        string $title,
        string $message,
        string $priority,
        string $targetType,
        int $targetId,
        int $allocationId,
        string $who
    ): void {
        try {
            NotificationBroadcast::create([
                'title'       => $title,
                'message'     => $message,
                'category'    => 'assistance',
                'priority'    => $priority,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'link_type'   => 'assistance_allocation',
                'link_id'     => $allocationId,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ])->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify ' . $who . ' of allocation ' . $allocationId . ': ' . $e->getMessage());
        }
    }

    public function show(AssistanceAllocation $allocation)
    {
        $allocation->load(self::detailRelations());

        return view('mao.assistance-allocations.show', compact('allocation'));
    }

    /**
     * List + detail panel (Sept 2026): the same eager-load set used by
     * show() above, reused by history()'s inline "View Details" panel so
     * the two never drift apart.
     */
    private static function detailRelations(): array
    {
        return [
            /* 'assistance', 'disaster' and 'crop' were dropped in Oct 2026:
               an allocation no longer names any of the three. 'association'
               stays, because a row created before that change still records
               the one association it was addressed to and the panel still
               shows it where it is set. */
            'association',
            'allocatedBy',
            'distributedBy',
            'distributions.farmer',
            'distributions.damageReport',
            'distributions.distributedBy',
            'beneficiaries.farmer.barangay',
            'beneficiaries.farmer.association',
            // For the Crop line: the crops the designated reports are about.
            'beneficiaries.damageReport.crops.crop',
            'documents.uploadedBy',
        ];
    }

    /* =====================================================================
     | Handing assistance over to a farmer  (Oct 2026, client decision)
     |
     | This was the association's job, on its own Distribute screen. The
     | office does it here now and the association monitors, which is what
     | the master prompt's section 61 always said the association does. The
     | eligibility rule is unchanged in substance - the office may only hand
     | over to a farmer it designated on THIS allocation - and the quantity
     | half of the old flow is gone with every other figure.
     ===================================================================== */

    /** The form for handing one designated farmer their assistance. */
    public function distribute(AssistanceAllocation $allocation)
    {
        if (in_array($allocation->status, ['completed', 'cancelled'], true)) {
            return redirect()
                ->route('mao.assistance-allocations.show', $allocation)
                ->withErrors(['allocation' => 'This allocation is closed, so nothing more can be handed out from it.']);
        }

        return view('mao.assistance-allocations.distribute', [
            'allocation' => $allocation->load(['beneficiaries.farmer.association']),
            'eligible'   => $this->designatedFarmers($allocation),
        ]);
    }

    /**
     * Record that assistance was handed to a farmer.
     *
     * Section 91.9: reviewed on screen, confirmed in the dialog, and only
     * then written. Everything here is the server checking the same things
     * again, because a dropdown is a suggestion once the form has been
     * posted.
     */
    public function storeDistribution(Request $request, AssistanceAllocation $allocation)
    {
        if (in_array($allocation->status, ['completed', 'cancelled'], true)) {
            return back()->withErrors(['allocation' => 'This allocation is closed.']);
        }

        $data = $request->validate([
            'farmer_id' => ['required', Rule::exists('farmers', 'id')],

            // Section 64: every hand-over cites the damage report that made
            // the farmer eligible, so assistance can always be traced back
            // to the damage it answers.
            'damage_report_id' => ['required', Rule::exists('damage_reports', 'id')],

            'remarks'        => ['nullable', 'string', 'max:1000'],
            'distributed_at' => ['required', 'date', 'before_or_equal:today'],

            'photos'   => ['nullable', 'array', 'max:' . self::MAX_HANDOVER_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],
        ], [
            'farmer_id.required'        => 'Please choose the farmer who received the assistance.',
            'damage_report_id.required' => 'Please choose which damage report this assistance is for.',
            'distributed_at.required'   => 'Please give the date it was handed over.',
            'distributed_at.before_or_equal' => 'The hand-over date cannot be in the future.',
            'photos.*.max'              => 'Each photo must be 5 MB or smaller.',
        ]);

        $farmer = Farmer::findOrFail($data['farmer_id']);
        $report = DamageReport::findOrFail($data['damage_report_id']);

        $this->assertCanReceive($farmer, $report, $allocation);

        DB::transaction(function () use ($request, $allocation, $farmer, $report, $data) {

            $distribution = AssistanceDistribution::create([
                'assistance_allocation_id' => $allocation->id,
                'farmer_id'                => $farmer->id,
                'damage_report_id'         => $report->id,

                /* No quantity and no description. The allocation's own name
                   says what this is, and nothing records how much. The
                   columns are still on the table and are left null; see the
                   note on App\Models\Assistance for why they were not
                   dropped. */

                'distributed_by' => Auth::id(),
                'distributed_at' => $data['distributed_at'],
                'remarks'        => $data['remarks'] ?? null,

                // Two separate tracks. The office has handed it over;
                // whether the farmer agrees they received it is their own
                // answer to give, on their own Assistance page.
                'distribution_status' => 'distributed',
                'receipt_status'      => 'pending_confirmation',
            ]);

            /* The office's own proof, kept apart from anything the farmer
               uploads later.

               Private disk (revision section 3.4). These are the most
               sensitive files in the module: a photograph of a named farmer
               receiving assistance is a fact about that person's
               circumstances, and on the public disk it was readable without
               logging in. */
            foreach ($request->file('photos', []) as $photo) {
                $distribution->photos()->create([
                    'photo_type'  => 'receipt',
                    'file_path'   => $photo->store('distributions/' . $distribution->id, 'local'),
                    'uploaded_at' => now(),
                ]);
            }

            $this->refreshAllocationProgress($allocation);
            $this->notifyFarmerOfHandover($farmer, $allocation, $data['distributed_at']);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Handed ' . $allocation->display_name . ' to ' . $farmer->full_name
                    . ' for ' . $report->full_reference,
                'target_table' => 'assistance_distributions',
                'target_id'    => $distribution->id,
                'created_at'   => now(),
            ]);
        });

        return redirect()
            ->route('mao.assistance-allocations.show', $allocation)
            ->with('status', 'Hand-over recorded for ' . $farmer->full_name
                . '. They will be asked to confirm they received it.');
    }

    /** How many photos the office may attach as proof of one hand-over. */
    private const MAX_HANDOVER_PHOTOS = 6;

    /**
     * The farmers this allocation designated, with the reports that made
     * them eligible, so the form can offer them.
     *
     * Scoped to THIS allocation's own beneficiary rows and not to "anyone
     * with an eligible report", which is the rule BUILD-STATUS section 32
     * established and the reason the designation step means anything at all.
     */
    private function designatedFarmers(AssistanceAllocation $allocation)
    {
        $reportIds = $allocation->beneficiaries()->pluck('damage_report_id');

        return Farmer::whereHas('damageReports', fn ($q) => $q->whereIn('id', $reportIds))
            ->with([
                'barangay',
                'association',
                'damageReports' => fn ($q) => $q->whereIn('id', $reportIds)
                    ->with(['crops.crop', 'validation'])
                    ->latest(),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * May this farmer receive from THIS allocation?
     *
     * Three things have to hold, checked here rather than trusted to the
     * form. The last is the one that matters: it is not enough that the
     * farmer has an eligible report somewhere, the office has to have
     * designated them on this specific allocation.
     */
    private function assertCanReceive(Farmer $farmer, DamageReport $report, AssistanceAllocation $allocation): void
    {
        if ($report->farmer_id !== $farmer->id) {
            throw ValidationException::withMessages([
                'damage_report_id' => 'That damage report belongs to a different farmer.',
            ]);
        }

        if (! in_array($report->status, self::QUALIFYING_REPORT_STATUSES, true)) {
            throw ValidationException::withMessages([
                'damage_report_id' => 'Report ' . $report->full_reference . ' has not been inspected yet, '
                    . 'so assistance cannot be recorded against it.',
            ]);
        }

        $isDesignated = AssistanceAllocationBeneficiary::where('assistance_allocation_id', $allocation->id)
            ->where('farmer_id', $farmer->id)
            ->where('damage_report_id', $report->id)
            ->exists();

        if (! $isDesignated) {
            throw ValidationException::withMessages([
                'farmer_id' => $farmer->full_name . ' was not designated on this allocation, so assistance '
                    . 'cannot be recorded against them from it.',
            ]);
        }
    }

    /**
     * Move the allocation's own status along after a hand-over.
     *
     * COMPLETE MEANS EVERY DESIGNATED FARMER HAS THEIRS
     *
     * This used to compare a running quantity total against the allocated
     * quantity, which is how "completed" was decided. With no quantities
     * there is nothing to compare, and the obvious replacement is better
     * than what it replaces: an allocation is finished when every farmer it
     * designated has been handed something, which is a fact about people
     * rather than about arithmetic, and is what an officer means when they
     * say the batch is done.
     *
     * An allocation that designated nobody can never complete this way and
     * stays at 'distributed', which is correct: there is nobody to finish
     * with. The office can still close it by hand from updateStatus().
     *
     * Recalculated from the rows every time rather than incremented, so it
     * cannot drift.
     */
    private function refreshAllocationProgress(AssistanceAllocation $allocation): void
    {
        $designated = $allocation->beneficiaries()->count();

        $handedTo = $allocation->distributions()
            ->whereIn('distribution_status', ['distributed', 'completed'])
            ->distinct('farmer_id')
            ->count('farmer_id');

        $allocation->update([
            'status' => ($designated > 0 && $handedTo >= $designated) ? 'completed' : 'distributed',
            'distributed_to_association_at' => $allocation->distributed_to_association_at ?? now(),
            'distributed_by'                => $allocation->distributed_by ?? Auth::id(),
        ]);
    }

    /**
     * Tell the farmer, in the app (proposal section 66).
     *
     * No figure in the message, which is the whole point of the change: the
     * farmer is told what they were given and when, and asked to confirm.
     * 'normal' priority, because this is good news rather than an emergency
     * and section 68 reserves SMS for urgent matters.
     */
    private function notifyFarmerOfHandover(Farmer $farmer, AssistanceAllocation $allocation, string $when): void
    {
        try {
            NotificationBroadcast::create([
                'title'       => 'Assistance recorded: ' . $allocation->display_name,
                'message'     => 'The Municipal Agriculture Office recorded giving you '
                    . $allocation->display_name . ' on ' . Carbon::parse($when)->format('F d, Y')
                    . '. Please open your Assistance page and confirm whether you received it. '
                    . 'Pakikumpirma po sa inyong Assistance page kung natanggap ninyo ito.',
                'category'    => 'assistance',
                'priority'    => 'normal',
                'target_type' => 'specific_farmer',
                'target_id'   => $farmer->id,
                'link_type'   => 'assistance_allocation',
                'link_id'     => $allocation->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ])->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify farmer ' . $farmer->id
                . ' of an assistance hand-over: ' . $e->getMessage());
        }
    }

    /**
     * Move the allocation along its track. Every step is confirmed in the UI
     * and written to the audit log.
     */
    public function updateStatus(Request $request, AssistanceAllocation $allocation)
    {
        $data = $request->validate([
            'status'  => ['required', Rule::in(['distributed', 'completed', 'cancelled'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($allocation->status === 'completed') {
            return back()->withErrors(['status' => 'This allocation is already completed.']);
        }

        DB::transaction(function () use ($allocation, $data) {
            $changes = ['status' => $data['status']];

            if ($data['status'] === 'distributed') {
                $changes['distributed_to_association_at'] = now();
                $changes['distributed_by']                = Auth::id();
                // No distributed_quantity: there is no quantity to carry
                // over. The date and the officer are the whole record of
                // this step now.
            }

            if (! empty($data['remarks'])) {
                $changes['remarks'] = trim(($allocation->remarks ? $allocation->remarks . "\n" : '') . $data['remarks']);
            }

            $allocation->update($changes);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Set allocation AA-' . str_pad($allocation->id, 3, '0', STR_PAD_LEFT)
                    . ' to ' . $data['status'],
                'target_table' => 'assistance_allocations',
                'target_id'    => $allocation->id,
                'created_at'   => now(),
            ]);
        });

        return back()->with('status', 'Allocation marked as ' . $data['status'] . '.');
    }

    /* ==================================================================
     | The office's assistance decision on one case  (section 11.1, 11.2)
     ================================================================== */

    /**
     * Record that this farmer is, or is not, designated for assistance.
     *
     * WHY THIS EXISTS SEPARATELY FROM ALLOCATING
     *
     * Saying yes already had a home: include the farmer in an allocation and
     * a beneficiary row is written. Saying no had none. A farmer the office
     * had considered and passed over looked exactly like a farmer nobody had
     * read yet, so the queue never emptied and the farmer never got an
     * answer. Section 11.1 makes Not Designated one of the four statuses,
     * which means the office needs a way to put it on the record.
     *
     * A reason is required for a refusal and optional for a designation.
     * That asymmetry is deliberate: the allocation itself explains a yes,
     * while a no leaves nothing behind unless somebody writes it down, and
     * an officer asked about it three months later will have only this.
     *
     * WHAT IT REFUSES TO DO
     *
     * It will not decide a case the farmer has already confirmed receiving,
     * and it will not decide a report no technician has verified. The first
     * would let a click rewrite something that has already happened in the
     * real world; the second would have the office deciding on damage nobody
     * has been out to see (section 10.5 keeps those stages apart).
     */
    public function decide(Request $request, DamageReport $damageReport)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in([
                AssistanceDecision::DESIGNATED,
                AssistanceDecision::NOT_DESIGNATED,
            ])],
            // Required when refusing. Rule::requiredIf rather than a second
            // validate() call, so the farmer-facing message and the field
            // name stay with the rest of the rules.
            'remarks'  => [
                Rule::requiredIf(fn () => $request->input('decision') === AssistanceDecision::NOT_DESIGNATED),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'remarks.required' => 'Please give the reason this farmer is not designated. It is kept on the record and shown to them.',
        ]);

        $damageReport->load(AssistanceCase::eagerLoad());

        if (AssistanceCase::forReport($damageReport) === AssistanceCase::RECEIVED) {
            return back()->withErrors([
                'decision' => 'This farmer has already confirmed receiving assistance for this report, so the decision can no longer be changed.',
            ]);
        }

        if (! in_array($damageReport->status, ['verified', 'approved'], true)) {
            return back()->withErrors([
                'decision' => 'This report has not been verified by a technician yet, so there is nothing to decide on.',
            ]);
        }

        $designated = $data['decision'] === AssistanceDecision::DESIGNATED;

        DB::transaction(function () use ($damageReport, $data, $designated) {
            // updateOrCreate, not create: the office may have been waiting
            // on a document and is now answering properly. One row per
            // report is enforced by a unique index as well, so a double
            // submit cannot leave two contradictory answers behind.
            AssistanceDecision::updateOrCreate(
                ['damage_report_id' => $damageReport->id],
                [
                    'farmer_id'  => $damageReport->farmer_id,
                    'decision'   => $data['decision'],
                    'decided_by' => Auth::id(),
                    'decided_at' => now(),
                    'remarks'    => $data['remarks'] ?: null,
                ]
            );

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => ($designated ? 'Designated' : 'Did not designate')
                    . ' ' . ($damageReport->farmer?->full_name ?? 'a farmer')
                    . ' for assistance on report ' . $damageReport->full_reference,
                'target_table' => 'assistance_decisions',
                'target_id'    => $damageReport->id,
                'created_at'   => now(),
            ]);
        });

        $this->notifyFarmerOfDecision($damageReport, $designated, $data['remarks'] ?: null);

        return back()->with('status', $designated
            ? 'Farmer designated for assistance.'
            : 'Recorded as not designated for assistance.');
    }

    /**
     * Tell the farmer what was decided.
     *
     * Section 11.2 asks for the farmer and the association to be notified of
     * an assistance decision, and a refusal is exactly the case where
     * silence is worst: the farmer is waiting, and without this they wait
     * indefinitely for an answer that has in fact been given.
     *
     * 'important' priority, so in-app only and no SMS. Section 68 keeps SMS
     * for urgent and critical matters, and a decision the farmer can read
     * the next time they open the app is neither. The refusal carries its
     * reason in the message, because a "no" with no reason reads as a
     * mistake and generates a phone call to the office.
     *
     * Runs after the transaction has committed and never throws: a
     * notification failure must not make a decision that is already saved
     * look as though it failed.
     */
    private function notifyFarmerOfDecision(DamageReport $damageReport, bool $designated, ?string $remarks): void
    {
        if (! $damageReport->farmer_id) {
            return;
        }

        try {
            $alert = NotificationBroadcast::create([
                'title'   => $designated
                    ? 'Designated for Assistance'
                    : 'Not Designated for Assistance',
                'message' => $designated
                    ? 'The Municipal Agriculture Office has designated you for assistance on report '
                        . $damageReport->full_reference . '. Nothing has been handed over yet; you will be '
                        . 'notified again when your association distributes it.'
                    : 'The Municipal Agriculture Office has reviewed report ' . $damageReport->full_reference
                        . ' and you were not designated for assistance on this allocation.'
                        . ($remarks ? ' Reason: ' . $remarks : ''),
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
            Log::warning('Could not notify farmer ' . $damageReport->farmer_id
                . ' of the assistance decision on report ' . $damageReport->id . ': ' . $e->getMessage());
        }
    }
}
