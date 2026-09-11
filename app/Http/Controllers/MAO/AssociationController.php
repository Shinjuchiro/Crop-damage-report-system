<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * MAO manages the Farmers' Associations. Assistance is always allocated to an
 * association, so an association with members or allocations is never deleted.
 */
class AssociationController extends Controller
{
    public function index(Request $request)
    {
        // Archived associations move to the Archive page (proposal section 72).
        $associations = Association::active()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';
                $query->where(fn ($sub) => $sub->where('name', 'like', $term)
                    ->orWhere('location', 'like', $term));
            })
            ->with('barangay')
            ->withCount(['farmers', 'officers', 'assistanceAllocations'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('mao.associations.index', compact('associations'));
    }

    public function create()
    {
        return view('mao.associations.form', [
            'association' => null,
            'barangays'   => Barangay::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $association = Association::create($this->validatedData($request));

        $this->log('Created association: ' . $association->name, $association->id);

        return redirect()->route('mao.associations.index')
            ->with('status', 'Farmers\' Association added successfully.');
    }

    public function edit(Association $association)
    {
        return view('mao.associations.form', [
            'association' => $association,
            'barangays'   => Barangay::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Association $association)
    {
        $association->update($this->validatedData($request, $association));

        $this->log('Updated association: ' . $association->name, $association->id);

        return redirect()->route('mao.associations.index')
            ->with('status', 'Farmers\' Association updated successfully.');
    }

    public function destroy(Association $association)
    {
        if ($this->timesUsed($association) > 0) {
            return back()->withErrors([
                'association' => 'This association already has members, officers or assistance records, so it cannot be deleted. Archive it instead.',
            ]);
        }

        $name = $association->name;
        $association->delete();

        $this->log('Deleted association: ' . $name, null);

        return redirect()->route('mao.associations.index')
            ->with('status', 'Farmers\' Association deleted.');
    }

    /**
     * Take the association out of circulation without losing its farmers,
     * officers or assistance history.
     */
    public function archive(Association $association)
    {
        if ($association->farmers()->whereHas('user', fn ($q) => $q->where('status', 'active'))->exists()) {
            return back()->withErrors([
                'association' => 'This association still has active farmer members. Move or archive its members first.',
            ]);
        }

        $association->archive(Auth::id());

        $this->log('Archived association: ' . $association->name, $association->id);

        return back()->with('status', 'Farmers\' Association archived.');
    }

    public function restore(Association $association)
    {
        $association->unarchive();

        $this->log('Restored association: ' . $association->name, $association->id);

        return back()->with('status', 'Farmers\' Association restored.');
    }

    private function validatedData(Request $request, ?Association $association = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255',
                Rule::unique('associations', 'name')->ignore($association?->id)],
            'location'    => ['nullable', 'string', 'max:100'],
            'barangay_id' => ['nullable', 'exists:barangays,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function timesUsed(Association $association): int
    {
        return DB::table('farmers')->where('association_id', $association->id)->count()
            + DB::table('association_officers')->where('association_id', $association->id)->count()
            + DB::table('assistance_allocations')->where('association_id', $association->id)->count();
    }

    private function log(string $action, ?int $targetId): void
    {
        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'target_table' => 'associations',
            'target_id'    => $targetId,
            'created_at'   => now(),
        ]);
    }
}
