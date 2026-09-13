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
use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * A damage report counts toward assistance eligibility once a
     * technician has verified it (or MAO has gone further and approved it).
     * Kept as one constant so the allocation modal, the stat cards and the
     * export all agree on exactly the same rule.
     */
    private const QUALIFYING_REPORT_STATUSES = ['verified', 'approved'];

    /* =====================================================================
     | Assistance Allocation (the working screen)
     ===================================================================== */

    public function index(Request $request)
    {
        $overview = $this->buildOverview($request);

        return view('mao.assistance-allocations.index', $overview + [
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

        // First visit defaults to the most recent disaster so the page opens
        // with something meaningful on it. Once the person has explicitly
        // chosen "All Disasters" (an empty value that is still present in
        // the query string), that choice is respected instead.
        $disasterId = $request->has('disaster_id')
            ? ($request->filled('disaster_id') ? (int) $request->input('disaster_id') : null)
            : optional($disasters->first())->id;

        $associationId = $request->filled('association_id') ? (int) $request->input('association_id') : null;
        $assistanceId  = $request->filled('assistance_id') ? (int) $request->input('assistance_id') : null;
        $rowStatus     = $request->filled('status') ? $request->input('status') : null;

        $selectedDisaster = $disasterId ? $disasters->firstWhere('id', $disasterId) : null;

        /*
        |--------------------------------------------------------------------
        | Who actually qualifies right now
        |--------------------------------------------------------------------
        | "Verified" here means a technician has inspected the report (or
        | MAO has gone further and approved it) - see
        | self::QUALIFYING_REPORT_STATUSES. One row per farmer: their most
        | recent qualifying report for the selected disaster.
        */
        $verifiedReports = $this->qualifiedReports($disasterId);
        $qualifiedReports = $verifiedReports->filter(fn ($report) => $report->farmer->association_id !== null)->values();
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
            'total_verified_farmers'  => $verifiedReports->count(),
            'verified_from_associations' => $verifiedReports->pluck('farmer.association_id')->filter()->unique()->count(),
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
            'disaster_id'    => ['required', 'exists:disasters,id'],
        ]);

        $association = Association::findOrFail($data['association_id']);

        $beneficiaries = $this->qualifiedReports((int) $data['disaster_id'], (int) $data['association_id'])
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
     * One row per farmer: their most recent report that both (a) a
     * technician has verified (or MAO has approved) and (b) is tied to the
     * given disaster. Restricting to one association is optional, so the
     * same method drives both the office-wide stat cards and the modal's
     * per-association checklist.
     */
    private function qualifiedReports(?int $disasterId, ?int $associationId = null): Collection
    {
        if (! $disasterId) {
            return collect();
        }

        return DamageReport::query()
            ->whereIn('status', self::QUALIFYING_REPORT_STATUSES)
            ->whereHas('disasters', fn ($d) => $d->where('disasters.id', $disasterId))
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

        return view('mao.assistance-allocations.history', [
            'allocations'  => $allocations,
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

        return view('mao.assistance-allocations.distribution-tracking', [
            'distributions' => $distributions,
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

        return view('mao.assistance-allocations.disputes', [
            'disputes'     => $disputes,
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

        // The Allocate Assistance modal always carries this hidden field, so
        // store() can tell "came from the new modal, a disaster is required"
        // apart from the older standalone form, where a disaster stays
        // optional. Checking for it directly is more reliable than inferring
        // it from beneficiary_ids, which simply is not sent at all when MAO
        // unchecks every name in the list.
        $fromModal = $request->input('allocation_source') === 'modal';

        $data = $request->validate([
            'assistance_id'  => ['required', $creatingNew ? 'string' : 'exists:assistances,id'],
            'association_id' => ['required', 'exists:associations,id'],
            'disaster_id'    => [$fromModal ? 'required' : 'nullable', 'exists:disasters,id'],
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
            'disaster_id.required'         => 'Please select the disaster event this beneficiary list was qualified under.',
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
            // server-side.
            $beneficiaryIds = $data['beneficiary_ids'] ?? [];

            if (! empty($beneficiaryIds) && ! empty($data['disaster_id'])) {
                $eligible = $this->qualifiedReports((int) $data['disaster_id'], (int) $data['association_id']);

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

        return redirect()->route('mao.assistance-allocations.show', $allocation)
            ->with('status', $creatingNew
                ? 'Assistance created and allocated successfully. The new item is now in your assistance list.'
                : 'Assistance allocated successfully.');
    }

    public function show(AssistanceAllocation $allocation)
    {
        $allocation->load([
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
        ]);

        return view('mao.assistance-allocations.show', compact('allocation'));
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
