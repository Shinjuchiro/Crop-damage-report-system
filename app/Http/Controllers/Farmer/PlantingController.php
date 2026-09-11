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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crop Planting Activity (Farmer side)
 *
 * Proposal sections 24 to 27.
 * The farmer records what crop they planted, when they planted it,
 * and how big the area was. That is all a planting record needs.
 *
 * This module also has a second job. Proposal section 22 says a farmer
 * becomes Inactive after 3 months with no activity, and section 21 says
 * we must NOT use damage reports for that (a farmer with no damage is
 * doing fine). So submitting a planting record is what counts as the
 * "qualifying activity" and resets the timer.
 */
class PlantingController extends Controller
{
    /**
     * List all planting records of the logged in farmer.
     */
    public function index()
    {
        $farmer = $this->farmer();

        return view('farmer.planting.index', [
            'farmer'  => $farmer,
            'records' => $farmer->plantingRecords()
                ->with('crops.crop')       // eager load so the table does not run extra queries
                ->withCount('crops')
                ->latest('date_submitted')
                ->latest('id')             // tie breaker if two were submitted the same day
                ->paginate(10),
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
            'crops'  => Crop::active()->orderBy('name')->get(),
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

        $record = DB::transaction(function () use ($farmer, $data) {

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

            // Section 26: this is a qualifying activity, so the farmer goes
            // back to Active and months inactive resets to 0.
            $farmer->recordQualifyingActivity();

            // Section 81: keep a history of important actions.
            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Submitted crop planting record with '
                                  . count($data['crops']) . ' crop(s)',
                'target_table' => 'crop_planting_records',
                'target_id'    => $record->id,
                'created_at'   => now(),
            ]);

            return $record;
        });

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
            'crops'                 => ['required', 'array', 'min:1', 'max:20'],
            'crops.*.crop_id'       => ['required', Rule::exists('crops', 'id')->whereNull('archived_at')],
            'crops.*.crop_specify'  => ['nullable', 'string', 'max:100'],

            // Cannot plant something in the future.
            'crops.*.date_planted'  => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],

            // 0 hectares is not a real planting, so the minimum is 0.01.
            'crops.*.area_hectares' => ['required', 'numeric', 'min:0.01', 'max:9999'],
        ], [
            // Plain error messages, because farmers will be reading these.
            'crops.required'                       => 'Please add at least one crop.',
            'crops.*.crop_id.required'             => 'Please choose a crop.',
            'crops.*.date_planted.required'        => 'Please give the date this crop was planted.',
            'crops.*.date_planted.before_or_equal' => 'The planting date cannot be in the future.',
            'crops.*.area_hectares.required'       => 'Please give the area planted, in hectares.',
            'crops.*.area_hectares.min'            => 'The area planted must be more than zero.',
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
