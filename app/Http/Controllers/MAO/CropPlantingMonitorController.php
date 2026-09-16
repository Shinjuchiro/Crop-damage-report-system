<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\CropPlantingRecord;
use App\Models\CropPlantingRecordCrop;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Monitoring of what farmers have planted, plus (Sept 2026) the Edit and
 * Archive pair every other MAO list already has. Editing here is meant for
 * correcting a farmer's mistake (wrong crop, date or area) - it never
 * touches which farmer the record belongs to, and it never replays the
 * Active/Inactive history: Farmer::sweepInactive()/recordQualifyingActivity()
 * only ever look at current state going forward, so archiving or editing a
 * past record leaves a farmer's already-computed status exactly as it was.
 *
 * One row per planted crop, since a single submission can carry several crops.
 */
class CropPlantingMonitorController extends Controller
{
    public function index(Request $request)
    {
        // Section 22's 3-month rule: keep it current before the
        // ?activity_status= filter below runs against it.
        Farmer::sweepInactive();

        $plantings = CropPlantingRecordCrop::query()
            ->with([
                'crop',
                'plantingRecord.farmer.user',
                'plantingRecord.farmer.association',
                'plantingRecord.farmer.barangay',
            ])
            ->whereHas('plantingRecord', fn ($q) => $q->notDeleted()->whereNull('archived_at'))
            ->when($request->filled('crop_id'),
                fn ($query) => $query->where('crop_id', $request->crop_id))
            ->whereHas('plantingRecord.farmer', function ($farmer) use ($request) {
                $farmer
                    ->when($request->filled('association_id'),
                        fn ($query) => $query->where('association_id', $request->association_id))
                    ->when($request->filled('barangay_id'),
                        fn ($query) => $query->where('barangay_id', $request->barangay_id))
                    ->when($request->filled('activity_status'),
                        fn ($query) => $query->where('activity_status', $request->activity_status))
                    ->when($request->filled('search'), function ($query) use ($request) {
                        $term = '%' . $request->search . '%';

                        $query->where(fn ($sub) => $sub
                            ->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                    });
            })
            ->orderByDesc('date_planted')
            ->paginate(15)
            ->withQueryString();

        $monthStart = Carbon::now()->startOfMonth();

        $summary = [
            'records'       => CropPlantingRecord::notDeleted()->whereNull('archived_at')->count(),
            'this_month'    => CropPlantingRecord::notDeleted()->whereNull('archived_at')
                ->where('date_submitted', '>=', $monthStart)->count(),
            'farmers'       => CropPlantingRecord::notDeleted()->whereNull('archived_at')
                ->distinct('farmer_id')->count('farmer_id'),
            'area_planted'  => (float) DB::table('crop_planting_record_crops')
                ->join('crop_planting_records', 'crop_planting_records.id', '=', 'crop_planting_record_crops.crop_planting_record_id')
                ->whereNull('crop_planting_records.archived_at')
                ->whereNull('crop_planting_records.deleted_at')
                ->sum('crop_planting_record_crops.area_hectares'),
        ];

        // List + detail panel (Sept 2026): "View" loads the record inline
        // in the right-hand panel via ?selected=<crop_planting_record_id>,
        // instead of navigating to the separate show page.
        $selected = $request->filled('selected')
            ? CropPlantingRecord::query()->with($this->detailRelations())->find($request->selected)
            : null;

        return view('mao.crop-planting.index', [
            'plantings'    => $plantings,
            'selected'     => $selected,
            'summary'      => $summary,
            'associations' => Association::orderBy('name')->get(),
            'barangays'    => Barangay::orderBy('name')->get(),
            'crops'        => Crop::orderBy('name')->get(),
        ]);
    }

    public function show(CropPlantingRecord $plantingRecord)
    {
        $plantingRecord->load($this->detailRelations());

        return view('mao.crop-planting.show', compact('plantingRecord'));
    }

    private function detailRelations(): array
    {
        return [
            'farmer.user',
            'farmer.association',
            'farmer.barangay',
            'crops.crop',
            'photos',
            'archivedBy',
        ];
    }

    /**
     * Correct a farmer's mistake - wrong crop, date or area. Same form shape
     * as the farmer's own create form (Crop 1 required, Crop 2+ optional),
     * prefilled with the record's current crops.
     */
    public function edit(CropPlantingRecord $plantingRecord)
    {
        $plantingRecord->load('crops.crop', 'farmer.user');

        return view('mao.crop-planting.edit', [
            'plantingRecord' => $plantingRecord,
            'crops'          => Crop::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CropPlantingRecord $plantingRecord)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($plantingRecord, $data) {
            // Simplest correct way to reconcile an arbitrary-length list of
            // crop rows against another arbitrary-length list: replace them
            // wholesale inside the transaction, same pattern the farmer's own
            // store() uses to create them in the first place.
            $plantingRecord->crops()->delete();

            foreach ($data['crops'] as $crop) {
                $plantingRecord->crops()->create([
                    'crop_id'       => $crop['crop_id'],
                    'crop_specify'  => $crop['crop_specify'] ?? null,
                    'date_planted'  => $crop['date_planted'],
                    'area_hectares' => $crop['area_hectares'],
                ]);
            }

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Corrected crop planting record #' . $plantingRecord->id,
                'target_table' => 'crop_planting_records',
                'target_id'    => $plantingRecord->id,
                'created_at'   => now(),
            ]);
        });

