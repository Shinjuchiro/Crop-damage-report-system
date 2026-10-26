<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Crop;
use App\Models\CropPlantingRecord;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crop Planting Activity (Farmer side)
 *
 * Proposal sections 24 to 27.
 * The farmer records what crop they planted, when they planted it,
 * and how big the area was. That is all a planting record needs.
 *
 * This module also has a second job. Section 22 says a farmer becomes
 * Inactive after a stretch with no activity (Farmer::INACTIVITY_MONTHS,
 * seven months as the client and adviser settled it), and section 21 says
 * we must NOT use damage reports for that (a farmer with no damage is
 * doing fine). So submitting a planting record is what counts as the
 * "qualifying activity" and resets the timer.
 */
class PlantingController extends Controller
{
    /*
     | How many crops one planting record may carry.
     |
     | The same ceiling, and the same reasoning, as a damage report (see
     | DamageReportController::MAX_CROPS). The table behind this is still a
     | child table with no limit of its own, as section 79 requires; this
     | caps one submission so that "Add another crop" cannot be pressed
     | forever, and it is checked here as well as in the form because the
     | form's limit can be edited in the browser.
     |
     | A farmer planting more than five crops in one go records the rest as
     | a second planting activity, which is also closer to how planting
     | actually happens: different crops, different days.
     */
    public const MAX_CROPS = 5;

    /*
     | Photos of the planting (Oct 2026). Optional: a farmer with no camera, or
     | a field already planted weeks ago, still has to be able to record it.
     |
     | They go to the PRIVATE disk and are read back only through
     | AttachmentController::plantingPhoto(), the same as damage photos, so the
     | MAO and the farmer's own association officer can see them and nobody
     | else can.
     */
    public const MAX_PHOTOS = 5;
    private const PHOTO_DISK = 'local';
    private const PHOTO_DIR  = 'planting-photos';

    /**
     * List all planting records of the logged in farmer.
     */
    public function index(Request $request)
    {
        $farmer = $this->farmer();

        $records = $farmer->plantingRecords()
            ->with('crops.crop')       // eager load so the table does not run extra queries
            ->withCount('crops')
            ->latest('date_submitted')
            ->latest('id')             // tie breaker if two were submitted the same day
            ->paginate(10)
            ->withQueryString();

        /*
         | The record shown in the right-hand panel when a row is picked
         | with ?selected=<id>. Looked up through $farmer->plantingRecords()
         | rather than by id alone, so another farmer's id in the query
         | string finds nothing instead of finding their record.
         */
        $selected = $request->filled('selected')
            ? $farmer->plantingRecords()->with('crops.crop', 'photos', 'farmer.association', 'farmer.barangay')->find($request->query('selected'))
            : null;

        return view('farmer.planting.index', [
            'farmer'   => $farmer,
            'records'  => $records,
            'selected' => $selected,
            'backUrl'  => $request->fullUrlWithoutQuery('selected'),
        ]);
    }

    /**
     * Show the blank form.
     */
    public function create()
    {
        return view('farmer.planting.create', [
            'farmer' => $this->farmer(),
            // crop list is managed by MAO; archived crops are retired
            'crops'  => Crop::active()->inDisplayOrder()->get(),

            // So the form stops where the validation stops.
            'maxCrops'  => self::MAX_CROPS,
            'maxPhotos' => self::MAX_PHOTOS,
        ]);
    }

