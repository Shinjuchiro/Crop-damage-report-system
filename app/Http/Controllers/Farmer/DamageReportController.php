<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crop Damage Reporting (Farmer side)
 *
 * Proposal sections 29 to 38. This is the most important form in the
 * whole system, so a few rules from the prompt are worth repeating:
 *
 *  - The FARMER creates the report, not the technician (section 29).
 *    The technician later inspects THIS record. They do not make a
 *    second report.
 *  - Nothing the technician enters overwrites what the farmer wrote
 *    here (sections 45 and 49).
 *  - One report can have many crops and many disasters, so both are
 *    stored in child tables and not in crop_1 / crop_2 columns
 *    (section 79).
 */
class DamageReportController extends Controller
{
    // Photos go to storage/app/public, which is served through the
    // "storage" symlink. Run: php artisan storage:link
    private const PHOTO_DISK = 'public';
    private const PHOTO_DIR  = 'damage-reports';
    private const MAX_PHOTOS = 10;

    /**
     * "My Reports" page.
     */
    public function index()
    {
        $farmer = $this->farmer();

        return view('farmer.reports.index', [
            'farmer'  => $farmer,
            'reports' => $farmer->damageReports()
                // Load everything the list needs in one go, otherwise
                // each card would fire its own queries (N+1 problem).
                ->with('crops.crop', 'disasters', 'assignedTechnician', 'validation')
                ->withCount('photos')
                ->latest()
                ->paginate(10),
        ]);
    }

    /**
     * Show the blank report form.
     */
    public function create()
    {
        $farmer = $this->farmer();

        return view('farmer.reports.create', [
            'farmer' => $farmer,

            // Crops, disasters and barangays are managed by MAO in Settings.
            // The farmer only chooses from them. Archived items are retired
            // from these lists (see app/Models/Concerns/Archivable.php).
            'crops'     => Crop::active()->orderBy('name')->get(),
            'disasters' => Disaster::active()->orderByDesc('date_start')->orderBy('name')->get(),
            'barangays' => Barangay::orderBy('name')->get(),

            // The cause list lives on the model, not in the database, because
            // it is a fixed vocabulary and not something the office edits.
            'causes' => DamageReport::CAUSES,

            // Which causes currently have a declared event to link to. Used by
            // the form to decide whether to ask for one at all.
            'causesWithEvents' => Disaster::distinct()->pluck('type')->all(),
        ]);
    }