        return redirect()->route('mao.crop-planting.show', $plantingRecord)
            ->with('status', 'Planting record updated successfully.');
    }

    /**
     * Take the record out of the active monitoring list without losing it -
     * it stays fully intact for audit purposes and never changes the
     * farmer's already-computed Active/Inactive history. See
     * app/Models/Concerns/Archivable.php.
     */
    public function archive(CropPlantingRecord $plantingRecord)
    {
        $plantingRecord->archive(Auth::id());

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Archived crop planting record #' . $plantingRecord->id,
            'target_table' => 'crop_planting_records',
            'target_id'    => $plantingRecord->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Planting record archived.');
    }

    public function restore(CropPlantingRecord $plantingRecord)
    {
        if ($plantingRecord->is_deleted) {
            return back()->withErrors(['plantingRecord' => 'This record was permanently deleted and can no longer be restored.']);
        }

        $plantingRecord->unarchive();

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Restored crop planting record #' . $plantingRecord->id,
            'target_table' => 'crop_planting_records',
            'target_id'    => $plantingRecord->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Planting record restored.');
    }

    public function destroy(CropPlantingRecord $plantingRecord)
    {
        $plantingRecord->markDeleted(Auth::id());

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Deleted crop planting record #' . $plantingRecord->id,
            'target_table' => 'crop_planting_records',
            'target_id'    => $plantingRecord->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Planting record deleted. Its data is kept for audit purposes.');
    }

    /**
     * Same shape and rules as Farmer\PlantingController::validated() - kept
     * as its own copy rather than a shared trait, since the two forms serve
     * different audiences (MAO correcting vs. a farmer submitting) and are
     * small enough that duplicating them is clearer than an abstraction that
     * would need to flex for both.
     */
    private function validated(Request $request): array
    {
        $hvcc = Crop::where('is_hvcc', true)->pluck('id')->all();

        $request->merge([
            'crops' => collect($request->input('crops', []))
                ->filter(fn ($crop) => filled($crop['crop_id'] ?? null))
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'crops'                 => ['required', 'array', 'min:1', 'max:20'],
            'crops.*.crop_id'       => ['required', Rule::exists('crops', 'id')->whereNull('archived_at')],
            'crops.*.crop_specify'  => ['nullable', 'string', 'max:100'],
            'crops.*.date_planted'  => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'crops.*.area_hectares' => ['required', 'numeric', 'min:0.01', 'max:9999'],
        ], [
            'crops.required'                       => 'Please add at least one crop.',
            'crops.*.crop_id.required'             => 'Please choose a crop.',
            'crops.*.date_planted.required'        => 'Please give the date this crop was planted.',
            'crops.*.date_planted.before_or_equal' => 'The planting date cannot be in the future.',
            'crops.*.area_hectares.required'       => 'Please give the area planted, in hectares.',
            'crops.*.area_hectares.min'            => 'The area planted must be more than zero.',
        ]);

        foreach ($validated['crops'] as $index => $crop) {
            if (in_array((int) $crop['crop_id'], $hvcc, true) && blank($crop['crop_specify'] ?? null)) {
                throw ValidationException::withMessages([
                    "crops.{$index}.crop_specify" =>
                        'Please say which high value crop this is, for example Ampalaya, Eggplant or Mango.',
                ]);
            }
        }

        return $validated;
    }
}