    /**
     * Save the planting record.
     *
     * Everything is inside a transaction. If saving one of the crops fails,
     * we do not want a planting record left in the database with no crops
     * in it, so the whole thing rolls back together.
     */
    public function store(Request $request)
    {
        $farmer = $this->farmer();
        $data   = $this->validated($request);

        $stored = [];

        try {
            $record = DB::transaction(function () use ($farmer, $data, $request, &$stored) {

                // The parent row first, because the crops need its id.
                $record = CropPlantingRecord::create([
                    'farmer_id'      => $farmer->id,
                    'date_submitted' => now()->toDateString(),
                ]);

                // Then one child row per crop.
                foreach ($data['crops'] as $crop) {
                    $record->crops()->create([
                        'crop_id'      => $crop['crop_id'],

                        // Only filled in when the crop is marked HVCC.
                        // "HVCC" by itself does not tell the office anything,
                        // so we ask which crop it actually is.
                        'crop_specify' => $crop['crop_specify'] ?? null,

                        'date_planted'  => $crop['date_planted'],
                        'area_hectares' => $crop['area_hectares'],
                    ]);
                }

                // Optional photos. Their paths are remembered so that, if anything
                // below fails and the database rolls back, the files that were
                // already written are removed too and nothing is left orphaned.
                $stored = $this->storePhotos($request, $record);

                // Section 26: this is a qualifying activity, so the farmer goes
                // back to Active and months inactive resets to 0.
                $farmer->recordQualifyingActivity();

                // Section 81: keep a history of important actions.
                AuditLog::create([
                    'user_id'      => Auth::id(),
                    'action'       => 'Submitted crop planting record with '
                                      . count($data['crops']) . ' crop(s) and '
                                      . count($stored) . ' photo(s)',
                    'target_table' => 'crop_planting_records',
                    'target_id'    => $record->id,
                    'created_at'   => now(),
                ]);

                return $record;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::PHOTO_DISK)->delete($stored);

            throw $e;
        }

        return redirect()
            ->route('farmer.planting.show', $record)
            ->with('status', 'Crop planting activity saved successfully. Naitala na po ang inyong pagtatanim.');
    }

    /**
     * Show one planting record.
     */
    public function show(CropPlantingRecord $planting)
    {
        $this->authorizeOwnership($planting);

        return view('farmer.planting.show', [
            'record' => $planting->load('crops.crop', 'photos'),
            'farmer' => $this->farmer(),
        ]);
    }

    /**
     * Correct a mistake in a record the farmer already submitted - wrong
     * crop, date or area. Sept 2026: farmers can fix their own records this
     * way, but (unlike MAO's monitoring page) cannot archive them - only the
     * office retires a record from the active list.
     */
    public function edit(CropPlantingRecord $planting)
    {
        $this->authorizeOwnership($planting);

        return view('farmer.planting.edit', [
            'record' => $planting->load('crops.crop', 'photos'),
            'farmer' => $this->farmer(),
            'crops'  => Crop::active()->inDisplayOrder()->get(),

            // So the form stops where the validation stops.
            'maxCrops'  => self::MAX_CROPS,
            'maxPhotos' => self::MAX_PHOTOS,
        ]);
    }

    public function update(Request $request, CropPlantingRecord $planting)
    {
        $this->authorizeOwnership($planting);

        $data = $this->validated($request);

        /*
         | Photos the farmer ticked off with the X on the edit form. Only ids
         | that belong to THIS record are honoured, so a made-up id in the
         | request cannot reach another farmer's photo.
         */
        $removeIds = collect((array) $request->input('remove_photos', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        $keepCount = $planting->photos()->whereNotIn('id', $removeIds)->count();
        $incoming  = count($request->file('photos', []));

        if ($keepCount + $incoming > self::MAX_PHOTOS) {
            throw ValidationException::withMessages([
                'photos' => 'A planting record can hold up to ' . self::MAX_PHOTOS
                    . ' photos. Please remove one before adding another.',
            ]);
        }

        $stored  = [];
        $removed = [];

        try {
            DB::transaction(function () use ($planting, $data, $request, $removeIds, &$stored, &$removed) {
                $planting->crops()->delete();

                foreach ($data['crops'] as $crop) {
                    $planting->crops()->create([
                        'crop_id'       => $crop['crop_id'],
                        'crop_specify'  => $crop['crop_specify'] ?? null,
                        'date_planted'  => $crop['date_planted'],
                        'area_hectares' => $crop['area_hectares'],
                    ]);
                }

                // Rows go inside the transaction; the files go after it
                // commits, so a rollback never leaves a row without its file.
                foreach ($planting->photos()->whereIn('id', $removeIds)->get() as $photo) {
                    $removed[] = $photo->file_path;
                    $photo->delete();
                }

                $stored = $this->storePhotos($request, $planting);

                AuditLog::create([
                    'user_id'      => Auth::id(),
                    'action'       => 'Corrected own crop planting record #' . $planting->id
                                      . ' (' . count($stored) . ' photo(s) added, '
                                      . count($removed) . ' removed)',
                    'target_table' => 'crop_planting_records',
                    'target_id'    => $planting->id,
                    'created_at'   => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk(self::PHOTO_DISK)->delete($stored);

            throw $e;
        }

        Storage::disk(self::PHOTO_DISK)->delete($removed);

        return redirect()->route('farmer.planting.show', $planting)
            ->with('status', 'Crop planting activity updated successfully.');
    }

    /* ==================================================================
     | Helper methods
     ================================================================== */

    /**
     * Validate the form.
     *
     * The form shows Crop 1 and Crop 2 at the start and has an
     * "Add another crop" button. Proposal section 79 says the DATABASE
     * must not be limited to two, so the crops arrive as an array and
     * each one becomes its own row in crop_planting_record_crops.
     * There are no crop_1 / crop_2 / crop_3 columns anywhere.
     */
    private function validated(Request $request): array
    {
        $hvcc = Crop::where('is_hvcc', true)->pluck('id')->all();

        // Crop 2 is optional, so if the farmer did not use it the block
        // arrives empty. We remove empty blocks BEFORE validating,
        // otherwise a farmer who planted one crop gets told that the
        // second crop is required, which is confusing.
        $request->merge([
            'crops' => collect($request->input('crops', []))
                ->filter(fn ($crop) => filled($crop['crop_id'] ?? null))
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'crops'                 => ['required', 'array', 'min:1', 'max:' . self::MAX_CROPS],
            'crops.*.crop_id'       => ['required', Rule::exists('crops', 'id')->whereNull('archived_at')],
            'crops.*.crop_specify'  => ['nullable', 'string', 'max:100'],

            // Cannot plant something in the future.
            'crops.*.date_planted'  => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],

            // 0 hectares is not a real planting, so the minimum is 0.01.
            'crops.*.area_hectares' => ['required', 'numeric', 'min:0.01', 'max:9999'],

            // Optional photos of the planting. 5120 KB = 5 MB each.
            'photos'                => ['nullable', 'array', 'max:' . self::MAX_PHOTOS],
            'photos.*'              => ['image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],
        ], [
            // Plain error messages, because farmers will be reading these.
            'crops.required'                       => 'Please add at least one crop.',
            'crops.max'                            => 'One planting record can cover up to ' . self::MAX_CROPS
                . ' crops. Please record the rest as a separate planting activity.',
            'crops.*.crop_id.required'             => 'Please choose a crop.',
            'crops.*.date_planted.required'        => 'Please give the date this crop was planted.',
            'crops.*.date_planted.before_or_equal' => 'The planting date cannot be in the future.',
            'crops.*.area_hectares.required'       => 'Please give the area planted, in hectares.',
            'crops.*.area_hectares.min'            => 'The area planted must be more than zero.',
            'photos.max'                           => 'Please upload no more than ' . self::MAX_PHOTOS . ' photos.',
            'photos.*.image'                       => 'Each file must be a photo.',
            'photos.*.mimes'                       => 'Photos must be JPG, PNG, WEBP or HEIC.',
            'photos.*.max'                         => 'Each photo must be 5 MB or smaller.',
            'photos.*.uploaded'                    => 'A photo could not be uploaded. Please try a smaller one.',
        ]);

        // Extra check that the normal rules cannot do: if the crop is a
        // High Value Commercial Crop, the farmer has to say which one.
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

    /**
     * Save the photos attached to the form and return their stored paths.
     *
     * Kept separate from the damage photos and the technician's inspection
     * photos: this is the farmer's own record of what was planted. Each record
     * gets its own folder so the disk does not become one huge directory.
     *
     * @return array<int, string>
     */
    private function storePhotos(Request $request, CropPlantingRecord $record): array
    {
        $paths = [];

        foreach ($request->file('photos', []) as $photo) {
            $path    = $photo->store(self::PHOTO_DIR . '/' . $record->id, self::PHOTO_DISK);
            $paths[] = $path;

            $record->photos()->create([
                'file_path'   => $path,
                'uploaded_at' => now(),
            ]);
        }

        return $paths;
    }

    /**
     * Get the logged in farmer.
     */
    private function farmer(): Farmer
    {
        return Farmer::with('association', 'barangay')
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    /**
     * Make sure a farmer can only open their OWN record.
     *
     * We check this in the controller, not just by hiding the link.
     * Hiding a link is not security: someone could still type the URL
     * with another id in it. (Proposal section 80.)
     */
    private function authorizeOwnership(CropPlantingRecord $record): void
    {
        abort_unless($record->farmer_id === $this->farmer()->id, 403);
    }
}