    /**
     * Save the report, its crops, its disasters and its photos.
     *
     * All in one transaction. A report saved without its crops, or with
     * half its photos missing, would be worse than no report at all.
     */
    public function store(Request $request)
    {
        $farmer = $this->farmer();
        $data   = $this->validated($request);

        $report = DB::transaction(function () use ($request, $farmer, $data) {

            // 1. The report itself.
            $report = DamageReport::create([
                'farmer_id' => $farmer->id,

                // What actually ruined the crop. Always answered, whether or
                // not the office has declared an event for it.
                'damage_cause'       => $data['damage_cause'],
                'damage_cause_other' => $data['damage_cause'] === 'other'
                                            ? ($data['damage_cause_other'] ?? null) : null,

                'farm_location_description' => $data['farm_location_description'],

                // If the farmer did not pick a barangay we fall back to the
                // one on their profile.
                'reported_barangay_id' => $data['reported_barangay_id'] ?? $farmer->barangay_id,

                // Section 37: this is the FARMER-reported location.
                // The technician's verified location is saved separately
                // in the validations table and never replaces this one.
                'reported_latitude'  => $data['reported_latitude'] ?? null,
                'reported_longitude' => $data['reported_longitude'] ?? null,
                'location_source'    => $data['location_source'] ?? 'none',

                'description' => $data['description'] ?? null,

                // Section 38: a new report always starts as Pending.
                // Only MAO can move it to Assigned.
                'status' => 'pending',
            ]);

            // 2. One row per damaged crop.
            foreach ($data['crops'] as $crop) {
                $report->crops()->create([
                    'crop_id'                  => $crop['crop_id'],
                    'crop_specify'             => $crop['crop_specify'] ?? null,
                    'damaged_area_hectares'    => $crop['damaged_area_hectares'],
                    'date_planted'             => $crop['date_planted'],
                    'estimated_damage_percent' => $crop['estimated_damage_percent'],
                    'production_cost'          => $crop['production_cost'],
                    'farmgate_price_per_kg'    => $crop['farmgate_price_per_kg'],

                    // We compute this instead of asking the farmer to type it,
                    // so it is always consistent. The formula is
                    // production cost x damage percent, and the form shows
                    // the formula on screen so the number is not a mystery.
                    'total_damage_cost' => round(
                        $crop['production_cost'] * ($crop['estimated_damage_percent'] / 100),
                        2
                    ),
                ]);
            }

            // 3. Declared disaster events, when the farmer linked any.
            // sync() fills the damage_report_disasters pivot table for us.
            // An empty array is fine: pest, disease and heat damage have no
            // declared event, and the cause above already records what it was.
            // Each row is tagged as farmer-linked so a technician correcting
            // or adding to this list later (Technician\InspectionController)
            // can tell which ones were the farmer's own choice.
            $report->disasters()->sync(
                collect($data['disasters'] ?? [])->mapWithKeys(fn ($disasterId) => [
                    $disasterId => [
                        'linked_by'      => Auth::id(),
                        'linked_by_role' => 'farmer',
                        'created_at'     => now(),
                    ],
                ])->all()
            );

            // 4. Photos.
            $this->storePhotos($request, $report);

            // 5. Audit trail (section 81).
            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Submitted damage report ' . $report->reference
                                  . ' covering ' . count($data['crops']) . ' crop(s), cause: '
                                  . $report->damage_cause_label,
                'target_table' => 'damage_reports',
                'target_id'    => $report->id,
                'created_at'   => now(),
            ]);

            return $report;
        });

        return redirect()
            ->route('farmer.reports.show', $report)
            ->with('status', 'Damage report submitted successfully. Naisumite na po ang inyong ulat.');
    }

    /**
     * Show one report, including the technician's inspection once it exists.
     */
    public function show(DamageReport $report)
    {
        $farmer = $this->farmer();

        // Same rule as planting: you can only open your own report.
        abort_unless($report->farmer_id === $farmer->id, 403);

        return view('farmer.reports.show', [
            'farmer' => $farmer,
            'report' => $report->load([
                'crops.crop', 'disasters', 'photos',
                'assignedTechnician', 'reportedBarangay',
                'validation.technician', 'validation.photos',
            ]),
        ]);
    }

    /* ==================================================================
     | Helper methods
     ================================================================== */

    /**
     * Validate the whole form.
     */
    private function validated(Request $request): array
    {
        // Same idea as the planting form. Crop 2 and Disaster 2 are
        // optional, so empty blocks are removed before validation runs.
        $request->merge([
            'crops' => collect($request->input('crops', []))
                ->filter(fn ($crop) => filled($crop['crop_id'] ?? null))
                ->values()
                ->all(),

            'disasters' => collect($request->input('disasters', []))
                ->filter(fn ($id) => filled($id))
                ->unique()          // no point recording the same typhoon twice
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            // What ruined the crop. Required on every report, and always
            // answerable: the list covers pests, disease and heat as well as
            // the weather events the office declares.
            'damage_cause'       => ['required', Rule::in(array_keys(DamageReport::CAUSES))],
            'damage_cause_other' => [
                Rule::requiredIf(fn () => $request->input('damage_cause') === 'other'),
                'nullable', 'string', 'max:120',
            ],

            'farm_location_description' => ['required', 'string', 'max:500'],
            'reported_barangay_id'      => ['nullable', Rule::exists('barangays', 'id')],
            'description'               => ['nullable', 'string', 'max:2000'],

            // Coordinates are NOT required on purpose. A farmer with no
            // signal in the field, or an old phone with no GPS, still has
            // to be able to report. They describe the location in words
            // instead. (Section 37 asks for GPS but also allows manual.)
            'reported_latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'reported_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_source'    => ['nullable', Rule::in(['gps', 'manual', 'none'])],

            // Section 30: the fields the prompt says a damage report must have.
            'crops'                            => ['required', 'array', 'min:1', 'max:20'],
            'crops.*.crop_id'                  => ['required', Rule::exists('crops', 'id')->whereNull('archived_at')],
            'crops.*.crop_specify'             => ['nullable', 'string', 'max:100'],
            'crops.*.damaged_area_hectares'    => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'crops.*.date_planted'             => ['required', 'date', 'before_or_equal:today'],
            'crops.*.estimated_damage_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'crops.*.production_cost'          => ['required', 'numeric', 'min:0', 'max:99999999'],
            'crops.*.farmgate_price_per_kg'    => ['required', 'numeric', 'min:0', 'max:99999'],

            // Section 33 asked for Disaster 1 to be required. It no longer is,
            // because a declared event only exists for weather. See
            // assertDisasterLinkedWhenDeclared() below for the rule that
            // replaced it.
            'disasters'   => ['nullable', 'array'],
            'disasters.*' => [Rule::exists('disasters', 'id')->whereNull('archived_at')],

            // Section 35: validate file type and size before storing anything.
            'photos'   => ['nullable', 'array', 'max:' . self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],   // 5120 KB = 5 MB
        ], [
            'farm_location_description.required'        => 'Please describe where the damaged farm is.',
            'crops.required'                            => 'Please add at least one damaged crop.',
            'crops.*.crop_id.required'                  => 'Please choose the damaged crop.',
            'crops.*.damaged_area_hectares.required'    => 'Please give the damaged area in hectares.',
            'crops.*.estimated_damage_percent.required' => 'Please give your estimate of the damage.',
            'crops.*.production_cost.required'          => 'Please give the production cost, including labour.',
            'crops.*.farmgate_price_per_kg.required'    => 'Please give the farmgate price per kilogram.',
            'damage_cause.required'                     => 'Please choose what caused the damage.',
            'damage_cause_other.required'               => 'Please say briefly what caused the damage.',
            'photos.max'                                => 'Please upload no more than ' . self::MAX_PHOTOS . ' photos.',
            'photos.*.image'                            => 'Each file must be a photo.',
            'photos.*.max'                              => 'Each photo must be 5 MB or smaller.',
        ]);

        // Three checks the normal rules cannot do on their own.
        $this->assertHvccSpecified($validated['crops']);
        $this->assertDamagedAreaFits($validated['crops']);
        $this->assertDisasterLinkedWhenDeclared($validated);

        // Latitude with no longitude (or the other way round) is useless,
        // so we ask for both or neither.
        if (filled($validated['reported_latitude'] ?? null) xor filled($validated['reported_longitude'] ?? null)) {
            throw ValidationException::withMessages([
                'reported_latitude' => 'Please give both the latitude and the longitude, or leave both blank.',
            ]);
        }

        // If there are no coordinates then the source is "none",
        // no matter what the hidden field says.
        if (blank($validated['reported_latitude'] ?? null)) {
            $validated['location_source'] = 'none';
        }

        return $validated;
    }

    /**
     * Link the report to a declared event, but only when there is one.
     *
     * This is what replaced "Disaster 1 is required" from section 33.
     *
     * The old rule blocked every report until the MAO had created a disaster
     * record, which meant a farmer losing corn to army worm could not file at
     * all. But the office still wants typhoon and flood reports grouped under
     * the event they belong to, otherwise the monthly report cannot say how
     * much damage Typhoon Kristine did.
     *
     * So: if the farmer says the cause was weather, AND the office has
     * actually declared an event of that kind, they have to say which one.
     * In every other case the cause on the report is the whole answer and
     * nothing is blocked.
     */
    private function assertDisasterLinkedWhenDeclared(array $validated): void
    {
        $cause = $validated['damage_cause'];

        if (! in_array($cause, DamageReport::WEATHER_CAUSES, true)) {
            return;                       // pest, disease, heat, other
        }

        if (filled($validated['disasters'] ?? [])) {
            return;                       // they already linked one
        }

        // Nothing declared of this kind yet, so there is nothing to link to
        // and the report goes through as it is. An archived event does not
        // count either: it is exactly what happens once the office archives
        // last season's typhoon, and new reports should not be forced to
        // link to a closed-out record.
        if (! Disaster::active()->where('type', $cause)->exists()) {
            return;
        }

        // The labels read "Typhoon / Bagyo", so take the English half for
        // the middle of a sentence.
        $label = strtolower(explode(' / ', DamageReport::CAUSES[$cause])[0]);

        throw ValidationException::withMessages([
            'disasters' => 'The office has already recorded ' . $label
                . ' events. Please choose the one that damaged your crop, so your report is counted with it.',
        ]);
    }

    /**
     * If the crop is a High Value Commercial Crop, ask which one.
     */
    private function assertHvccSpecified(array $crops): void
    {
        $hvcc = Crop::where('is_hvcc', true)->pluck('id')->all();

        foreach ($crops as $index => $crop) {
            if (in_array((int) $crop['crop_id'], $hvcc, true) && blank($crop['crop_specify'] ?? null)) {
                throw ValidationException::withMessages([
                    "crops.{$index}.crop_specify" =>
                        'Please say which high value crop this is, for example Ampalaya, Eggplant or Mango.',
                ]);
            }
        }
    }

    /**
     * The damaged area cannot be bigger than the farm.
     *
     * We catch this here instead of letting the technician find out on
     * site. A misplaced decimal point (2.5 typed as 25) is the easiest
     * mistake to make on this form, and it would throw off every total
     * the office reports afterwards.
     *
     * We skip the check when the farm size was never recorded, because
     * then we have nothing to compare against.
     */
    private function assertDamagedAreaFits(array $crops): void
    {
        $farmSize = (float) ($this->farmer()->farm_size_hectares ?? 0);

        if ($farmSize <= 0) {
            return;
        }

        $claimed = collect($crops)->sum(fn ($crop) => (float) $crop['damaged_area_hectares']);

        // The small 0.001 allowance is for rounding, so 2.50 does not get
        // rejected against a farm of exactly 2.5 hectares.
        if ($claimed > $farmSize + 0.001) {
            throw ValidationException::withMessages([
                'crops' => 'The damaged area adds up to ' . number_format($claimed, 2)
                    . ' hectares, which is larger than your registered farm of '
                    . number_format($farmSize, 2) . ' hectares. Please check the figures.',
            ]);
        }
    }

    /**
     * Save the farmer's damage photos.
     *
     * Section 36: these are the FARMER's photos. The technician's
     * inspection photos are stored separately in validation_photos.
     * We keep them apart because they are evidence from two different
     * people and mixing them would ruin the whole point.
     *
     * Each report gets its own folder so the storage does not become one
     * giant directory with thousands of files in it.
     */
    private function storePhotos(Request $request, DamageReport $report): void
    {
        foreach ($request->file('photos', []) as $photo) {
            // store() gives the file a random unique name, which also
            // stops two farmers uploading "IMG_1234.jpg" from clashing.
            $path = $photo->store(self::PHOTO_DIR . '/' . $report->id, self::PHOTO_DISK);

            $report->photos()->create([
                'file_path'   => $path,
                'file_name'   => $photo->getClientOriginalName(),   // keep the original name for display
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);
        }
    }

    /**
     * Get the logged in farmer.
     *
     * The static variable is a small cache. This method gets called a few
     * times during one request and there is no reason to hit the database
     * again each time.
     */
    private function farmer(): Farmer
    {
        static $farmer;

        return $farmer ??= Farmer::with('association', 'barangay')
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }
}
