<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\AuditLog;
use App\Models\Crop;
use App\Models\Disaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->notDeleted()
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

        // List + detail panel (Sept 2026, matching every other MAO list):
        // "View" loads the item inline in the right-hand panel via
        // ?selected=<id> instead of the row just carrying Edit/Archive/Restore.
        $selected = $request->filled('selected')
            ? Assistance::with(['disaster', 'crop'])
                ->withCount('allocations')
                ->withSum('allocations', 'allocated_quantity')
                ->find($request->selected)
            : null;

        return view('mao.assistance.index', [
            'assistances' => $assistances,
            'types'       => self::TYPES,
            'statuses'    => self::STATUSES,
            'selected'    => $selected,
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

    /**
     * Take the assistance item out of the working system for good. Not a
     * database delete: markDeleted() only stamps deleted_at/deleted_by, so
     * every allocation and distribution already made against it keeps
     * resolving its name exactly as before.
     */
    public function destroy(Assistance $assistance)
    {
        $name = $assistance->name;
        $assistance->markDeleted(Auth::id());

        $this->log('Deleted assistance: ' . $name, $assistance->id);

        // back() rather than a fixed route: reused by both the Assistance
        // catalogue and the Archive page's permanent-delete action.
        return back()->with('status', 'Assistance deleted. Its data is kept for audit purposes.');
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
        if ($assistance->is_deleted) {
            return back()->withErrors(['assistance' => 'This assistance item was permanently deleted and can no longer be restored.']);
        }

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
