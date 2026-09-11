<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\AuditLog;
use App\Models\Crop;
use App\Models\Disaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The catalogue of assistance the MAO can hand out.
 *
 * An entry here is the pool ("Rice Seeds", "Financial Assistance"); allocating
 * it to an association happens in AssistanceAllocationController.
 */
class AssistanceController extends Controller
{
    public const TYPES    = ['cash', 'in_kind'];
    public const STATUSES = ['active', 'inactive', 'closed'];

    public function index(Request $request)
    {
        $assistances = Assistance::query()
            ->with(['disaster', 'crop'])
            ->withCount('allocations')
            ->withSum('allocations', 'allocated_quantity')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->type))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'),
                fn ($query) => $query->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('mao.assistance.index', [
            'assistances' => $assistances,
            'types'       => self::TYPES,
            'statuses'    => self::STATUSES,
        ]);
    }

    public function create()
    {
        return view('mao.assistance.form', $this->formData(null));
    }

    public function store(Request $request)
    {
        $assistance = Assistance::create($this->validatedData($request));

        $this->log('Created assistance: ' . $assistance->name, $assistance->id);

        return redirect()->route('mao.assistance.index')
            ->with('status', 'Assistance added successfully.');
    }

    public function edit(Assistance $assistance)
    {
        return view('mao.assistance.form', $this->formData($assistance));
    }

    public function update(Request $request, Assistance $assistance)
    {
        $assistance->update($this->validatedData($request));

        $this->log('Updated assistance: ' . $assistance->name, $assistance->id);

        return redirect()->route('mao.assistance.index')
            ->with('status', 'Assistance updated successfully.');
    }

    public function destroy(Assistance $assistance)
    {
        if (DB::table('assistance_allocations')->where('assistance_id', $assistance->id)->exists()) {
            return back()->withErrors([
                'assistance' => 'This assistance has already been allocated, so it cannot be deleted. Close it instead.',
            ]);
        }

        $name = $assistance->name;
        $assistance->delete();

        $this->log('Deleted assistance: ' . $name, null);

        return redirect()->route('mao.assistance.index')->with('status', 'Assistance deleted.');
    }

    /**
     * Archive rather than delete once assistance has already been allocated
     * (proposal section 72). Distinct from 'closed', which means the pool
     * itself ran out; 'inactive' just takes it off the active catalogue.
     */
    public function archive(Assistance $assistance)
    {
        $assistance->update(['status' => 'inactive']);

        $this->log('Archived assistance: ' . $assistance->name, $assistance->id);

        return back()->with('status', 'Assistance archived. It can no longer be allocated until restored.');
    }

    public function restore(Assistance $assistance)
    {
        $assistance->update(['status' => 'active']);

        $this->log('Restored assistance: ' . $assistance->name, $assistance->id);

        return back()->with('status', 'Assistance restored to the active catalogue.');
    }

    private function formData(?Assistance $assistance): array
    {
        return [
            'assistance' => $assistance,
            'types'      => self::TYPES,
            'statuses'   => self::STATUSES,
            // Archived events and crops should not be offered when defining
            // a NEW assistance item, though an existing one that already
            // points at one keeps showing it (see app/Models/Concerns/Archivable.php).
            'disasters'  => Disaster::active()->orderByDesc('date_start')->get()
                ->when($assistance?->disaster && $assistance->disaster->is_archived,
                    fn ($list) => $list->push($assistance->disaster)),
            'crops'      => Crop::active()->orderBy('name')->get()
                ->when($assistance?->crop && $assistance->crop->is_archived,
                    fn ($list) => $list->push($assistance->crop)),
        ];
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name'                         => ['required', 'string', 'max:255'],
            'type'                         => ['required', Rule::in(self::TYPES)],
            'description'                  => ['nullable', 'string', 'max:1000'],
            'disaster_id'                  => ['nullable', 'exists:disasters,id'],
            'crop_id'                      => ['nullable', 'exists:crops,id'],
            'available_quantity_or_amount' => ['nullable', 'numeric', 'min:0'],
            'status'                       => ['required', Rule::in(self::STATUSES)],
        ]);
    }

    private function log(string $action, ?int $targetId): void
    {
        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'target_table' => 'assistances',
            'target_id'    => $targetId,
            'created_at'   => now(),
        ]);
    }
}
