<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\AssistanceAllocation;
use App\Models\AssistanceAllocationBeneficiary;
use App\Models\AssistanceAllocationDocument;
use App\Models\AssistanceDistribution;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\NotificationBroadcast;
use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * MAO allocates assistance to a Farmers' Association, never to a farmer
 * directly. The association then records what it hands out to each member,
 * and the farmer separately confirms whether they actually received it.
 *
 * This controller covers three screens plus one JSON endpoint:
 *
 *   index()                the working "Assistance Allocation" screen -
 *                           who is eligible right now, who still needs an
 *                           allocation, and the shortcut to create one
 *   history()               every allocation ever made, searchable
 *   distributionTracking()  every association -> farmer hand-out, office-wide
 *   eligibleBeneficiaries()  JSON: who qualifies for a given association +
 *                           disaster, used to fill the allocation modal's
 *                           checklist without a full page reload
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
     * A report sitting here has been inspected by a technician but MAO has
     * not decided anything about it yet. This is the "Total Verified
     * Farmers" stat - farmers waiting on an MAO decision, not (yet) farmers
     * who qualify for assistance. See section 31.
     */
    private const PENDING_REVIEW_STATUS = 'verified';

    /**
     * A damage report only counts toward assistance eligibility once MAO
     * has actually approved it. "Verified" on its own just means a
     * technician inspected the report - it does not mean MAO agreed the
     * farmer should receive help, so it used to also appear here, which
     * made "Total Verified Farmers" and "Qualified Beneficiaries" count the
     * exact same reports (section 31). Kept as one constant so the
     * allocation modal, the stat cards and the export all agree on exactly
     * the same rule.
     */
    private const QUALIFYING_REPORT_STATUSES = ['approved'];

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
            'assistances'  => Assistance::where('status', 'active')->orderBy('name')->get(),
            'associations' => Association::active()->orderBy('name')->get(),
            'disasters'    => Disaster::active()->orderByDesc('date_start')->get(),
            'crops'        => Crop::active()->orderBy('name')->get(),
            'types'        => AssistanceController::TYPES,
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
        $assistanceId  = $request->filled('assistance_id') ? (int) $request->input('assistance_id') : null;
        $rowStatus     = $request->filled('status') ? $request->input('status') : null;

        $selectedDisaster = $disasterId ? $disasters->firstWhere('id', $disasterId) : null;

        /*
        |--------------------------------------------------------------------
        | Two disjoint pipeline stages (section 31)
        |--------------------------------------------------------------------
        | "Total Verified Farmers" = a technician has inspected the report
        | but MAO has not decided anything yet (status "verified" only - see
        | self::PENDING_REVIEW_STATUS). "Qualified Beneficiaries" = MAO has
        | approved the report AND it is not already spoken for by another,
        | still-active allocation (see self::QUALIFYING_REPORT_STATUSES and
        | the whereDoesntHave() clause inside qualifiedReports()). A report
        | only ever counts toward one of these two stats, never both, and a
        | farmer drops out of "Qualified Beneficiaries" the moment MAO
        | includes them in a new allocation - they do not need to wait for
        | the association to actually distribute anything.
        */
        $pendingReviewReports = $this->pendingReviewReports($disasterId);
        $qualifiedReports = $this->qualifiedReports($disasterId)
            ->filter(fn ($report) => $report->farmer->association_id !== null)
            ->values();
        $qualifiedByAssociation = $qualifiedReports->groupBy(fn ($report) => $report->farmer->association_id);

        $allocationsInScope = AssistanceAllocation::query()
            ->when($disasterId, fn ($q) => $q->where('disaster_id', $disasterId))
            ->when($assistanceId, fn ($q) => $q->where('assistance_id', $assistanceId))
            ->where('status', '!=', 'cancelled')
            ->with('assistance')
            ->orderByDesc('allocated_at')
            ->get()
            ->groupBy('association_id');

        $stats = [
            'total_verified_farmers'  => $pendingReviewReports->count(),
            'verified_from_associations' => $pendingReviewReports->pluck('farmer.association_id')->filter()->unique()->count(),
            'qualified_beneficiaries' => $qualifiedReports->count(),
            'pending_allocation'      => $qualifiedByAssociation->keys()->diff($allocationsInScope->keys())->count(),
            'total_associations'      => $allocationsInScope->keys()->filter()->count(),
        ];

        /*
        |--------------------------------------------------------------------
        | Association Allocation Overview (paginated, filterable by status)
        |--------------------------------------------------------------------
        */
        $rows = Association::active()->orderBy('name')->get()->map(function ($association) use ($qualifiedByAssociation, $allocationsInScope) {
            $qualifiedCount = $qualifiedByAssociation->get($association->id, collect())->count();
            $allocation     = $allocationsInScope->get($association->id)?->first();

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
        $recentAllocations = AssistanceAllocation::with(['assistance', 'association'])
            ->withCount('beneficiaries')
            ->latest('allocated_at')
            ->take(5)
            ->get();

        return [
            'stats'              => $stats,
            'overviewRows'       => $overviewRows,
            // The same rows, unpaginated - exportOverview() downloads all of
            // them, not just the page currently on screen.
            'allOverviewRows'    => $rows,
            'recentAllocations'  => $recentAllocations,
            'disasters'          => $disasters,
            'selectedDisaster'   => $selectedDisaster,
            'filters'            => [
                'disaster_id'    => $request->has('disaster_id') ? $request->input('disaster_id') : $disasterId,
                'association_id' => $associationId,
                'assistance_id'  => $assistanceId,
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
            fputcsv($out, ['Association', 'Qualified Beneficiaries', 'Assistance', 'Status']);

            // The full filtered list, not just the page currently on screen -
            // a paginated CSV would be a strange thing to hand someone.
            foreach ($overview['allOverviewRows'] as $row) {
                fputcsv($out, [
                    $row->association->name,
                    $row->qualified_count,
                    $row->allocation?->assistance?->name ?? '-',
                    ucwords(str_replace('_', ' ', $row->status)),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * JSON: which farmers in this association qualify for this disaster.
     * Feeds the "Select Beneficiaries" checklist in the Allocate Assistance
     * modal without a full page reload.
     */
    public function eligibleBeneficiaries(Request $request)
    {
        $data = $request->validate([
            'association_id' => ['required', 'exists:associations,id'],
            // Optional (see qualifiedReports()): the modal can build the
            // whole-association checklist without a disaster being picked
            // at all.
            'disaster_id'    => ['nullable', 'exists:disasters,id'],
        ]);

        $association = Association::findOrFail($data['association_id']);

        $beneficiaries = $this->qualifiedReports(
                isset($data['disaster_id']) ? (int) $data['disaster_id'] : null,
                (int) $data['association_id']
            )
            ->map(fn ($report) => [
                'farmer_id'        => $report->farmer_id,
                'damage_report_id' => $report->id,
                'name'             => $report->farmer->full_name,
                'barangay'         => $report->farmer->barangay?->name ?? '-',
                'severity_label'   => $report->validation && $report->validation->severity
                    ? (Validation::SEVERITY_SCALE[$report->validation->severity]['label'] ?? '-')
                    : '-',
            ])
            ->values();

        return response()->json([
            'association'   => ['id' => $association->id, 'name' => $association->name],
            'beneficiaries' => $beneficiaries,
        ]);
    }

    /**
     * One row per farmer: their most recent report matching $statuses.
     * Restricting to a disaster and/or one association is optional, so the
     * same method backs both the office-wide stat cards and the modal's
     * per-association checklist. Shared by qualifiedReports() and
     * pendingReviewReports() so the two stages never quietly drift apart -
     * see section 31.
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
     * Farmers waiting on an MAO decision: a technician has verified the
     * report, but MAO has not approved, rejected or flagged it yet. Feeds
     * the "Total Verified Farmers" stat only - see section 31.
     */
    private function pendingReviewReports(?int $disasterId, ?int $associationId = null): Collection
    {
        return $this->reportsByStatus([self::PENDING_REVIEW_STATUS], $disasterId, $associationId);
    }

    /**
     * Farmers who actually qualify for assistance right now: MAO has
     * approved their report and it is not already covered by another
     * active allocation. Feeds "Qualified Beneficiaries", the
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
        $allocations = AssistanceAllocation::query()
            ->with(['assistance', 'association', 'disaster', 'crop', 'allocatedBy'])
            ->withCount(['distributions', 'beneficiaries'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('association_id'),
                fn ($q) => $q->where('association_id', $request->association_id))
            ->when($request->filled('assistance_id'),
                fn ($q) => $q->where('assistance_id', $request->assistance_id))
            ->when($request->filled('search'), fn ($q) => $q
                ->whereHas('assistance', fn ($a) => $a->where('name', 'like', '%' . $request->search . '%')))
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
            'assistances'  => Assistance::orderBy('name')->get(),
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
     * Every association -> farmer hand-out, office-wide. Where Allocation
     * History shows what MAO sent to associations, this shows what
     * associations actually did with it - and whether the farmer confirms
     * receiving it. The Disputes list is the "not received" slice of
     * exactly this same table.
     */
    public function distributionTracking(Request $request)
    {
        $distributions = AssistanceDistribution::query()
            ->with(['farmer', 'allocation.assistance', 'allocation.association', 'distributedBy'])
            ->when($request->filled('association_id'), fn ($q) => $q
                ->whereHas('allocation', fn ($a) => $a->where('association_id', $request->association_id)))
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
            ? AssistanceDistribution::with(['farmer', 'allocation.assistance', 'allocation.association', 'distributedBy'])
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
                'farmer',
                'allocation.assistance',
                'allocation.association',
                'allocation.disaster',
                'damageReport',
                'distributedBy',
            ])
            ->when($request->filled('association_id'), fn ($query) => $query
                ->whereHas('allocation', fn ($a) => $a->where('association_id', $request->association_id)))
            ->orderByDesc('receipt_confirmed_at')
            ->paginate(15)
            ->withQueryString();

        // List + detail panel (Sept 2026, matching every other MAO list):
        // "View" loads the disputed distribution inline via ?selected=<id>.
        $selected = $request->filled('selected')
            ? AssistanceDistribution::with([
                    'farmer', 'allocation.assistance', 'allocation.association',
                    'allocation.disaster', 'damageReport', 'distributedBy',
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

    public function create()
    {
        return view('mao.assistance-allocations.form', [
            'types'        => AssistanceController::TYPES,
            'assistances'  => Assistance::where('status', 'active')->orderBy('name')->get(),
            'associations' => Association::active()->orderBy('name')->get(),
            'disasters'    => Disaster::active()->orderByDesc('date_start')->get(),
            'crops'        => Crop::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * The value the Assistance dropdown uses for "I want to make a new one".
     * Not an id, so it can never collide with a real row.
     */
    public const NEW_ASSISTANCE = '__new__';

    public function store(Request $request)
    {
        // Creating the assistance item on the spot rather than making the
        // officer go to Settings, add it there, and come back.
        $creatingNew = $request->input('assistance_id') === self::NEW_ASSISTANCE;

        // A disaster event is optional from either source now (Section 8's
        // rule for the farmer's own damage report applies here too: not
        // every assistance item is tied to one specific declared event - a
        // general seed subsidy or a routine input give-away has nowhere
        // sensible to attach a disaster_id). Picking one narrows the
        // qualified-beneficiary checklist below to reports citing that
        // event; leaving it blank still builds a real checklist, of every
        // verified/approved farmer in the association regardless of
        // disaster - see qualifiedReports() and section 29.

        $data = $request->validate([
            'assistance_id'  => ['required', $creatingNew ? 'string' : 'exists:assistances,id'],
            'association_id' => ['required', 'exists:associations,id'],
            'disaster_id'    => ['nullable', 'exists:disasters,id'],
            'crop_id'        => ['nullable', 'exists:crops,id'],

            // The new catalogue entry, only when one is being made.
            'new_assistance_name' => [
                Rule::requiredIf($creatingNew), 'nullable', 'string', 'max:255',
            ],
            'new_assistance_type' => [
                Rule::requiredIf($creatingNew), 'nullable', Rule::in(AssistanceController::TYPES),
            ],
            'new_assistance_description' => ['nullable', 'string', 'max:1000'],
            'new_assistance_available'   => ['nullable', 'numeric', 'min:0'],

            'in_kind_description' => ['nullable', 'string', 'max:255'],
            'allocated_quantity'  => ['nullable', 'numeric', 'min:0'],
            'start_date'          => ['nullable', 'date'],
            'remarks'             => ['nullable', 'string', 'max:1000'],

            // The MAO-reviewed beneficiary list. Optional even when the
            // modal is the source, since MAO can uncheck every name and
            // still send the pool to the association.
            'beneficiary_ids'   => ['nullable', 'array'],
            'beneficiary_ids.*' => ['integer', 'exists:farmers,id'],

            'documents'   => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'new_assistance_name.required' => 'Please name the new assistance item.',
            'new_assistance_type.required' => 'Please say whether the new item is cash or in kind.',
            'documents.*.max'              => 'Each file must be 10 MB or smaller.',
            'documents.*.mimes'            => 'Only PDF, JPG or PNG files are accepted.',
        ]);

        $allocation = DB::transaction(function () use ($data, $creatingNew, $request) {

            // Make the catalogue entry first, so the allocation has something
            // real to point at. Both happen in one transaction: if the
            // allocation fails we do not leave a stray item behind.
            if ($creatingNew) {
                $assistance = Assistance::create([
                    'name'        => $data['new_assistance_name'],
                    'type'        => $data['new_assistance_type'],
                    'description' => $data['new_assistance_description'] ?? null,
                    'disaster_id' => $data['disaster_id'] ?? null,
                    'crop_id'     => $data['crop_id'] ?? null,
                    'available_quantity_or_amount' => $data['new_assistance_available'] ?? null,
                    'status'      => 'active',
                ]);

                $data['assistance_id'] = $assistance->id;

                AuditLog::create([
                    'user_id'      => Auth::id(),
                    'action'       => 'Created assistance while allocating: ' . $assistance->name,
                    'target_table' => 'assistances',
                    'target_id'    => $assistance->id,
                    'created_at'   => now(),
                ]);
            }

            $allocation = AssistanceAllocation::create(Arr::only($data, [
                'assistance_id', 'association_id', 'disaster_id', 'crop_id',
                'in_kind_description', 'allocated_quantity', 'remarks',
            ]) + [
                'allocated_by' => Auth::id(),
                'allocated_at' => ! empty($data['start_date']) ? Carbon::parse($data['start_date']) : now(),
                'status'       => 'allocated',
            ]);

            // The MAO-reviewed beneficiary list. Re-checked against the same
            // eligibility rule the modal used to build the checklist -
            // never trust ids a form posted back without verifying them
            // server-side. A disaster is not required for this check any
            // more than it is in qualifiedReports() itself - an allocation
            // with no disaster_id can still have a real, MAO-reviewed
            // beneficiary list.
            $beneficiaryIds = $data['beneficiary_ids'] ?? [];

            if (! empty($beneficiaryIds)) {
                $eligible = $this->qualifiedReports(
                    ! empty($data['disaster_id']) ? (int) $data['disaster_id'] : null,
                    (int) $data['association_id']
                );

                foreach ($eligible->whereIn('farmer_id', $beneficiaryIds) as $report) {
                    AssistanceAllocationBeneficiary::create([
                        'assistance_allocation_id' => $allocation->id,
                        'farmer_id'                => $report->farmer_id,
                        'damage_report_id'         => $report->id,
                        'created_at'               => now(),
                    ]);
                }
            }

            // Supporting documents (the MAO endorsement memo, a beneficiary
            // list PDF, etc.) - all optional.
            foreach ($request->file('documents', []) as $file) {
                $path = $file->store('assistance-allocation-documents', 'public');

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
                'action'       => 'Allocated assistance AA-' . str_pad($allocation->id, 3, '0', STR_PAD_LEFT)
                    . ' to ' . ($allocation->association?->name ?? 'an association')
                    . (count($beneficiaryIds) ? ' for ' . count($beneficiaryIds) . ' beneficiary/beneficiaries' : ''),
                'target_table' => 'assistance_allocations',
                'target_id'    => $allocation->id,
                'created_at'   => now(),
            ]);

            return $allocation;
        });

        $this->notifyAssociationOfAllocation($allocation);

        return redirect()->route('mao.assistance-allocations.show', $allocation)
            ->with('status', $creatingNew
                ? 'Assistance created and allocated successfully. The new item is now in your assistance list.'
                : 'Assistance allocated successfully.');
    }

    /**
     * Proposal section 66: an association should be told about "assistance
     * allocation/distribution updates" - this was previously missing (only
     * the association's own later distribution step notified anyone, the
     * farmer). Runs after the transaction above has already committed, and
     * never throws - the allocation itself must never appear to fail just
     * because a notification could not be sent.
     *
     * 'important' priority (in-app only, section 68 reserves SMS for urgent/
     * critical matters): real work waiting for the association (deciding who
     * to distribute it to), more than routine but not an emergency.
     * link_type/link_id let the association's bell open this exact
     * allocation (NotificationBroadcast::linkUrl()).
     */
    private function notifyAssociationOfAllocation(AssistanceAllocation $allocation): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Assistance Allocated',
                'message'     => 'The Municipal Agriculture Office has allocated '
                    . ($allocation->assistance?->name ?? 'assistance') . ' to your association. '
                    . 'Please review it and distribute it to your eligible members.',
                'category'    => 'assistance',
                'priority'    => 'important',
                'target_type' => 'specific_association',
                'target_id'   => $allocation->association_id,
                'link_type'   => 'assistance_allocation',
                'link_id'     => $allocation->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify association ' . $allocation->association_id
                . ' of allocation ' . $allocation->id . ': ' . $e->getMessage());
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
            'assistance',
            'association',
            'disaster',
            'crop',
            'allocatedBy',
            'distributedBy',
            'distributions.farmer',
            'distributions.damageReport',
            'distributions.distributedBy',
            'beneficiaries.farmer.barangay',
            'documents.uploadedBy',
        ];
    }

    /**
     * Move the allocation along its track. Every step is confirmed in the UI
     * and written to the audit log.
     */
    public function updateStatus(Request $request, AssistanceAllocation $allocation)
    {
        $data = $request->validate([
            'status'              => ['required', Rule::in(['distributed', 'completed', 'cancelled'])],
            'distributed_quantity'=> ['nullable', 'numeric', 'min:0'],
            'remarks'             => ['nullable', 'string', 'max:1000'],
        ]);

        if ($allocation->status === 'completed') {
            return back()->withErrors(['status' => 'This allocation is already completed.']);
        }

        DB::transaction(function () use ($allocation, $data) {
            $changes = ['status' => $data['status']];

            if ($data['status'] === 'distributed') {
                $changes['distributed_to_association_at'] = now();
                $changes['distributed_by']                = Auth::id();
                $changes['distributed_quantity']          = $data['distributed_quantity']
                    ?? $allocation->allocated_quantity;
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
}
