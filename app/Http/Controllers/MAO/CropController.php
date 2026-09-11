<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Crop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * MAO manages the crop reference list. Crops are referenced by farmer profiles,
 * planting records and damage reports, so a crop that is already in use is
 * never deleted - it is renamed instead.
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

        return view('mao.crops.index', compact('crops'));
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

    public function destroy(Crop $crop)
    {
        if ($this->timesUsed($crop) > 0) {
            return back()->withErrors([
                'crop' => 'This crop is already used in farmer records, so it cannot be deleted. Archive it instead.',
            ]);
        }

        $name = $crop->name;
        $crop->delete();

        $this->log('Deleted crop: ' . $name, null);

        return redirect()->route('mao.crops.index')
            ->with('status', 'Crop deleted.');
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

    /**
     * How many records point at this crop. Anything above zero blocks deletion.
     */
    private function timesUsed(Crop $crop): int
    {
        return DB::table('farmer_main_crops')->where('crop_id', $crop->id)->count()
            + DB::table('crop_planting_record_crops')->where('crop_id', $crop->id)->count()
            + DB::table('damage_report_crops')->where('crop_id', $crop->id)->count()
            + DB::table('assistances')->where('crop_id', $crop->id)->count()
            + DB::table('assistance_allocations')->where('crop_id', $crop->id)->count();
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
