<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\AssistanceAllocation;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Crop;
use App\Models\Disaster;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * MAO allocates assistance to a Farmers' Association, never to a farmer
 * directly. The association then records what it hands out to each member.
 */
class AssistanceAllocationController extends Controller
{
    public const STATUSES = ['pending', 'allocated', 'distributed', 'completed', 'cancelled'];

    public function index(Request $request)
    {
        $allocations = AssistanceAllocation::query()
            ->with(['assistance', 'association', 'disaster', 'crop', 'allocatedBy'])
            ->withCount('distributions')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('association_id'),
                fn ($query) => $query->where('association_id', $request->association_id))
            ->when($request->filled('assistance_id'),
                fn ($query) => $query->where('assistance_id', $request->assistance_id))
            ->when($request->filled('search'), fn ($query) => $query
                ->whereHas('assistance', fn ($a) => $a->where('name', 'like', '%' . $request->search . '%')))
            ->latest('allocated_at')
            ->paginate(15)
            ->withQueryString();

        $statusCounts = AssistanceAllocation::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = [
            'pending'  => (int) $statusCounts->get('pending', 0),
            'approved' => (int) $statusCounts->get('allocated', 0),
            'released' => (int) $statusCounts->get('distributed', 0) + (int) $statusCounts->get('completed', 0),
        ];

        return view('mao.assistance-allocations.index', [
            'allocations'  => $allocations,
            'summary'      => $summary,
            'statuses'     => self::STATUSES,
            // Filter dropdowns search historical data, so archived
            // associations still need to appear here.
            'associations' => Association::orderBy('name')->get(),
            'assistances'  => Assistance::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('mao.assistance-allocations.form', [
            // Types are needed so a brand new item can be defined right here
            // instead of sending the officer off to Settings first.
            'types'        => AssistanceController::TYPES,
            'assistances'  => Assistance::where('status', 'active')->orderBy('name')->get(),
            // A new allocation should never be pointed at an association,
            // crop or disaster that has been archived out of circulation
            // (see app/Models/Concerns/Archivable.php).
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
        // officer go to Settings, add it there, and come back. The catalogue
        // page still exists for editing and closing items; this is just the
        // shortcut for the common case of "we have something new to give out".
        $creatingNew = $request->input('assistance_id') === self::NEW_ASSISTANCE;

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
            'remarks'             => ['nullable', 'string', 'max:1000'],
        ], [
            'new_assistance_name.required' => 'Please name the new assistance item.',
            'new_assistance_type.required' => 'Please say whether the new item is cash or in kind.',
        ]);

        $allocation = DB::transaction(function () use ($data, $creatingNew) {

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
                'allocated_at' => now(),
                'status'       => 'allocated',
            ]);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Allocated assistance AA-' . str_pad($allocation->id, 3, '0', STR_PAD_LEFT)
                    . ' to ' . ($allocation->association?->name ?? 'an association'),
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
