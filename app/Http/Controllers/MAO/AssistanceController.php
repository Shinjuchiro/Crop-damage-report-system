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
 * An entry here is the kind of assistance ("Rice Seeds", "Fertilizer");
 * allocating it happens in AssistanceAllocationController.
 *
 * It used to be a POOL, with a type and an available quantity that
 * allocations drew down. Both are gone (Oct 2026): an item is now a name, a
 * description and what it is for, with no figure anywhere. See the note on
 * App\Models\Assistance for the client's reason, which is privacy.
 */
class AssistanceController extends Controller
{
    /* TYPES IS GONE (Oct 2026, client decision)
 
       It held ['cash', 'in_kind']. An assistance item is no longer
       classified, and no figure is recorded against one, because how much
       a named farmer received is a financial detail about an identifiable
       person and the client asked for it to stay out of this system. See
       the note on App\Models\Assistance.
 
       Anything that imported AssistanceController::TYPES was updated in
       the same pass; nothing should reintroduce it. */
    public const STATUSES = ['active', 'inactive', 'closed'];

    public function index(Request $request)
    {
        $assistances = Assistance::query()
            ->notDeleted()
            ->with(['disaster', 'crop'])
            ->withCount('allocations')
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
                ->find($request->selected)
            : null;

        return view('mao.assistance.index', [
            'assistances' => $assistances,
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
            'statuses'   => self::STATUSES,
            // Archived events and crops should not be offered when defining
            // a NEW assistance item, though an existing one that already
            // points at one keeps showing it (see app/Models/Concerns/Archivable.php).
            'disasters'  => Disaster::active()->orderByDesc('date_start')->get()
                ->when($assistance?->disaster && $assistance->disaster->is_archived,
                    fn ($list) => $list->push($assistance->disaster)),
            'crops'      => Crop::active()->inDisplayOrder()->get()
                ->when($assistance?->crop && $assistance->crop->is_archived,
                    fn ($list) => $list->push($assistance->crop)),
        ];
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'disaster_id' => ['nullable', 'exists:disasters,id'],
            'crop_id'     => ['nullable', 'exists:crops,id'],
            'status'      => ['required', Rule::in(self::STATUSES)],
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
