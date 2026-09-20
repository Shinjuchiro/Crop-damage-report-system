<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Crop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * MAO manages the crop reference list. Crops are referenced by farmer profiles,
 * planting records and damage reports, so deleting one never breaks those -
 * see destroy() and app/Models/Concerns/SoftDeletable.php.
 */
class CropController extends Controller
{
    public function index(Request $request)
    {
        // Archived crops move to the Archive page (proposal section 72),
        // so this management list only ever shows the ones still in use.
        $crops = Crop::active()
            ->when($request->filled('search'),
                fn ($query) => $query->where('name', 'like', '%' . $request->search . '%'))
            ->withCount(['mainCrops', 'plantingRecordCrops', 'damageReportCrops'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // List + detail panel (Sept 2026, matching every other MAO list):
        // "View" loads the crop inline in the right-hand panel via
        // ?selected=<id> instead of the row just carrying Edit/Archive.
        $selected = $request->filled('selected')
            ? Crop::withCount(['mainCrops', 'plantingRecordCrops', 'damageReportCrops'])->find($request->selected)
            : null;

        return view('mao.crops.index', compact('crops', 'selected'));
    }

    public function create()
    {
        return view('mao.crops.form', ['crop' => null]);
    }

    public function store(Request $request)
    {
        $crop = Crop::create($this->validatedData($request));

        $this->log('Created crop: ' . $crop->name, $crop->id);

        return redirect()->route('mao.crops.index')
            ->with('status', 'Crop added successfully.');
    }

    public function edit(Crop $crop)
    {
        return view('mao.crops.form', compact('crop'));
    }

    public function update(Request $request, Crop $crop)
    {
        $crop->update($this->validatedData($request, $crop));

        $this->log('Updated crop: ' . $crop->name, $crop->id);

        return redirect()->route('mao.crops.index')
            ->with('status', 'Crop updated successfully.');
    }

    /**
     * Take the crop out of the working system for good. Not a database
     * delete: markDeleted() only stamps deleted_at/deleted_by, so every
     * farmer profile, planting record and damage report that already cites
     * this crop keeps resolving its name exactly as before (see
     * app/Models/Concerns/SoftDeletable.php).
     */
    public function destroy(Crop $crop)
    {
        $name = $crop->name;
        $crop->markDeleted(Auth::id());

        $this->log('Deleted crop: ' . $name, $crop->id);

        // back() rather than a fixed route: this action is reused by both the
        // Crops list (an unarchived crop) and the Archive page (one already
        // archived), so it returns the MAO to whichever of the two they were on.
        return back()->with('status', 'Crop deleted. Its data is kept for audit purposes.');
    }

    /**
     * Take the crop out of circulation without losing the records that
     * already cite it. See app/Models/Concerns/Archivable.php.
     */
    public function archive(Crop $crop)
    {
        $crop->archive(Auth::id());

        $this->log('Archived crop: ' . $crop->name, $crop->id);

        return back()->with('status', 'Crop archived. It will no longer appear as a choice on new forms.');
    }

    public function restore(Crop $crop)
    {
        if ($crop->is_deleted) {
            return back()->withErrors(['crop' => 'This crop was permanently deleted and can no longer be restored.']);
        }

        $crop->unarchive();

        $this->log('Restored crop: ' . $crop->name, $crop->id);

        return back()->with('status', 'Crop restored.');
    }

    private function validatedData(Request $request, ?Crop $crop = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('crops', 'name')->ignore($crop?->id)],
        ]);

        $data['is_hvcc'] = $request->boolean('is_hvcc');

        return $data;
    }

    private function log(string $action, ?int $targetId): void
    {
        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'target_table' => 'crops',
            'target_id'    => $targetId,
            'created_at'   => now(),
        ]);
    }
}
