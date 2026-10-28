<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\CropPlantingRecordCrop;
use App\Models\Disaster;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
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
    /* Photos go to the PRIVATE disk: storage/app/private, which the web
       server cannot reach at all. They are read through
       App\Http\Controllers\AttachmentController, which checks on every
       request that the person asking is this farmer, an officer of their
       association, the technician handling the case, or the office
       (revision section 3.4).

       They used to go to 'public' and be served through the storage
       symlink, so every damage photo in the system was readable by anybody
       with the link and no login. Nothing here needs
       `php artisan storage:link` any more. */
    private const PHOTO_DISK = 'local';
    private const PHOTO_DIR  = 'damage-reports';
    private const MAX_PHOTOS = 10;

    /*
     | How many damaged crops one report may carry.
     |
     | The database is not limited: damage_report_crops is a child table and
     | section 79 of the proposal is explicit that it must stay that way. This
     | is a limit on one submission, not on the design, and it exists because
     | the form had no ceiling at all - "Add another crop" could be pressed
     | until the page gave out, and a report with forty crops on it is not a
     | report a technician can verify in the field.
     |
     | Five covers the real case comfortably. A farm in Tanza growing more
     | than five different crops and losing all of them to one event is rare
     | enough that it is better handled as a second report than by letting
     | every form grow without end.
     |
     | Enforced here as well as in the form. The form's own limit is a
     | courtesy to the farmer; this one is the rule, because anything the
     | browser enforces can be edited in the browser.
     */
    public const MAX_CROPS = 5;

    /** Disaster events one report can be linked to. The first is the main event. */
    public const MAX_DISASTERS = 3;

    /**
     * "My Reports" page.
     */
    public function index(Request $request)
    {
        $farmer = $this->farmer();

        $reports = $farmer->damageReports()
            // Load everything the list needs in one go, otherwise
            // each card would fire its own queries (N+1 problem).
            ->with('crops.crop', 'disasters', 'assignedTechnician', 'validation')
            ->withCount('photos')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        /*
         | The report shown in the right-hand panel when a row is picked
         | with ?selected=<id>.
         |
         | The important part is that the lookup starts from THIS farmer's
         | own reports, not from DamageReport::find(). Typing another
         | farmer's id into the query string then simply finds nothing,
         | rather than finding somebody else's report and rendering it.
         */
        $selected = $request->filled('selected')
            ? $farmer->damageReports()
                ->with('crops.crop', 'disasters', 'photos', 'assignedTechnician',
                       'validation.photos', 'validation.technician', 'reportedBarangay')
                ->find($request->query('selected'))
            : null;

        return view('farmer.reports.index', [
            'farmer'   => $farmer,
            'reports'  => $reports,
            'selected' => $selected,

            // "Back to list" on a phone: this page with the selection
            // dropped and the page number kept.
            'backUrl'  => $request->fullUrlWithoutQuery('selected'),
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
            'crops'     => Crop::active()->inDisplayOrder()->get(),
            'disasters' => Disaster::offeredToFarmers()->orderByDesc('date_start')->orderBy('name')->get(),
            // Only the barangays the farmer's own association covers.
            'barangays' => $this->allowedBarangays($farmer),

            // The cause list lives on the model, not in the database, because
            // it is a fixed vocabulary and not something the office edits.
            'causes' => DamageReport::formCauses(),

            // Which causes currently have a declared event to link to. Used by
            // the form to decide whether to ask for one at all.
            'causesWithEvents' => Disaster::offeredToFarmers()->distinct()->pluck('type')->all(),

            // So the form stops at the same number the validation does. One
            // constant, two places that need it.
            'maxCrops' => self::MAX_CROPS,
            'maxEvents' => self::MAX_DISASTERS,

            // The farmer's own Crop Planted entries, so a crop (and the date
            // they actually planted it) can be picked instead of remembered.
            'plantingOptions' => $this->plantingOptions($farmer),
        ]);
    }

    /**
     * The farmer's Crop Planted lines, shaped for the form's picker. Archived
     * and deleted records are left out, and nobody else's are ever included.
     */
    private function plantingOptions(Farmer $farmer): array
    {
        return CropPlantingRecordCrop::query()
            ->with('crop', 'plantingRecord')
            ->whereHas('plantingRecord', fn ($q) => $q->where('farmer_id', $farmer->id)->active())
            ->orderByDesc('date_planted')
            ->get()
            ->map(fn ($row) => [
                'id'         => $row->id,
                'crop_id'    => $row->crop_id,
                'specify'    => $row->crop_specify,
                'crop_name'  => $row->crop_specify ?: ($row->crop?->name ?? 'Crop'),
                'date'       => $row->date_planted?->toDateString(),
                'date_label' => $row->date_planted?->format('M d, Y'),
                'area'       => $row->area_hectares,
                // When it was RECORDED in the system, kept apart from the date
                // the crop was actually planted (date / date_label above).
                'recorded'   => $row->plantingRecord?->date_submitted?->format('M d, Y'),
                // The system holds one registered farm per farmer, so that is
                // the farm detail there is to show.
                'farm'       => collect([$farmer->barangay?->name, $farmer->address])->filter()->implode(', '),
                'reference'  => 'REC-' . str_pad((string) $row->crop_planting_record_id, 4, '0', STR_PAD_LEFT),
            ])
            ->values()
            ->all();
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
                // The typhoon's name, only when the cause is a typhoon.
                'typhoon_name'       => $data['damage_cause'] === 'typhoon'
                                            ? trim($data['typhoon_name'] ?? '') : null,

                /* Two dates, two columns. date_damaged is when the crop was
                   actually damaged; submitted_at is when this form was sent.
                   Neither is ever derived from the other. */
                'date_damaged' => $data['date_damaged'],
                'submitted_at' => now(),

                'farm_location_description' => $data['farm_location_description'],

                // If the farmer did not pick a barangay we fall back to the
                // one on their profile.
                'reported_barangay_id' => $data['reported_barangay_id'] ?? $farmer->barangay_id,

                /* The farmer-reported location (sections 37 and 49).
                 *
                 * Optional, so all three stay null/'none' when it was not
                 * given, and the written directions above carry the location
                 * on their own. The technician's verified pin is a separate
                 * record on the validations row and never writes back here,
                 * which is what lets the map show both.
                 *
                 * location_source is derived rather than taken as submitted:
                 * with no coordinates it is always 'none', and 'gps' is only
                 * honoured when the browser actually claimed a fix. That
                 * claim is still the browser's word, since nothing on the
                 * server can tell a real reading from typed numbers, but at
                 * least the flag cannot contradict the data next to it. */
                'reported_latitude'  => $data['reported_latitude'] ?? null,
                'reported_longitude' => $data['reported_longitude'] ?? null,
                'location_source'    => ! empty($data['reported_latitude'])
                    ? (($data['location_source'] ?? null) === 'gps' ? 'gps' : 'manual')
                    : 'none',

                'description' => $data['description'] ?? null,

                // Section 38: a new report always starts as Pending.
                // Only MAO can move it to Assigned.
                'status' => 'pending',
            ]);

            // 2. One row per damaged crop.
            foreach ($data['crops'] as $crop) {
                $report->crops()->create([
                    'crop_id'                  => $crop['crop_id'],
                    // Set when the line was picked from a Crop Planted report;
                    // the date below is then that record's own date.
                    'crop_planting_record_crop_id' => $crop['planting_record_crop_id'] ?? null,
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
            // is_primary marks the MAIN event, the one the dates are checked
            // against: the first one the farmer chose. Extra events may be
            // added but are never used for the date check.
            $linked  = collect($data['disasters'] ?? []);
            $primary = $linked->isNotEmpty() ? (int) $linked->first() : null;

            $report->disasters()->sync(
                $linked->mapWithKeys(fn ($disasterId) => [
                    $disasterId => [
                        'linked_by'      => Auth::id(),
                        'linked_by_role' => 'farmer',
                        'created_at'     => now(),
                        'is_primary'     => $primary !== null && (int) $disasterId === $primary,
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

        $this->notifyMaoOfNewReport($report);
        $this->notifyAssociationOfNewReport($report);

        return redirect()
            ->route('farmer.reports.show', $report)
            ->with('status', 'Damage report submitted successfully. Naisumite na po ang inyong ulat.');
    }

    /**
     * Proposal section 66: MAO should be told about a "new/unprocessed
     * damage report" - this was previously missing entirely, the same gap
     * that was closed for new farmer registrations (the registration notice
     * now goes to the farmers' association instead; see
     * Auth\RegisterController and revision step 6).
     * Runs after the transaction above has already committed,
     * and never throws - a notification failure must never make a farmer's
     * report appear to fail (see the Sept 2026 notification-system rule).
     *
     * 'normal' priority: a new report is routine, not urgent (section 68
     * reserves SMS for urgent/critical matters). link_type/link_id let MAO's
     * bell open this exact report (NotificationBroadcast::linkUrl()).
     */
    private function notifyMaoOfNewReport(DamageReport $report): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'New Damage Report',
                'message'     => $this->farmer()->full_name . ' submitted damage report '
                    . $report->reference . ' and is waiting to be assigned to a technician.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'all_mao',
                'link_type'   => 'damage_report',
                'link_id'     => $report->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify MAO of new damage report ' . $report->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Proposal section 66: the association should be told about "member
     * damage reports" - purely informational, no action expected of them
     * (proposal Role 3, they only monitor). Skipped entirely for a farmer who
     * registered without an association. 'normal' priority: routine, not
     * urgent (section 68).
     */
    private function notifyAssociationOfNewReport(DamageReport $report): void
    {
        $farmer = $this->farmer();

        if (! $farmer->association_id) {
            return;
        }

        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Member Damage Report Submitted',
                'message'     => $farmer->full_name . ' submitted damage report ' . $report->reference . '.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'specific_association',
                'target_id'   => $farmer->association_id,
                'link_type'   => 'damage_report',
                'link_id'     => $report->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify association ' . $farmer->association_id
                . ' of new report ' . $report->id . ': ' . $e->getMessage());
        }
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
        // A line picked from the farmer's own Crop Planted reports takes its
        // crop and planting date from that record, never from the browser.
        $crops = $this->applyPlantingRecords($request->input('crops', []));

        // Same idea as the planting form. Crop 2 and Disaster 2 are
        // optional, so empty blocks are removed before validation runs.
        $request->merge([
            'crops' => collect($crops)
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
            'damage_cause'       => ['required', Rule::in(array_keys(DamageReport::formCauses()))],
            // A typhoon report has to say which typhoon it was.
            'typhoon_name'       => [
                Rule::requiredIf(fn () => $request->input('damage_cause') === 'typhoon'),
                'nullable', 'string', 'max:120',
            ],
            'damage_cause_other' => [
                Rule::requiredIf(fn () => $request->input('damage_cause') === 'other'),
                'nullable', 'string', 'max:120',
            ],

            /* The ACTUAL date the crop was damaged. Not capped at today: only the
               official disaster date limits it (checked in assertDatesFollowEvent,
               since that date comes from the event the farmer picked). */
            'date_damaged'      => ['required', 'date', 'after_or_equal:2000-01-01'],

            'farm_location_description' => ['required', 'string', 'max:500'],
            // Only a barangay the farmer's association covers (the form offers
            // no others; this is what stops one being posted by hand).
            'reported_barangay_id'      => ['nullable', Rule::in($this->allowedBarangays($this->farmer())->pluck('id')->all())],
            'description'               => ['nullable', 'string', 'max:2000'],

            /* Sections 37 and 49: the farmer's own coordinates.
             *
             * Optional, because a farmer on a cheap phone in a flooded field
             * may have no signal, and blocking the report would be far worse
             * than storing one without a pin. farm_location_description above
             * stays required and carries the location when these are empty.
             *
             * Bounded to the Philippines rather than the full -90..90 and
             * -180..180, which catches the commonest coordinate mistake there
             * is: entering them the wrong way round. Tanza's longitude of
             * 120.85 in the latitude box passes a global check and silently
             * puts the pin in Kazakhstan; here it is rejected with something
             * the farmer can act on. required_with on each side stops a
             * latitude being saved with no longitude to go with it. */
            'reported_latitude' => [
                'nullable', 'required_with:reported_longitude', 'numeric', 'between:4.5,21.5',
            ],
            'reported_longitude' => [
                'nullable', 'required_with:reported_latitude', 'numeric', 'between:116,127',
            ],
            'location_source' => ['nullable', Rule::in(['gps', 'manual', 'none'])],

            // Section 30: the fields the prompt says a damage report must have.
            'crops'                            => ['required', 'array', 'min:1', 'max:' . self::MAX_CROPS],
            'crops.*.crop_id'                  => ['required', Rule::exists('crops', 'id')->whereNull('archived_at')],
            'crops.*.crop_specify'             => ['nullable', 'string', 'max:100'],
            'crops.*.planting_record_crop_id'  => ['nullable', 'integer'],
            'crops.*.damaged_area_hectares'    => ['required', 'numeric', 'min:0.01', 'max:9999'],
            // No "before today" cap any more: the limit is the disaster date.
            'crops.*.date_planted'             => ['required', 'date', 'after_or_equal:2000-01-01'],
            'crops.*.estimated_damage_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'crops.*.production_cost'          => ['required', 'numeric', 'min:0', 'max:99999999'],
            'crops.*.farmgate_price_per_kg'    => ['required', 'numeric', 'min:0', 'max:99999'],

            // A weather cause now REQUIRES a recorded disaster (Oct 2026): see
            // assertDisasterRecorded(). Other causes have no event to pick.
            'disasters'   => ['nullable', 'array', 'max:' . self::MAX_DISASTERS],
            'disasters.*' => [
                Rule::exists('disasters', 'id')->whereNull('archived_at'),
                // Only events a farmer is actually offered: not one whose
                // advisory is still scheduled or never went out.
                function ($attribute, $value, $fail) {
                    if (! Disaster::offeredToFarmers()->whereKey($value)->exists()) {
                        $fail('That disaster event is not open for reports yet. Please choose another.');
                    }
                },
            ],

            // Section 35: validate file type and size before storing anything.
            'photos'   => ['nullable', 'array', 'max:' . self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],   // 5120 KB = 5 MB
        ], [
            'farm_location_description.required'        => 'Please describe where the damaged farm is.',
            'crops.required'                            => 'Please add at least one damaged crop.',
            'disasters.max'                           => 'One report can be linked to up to ' . self::MAX_DISASTERS . ' events.',
            'crops.max'                                 => 'One report can cover up to ' . self::MAX_CROPS
                . ' crops. Please submit the rest as a separate report.',
            'date_damaged.required'                     => 'Please enter the date the crop was damaged.',
            'crops.*.date_planted.required'             => 'Please enter the date you planted this crop, or choose it from your Crop Planted reports.',
            'crops.*.crop_id.required'                  => 'Please choose the damaged crop.',
            'crops.*.damaged_area_hectares.required'    => 'Please give the damaged area in hectares.',
            'crops.*.estimated_damage_percent.required' => 'Please give your estimate of the damage.',
            'crops.*.production_cost.required'          => 'Please give the production cost, including labour.',
            'crops.*.farmgate_price_per_kg.required'    => 'Please give the farmgate price per kilogram.',
            'damage_cause.required'                     => 'Please choose what caused the damage.',
            'damage_cause_other.required'               => 'Please say briefly what caused the damage.',
            'typhoon_name.required'                     => 'Please give the name of the typhoon, for example Egay or Odette.',
            'photos.max'                                => 'Please upload no more than ' . self::MAX_PHOTOS . ' photos.',
            'photos.*.image'                            => 'Each file must be a photo.',
            'photos.*.max'                              => 'Each photo must be 5 MB or smaller.',

            'reported_latitude.required_with'  => 'Please give both the latitude and the longitude, or leave both blank.',
            'reported_longitude.required_with' => 'Please give both the latitude and the longitude, or leave both blank.',
            'reported_latitude.between'        => 'That latitude is outside the Philippines. Check that the latitude and longitude are not swapped.',
            'reported_longitude.between'       => 'That longitude is outside the Philippines. Check that the latitude and longitude are not swapped.',
        ]);

        // Three checks the normal rules cannot do on their own.
        $this->assertHvccSpecified($validated['crops']);
        $this->assertDamagedAreaFits($validated['crops']);
        $this->assertDisasterRecorded($validated);
        $this->assertDisasterMatchesCause($validated);
        $this->assertDatesFollowEvent($validated);

        return $validated;
    }

    /**
     * Fill in crop, specify and planting date for every line that was picked
     * from a Crop Planted report.
     *
     * A line may only point at a record that belongs to THIS farmer and is not
     * archived or deleted. Anything else is refused. The record's date is
     * copied as it stands and the record itself is never altered. A copied
     * date is not judged against the disaster date; only dates the farmer
     * types are.
     */
    private function applyPlantingRecords(array $crops): array
    {
        $ids = collect($crops)->pluck('planting_record_crop_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return $crops;
        }

        $farmerId = $this->farmer()->id;

        $records = CropPlantingRecordCrop::query()
            ->whereIn('id', $ids)
            ->whereHas('plantingRecord', fn ($q) => $q->where('farmer_id', $farmerId)->active())
            ->get()
            ->keyBy('id');

        foreach ($crops as $index => $crop) {
            $id = $crop['planting_record_crop_id'] ?? null;

            if (blank($id)) {
                $crops[$index]['planting_record_crop_id'] = null;
                continue;
            }

            $record = $records->get((int) $id);

            if (! $record) {
                throw ValidationException::withMessages([
                    "crops.{$index}.planting_record_crop_id" =>
                        'That Crop Planted report could not be found. Please choose it again or enter the planting date yourself.',
                ]);
            }

            $crops[$index]['planting_record_crop_id'] = $record->id;
            $crops[$index]['crop_id']       = $record->crop_id;
            $crops[$index]['crop_specify']  = $record->crop_specify;
            $crops[$index]['date_planted']  = $record->date_planted?->toDateString();
        }

        return $crops;
    }

    /**
     * THE RECORDED DISASTER IS THE SOURCE OF TRUTH (Oct 2026)
     *
     * When the office has recorded and published a disaster of the farmer's
     * weather cause, the farmer must choose one, and the dates are checked
     * against it. If the office has recorded none yet, the farmer can still file
     * (the planting date just has to be before the damage date), and nothing is
     * flagged. A cause with no event kind (Other) has nothing to match.
     *
     * Reports saved before this rule are never touched; this only stops a new
     * one.
     */
    private function assertDisasterRecorded(array $validated): void
    {
        $cause = $validated['damage_cause'];

        if (! in_array($cause, DamageReport::WEATHER_CAUSES, true)) {
            return;
        }

        $linked = collect($validated['disasters'] ?? [])->filter()->values();

        if ($linked->isEmpty()) {
            $available = Disaster::offeredToFarmers()->where('type', $cause)->whereNotNull('date_start')->exists();

            // The office has recorded no disaster of this kind: there is nothing to
            // choose and no date to check against, and the farmer can still file.
            if (! $available) {
                return;
            }

            throw ValidationException::withMessages([
                'disasters' => 'Please select the relevant recorded disaster to validate the crop damage date.',
            ]);
        }

        // Every chosen event needs an official date to check against.
        if (Disaster::whereIn('id', $linked)->whereNull('date_start')->exists()) {
            throw ValidationException::withMessages([
                'disasters' => 'That disaster has no official date recorded yet, so this report cannot be submitted against it.',
            ]);
        }
    }

    /**
     * The two date rules, against the recorded disaster date.
     *
     *   planting date  must be BEFORE the disaster date (strictly earlier)
     *   damage date    must be AFTER the disaster date (strictly later)
     *
     * The disaster date itself fails both. The date is always the one recorded
     * on the disaster the farmer chose: never today, and never the day an
     * advisory was written or sent, so a report filed months later is held to
     * the same dates. The date the form is submitted is kept separately.
     *
     * The planting rule applies to a planting date the farmer enters in a NEW
     * report. A line copied from an existing Crop Planted record uses the date
     * already recorded there: it is not asked for again, not changed, and the
     * report is not refused because of it.
     *
     * The MAIN event is the first one linked. Extra events are allowed but are
     * not used for the check.
     *
     * With no event (cause Other) there is no disaster date, so the one thing
     * left to check is that a planting date is not after the damage date.
     */
    private function assertDatesFollowEvent(array $validated): void
    {
        $damaged  = Carbon::parse($validated['date_damaged'])->startOfDay();
        $mainId   = collect($validated['disasters'] ?? [])->filter()->first();
        $mainDate = $mainId ? Disaster::whereKey($mainId)->value('date_start') : null;
        $official = $mainDate ? Carbon::parse($mainDate)->startOfDay() : null;

        $errors = [];

        if ($official && $damaged->lte($official)) {
            $errors['date_damaged'] = 'The actual crop damage date must be after the disaster date. '
                . 'The disaster date is ' . $official->format('F j, Y') . '.';
        }

        foreach ($validated['crops'] as $index => $crop) {
            // Date taken from an existing planting record: shown as recorded,
            // never judged here.
            if (! empty($crop['planting_record_crop_id'])) {
                continue;
            }

            $planted = Carbon::parse($crop['date_planted'])->startOfDay();

            if ($official && $planted->gte($official)) {
                $errors["crops.{$index}.date_planted"] = 'The actual planting date must be before the disaster date. '
                    . 'The disaster date is ' . $official->format('F j, Y') . '.';
            } elseif (! $official && $planted->gt($damaged)) {
                $errors["crops.{$index}.date_planted"] = 'The planting date cannot be after the date the crop was damaged.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A linked disaster has to be of the kind the farmer said caused the damage.
     *
     * The form only offers events that match the chosen cause, so this only
     * ever refuses a form that was edited or left half-changed (cause switched
     * after an event was picked). Without it, a typhoon's date could end up
     * judging a drought report.
     */
    private function assertDisasterMatchesCause(array $validated): void
    {
        if (blank($validated['disasters'] ?? [])) {
            return;
        }

        $mismatch = Disaster::whereIn('id', $validated['disasters'])
            ->where('type', '!=', $validated['damage_cause'])
            ->exists();

        if ($mismatch) {
            throw ValidationException::withMessages([
                'disasters' => 'The disaster event you chose does not match the cause of damage. Please choose an event of the same kind.',
            ]);
        }
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
    /**
     * The barangays this farmer may name as the farm's location: the ones the
     * association they belong to covers, plus the barangay on their own
     * profile so the list is never empty and their default is always in it.
     */
    private function allowedBarangays(Farmer $farmer)
    {
        $covered = $farmer->association
            ? $farmer->association->coverage()->orderBy('barangays.name')->get()
            : collect();

        if ($farmer->barangay && ! $covered->contains('id', $farmer->barangay->id)) {
            $covered = $covered->push($farmer->barangay)->sortBy('name')->values();
        }

        return $covered;
    }

    private function farmer(): Farmer
    {
        static $farmer;

        return $farmer ??= Farmer::with('association', 'barangay')
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }
}
