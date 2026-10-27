<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;

class DamageReport extends Model
{
    /**
     * Sept 2026: joined the Archive page as its own tab (migration
     * 2024_01_16_000001). Deliberately independent of the STATUSES workflow
     * above - the developer asked that Archive be available "any report, any
     * status", so a report can be taken off the active monitoring list at
     * any point in its pipeline without touching its status/decision at all.
     */
    use Archivable, SoftDeletable;

    /**
     * The statuses from proposal section 38, in the order they happen.
     *
     *   pending             farmer submitted it, nobody assigned yet
     *   assigned            MAO gave it to a technician
     *   under_verification  technician pressed Start Inspection
     *   verified            technician submitted the inspection
     *   flagged             something does not add up, needs another look
     *   approved / rejected the MAO decision at the end
     *
     * Kept here so the farmer pages, the MAO pages and the map all use the
     * same list and nobody invents a new status by accident.
     */
    public const STATUSES = [
        'pending'            => 'Pending',
        'assigned'           => 'Assigned',
        'under_verification' => 'Under Verification',
        'verified'           => 'Verified',
        'flagged'            => 'Flagged',
        'approved'           => 'Approved',
        'rejected'           => 'Rejected',
    ];

    /**
     * What actually ruined the crop.
     *
     * This is asked on every report and it is always answerable, which is the
     * point. A declared disaster event covers a typhoon or a flood, but the
     * office does not declare an event for army worm in the corn or for a week
     * of extreme heat, and those ruin crops too. Tying reports only to
     * declared events left a farmer with insect damage unable to file at all.
     *
     * The first four line up with the type column on the disasters table, so
     * a report can also be linked to the declared event when one exists.
     */
    public const CAUSES = [
        'typhoon'             => 'Typhoon / Bagyo',
        'flood'               => 'Flood / Baha',
        'drought'             => 'Drought / Tagtuyot',
        'storm_surge'         => 'Storm Surge / Daluyong ng Bagyo',
        'soil_erosion'        => 'Soil Erosion / Pagkaagnas ng Lupa',
        'landslide'           => 'Landslide / Pagguho ng Lupa',
        'saltwater_intrusion' => 'Saltwater Intrusion / Pagpasok ng Tubig-Alat',
        'sea_level_rise'      => 'Sea Level Rise / Pagtaas ng Dagat',
        'heat_stress'         => 'Extreme Heat / Sobrang Init',
        'pest_disease_outbreak' => 'Pest and Disease Outbreak / Peste at Sakit',
        'strong_winds'        => 'Strong Winds / Malakas na Hangin',
        'other'               => 'Other / Iba pa',

        // No longer offered on the form (Pest and Disease Outbreak covers
        // both), kept so reports already filed under them still read right.
        'pest_infestation'    => 'Pest Infestation / Peste o Insekto',
        'plant_disease'       => 'Plant Disease / Sakit ng Halaman',
    ];

    /** Causes that stay in CAUSES for old reports but are not offered any more. */
    public const RETIRED_CAUSES = ['pest_infestation', 'plant_disease'];

    /** The causes a farmer can choose on the report form, in display order. */
    public static function formCauses(): array
    {
        return array_diff_key(self::CAUSES, array_flip(self::RETIRED_CAUSES));
    }

    /**
     * The causes that the MAO declares as an event (every cause except "Other").
     * For these, and only these, the farmer is asked to pick the declared event
     * as well, and even then only when the office has actually declared one of
     * that type.
     */
    public const WEATHER_CAUSES = [
        'typhoon', 'flood', 'drought', 'strong_winds',
        // Added with the Disaster Advisory dates (Oct 2026): an advisory can now
        // declare any of the hazards, and every one of them carries an official
        // date that the planting and damage dates are checked against.
        'storm_surge', 'soil_erosion', 'landslide', 'saltwater_intrusion',
        'sea_level_rise', 'heat_stress', 'pest_disease_outbreak',
    ];

    protected $table = 'damage_reports';

    protected $fillable = [
        'farmer_id', 'farmer_report_no', 'damage_cause', 'damage_cause_other', 'typhoon_name',
        // The ACTUAL date the crop was damaged, and when the report was filed.
        // Two different things, kept in two columns.
        'date_damaged', 'submitted_at',
        'assigned_technician_id', 'assigned_at', 'farm_location_description',
        'reported_barangay_id',
        // The farmer's own coordinates, set once when the report is filed.
        // The technician's verified pin lives on the validations row and
        // never touches these, so both locations survive (section 49).
        'reported_latitude', 'reported_longitude', 'location_source',
        'description', 'status', 'approved_by', 'approved_at',
        // The office's review of the technician's assessment (Oct 2026):
        // who decided, when, and the remarks (required on a rejection).
        'reviewed_by', 'reviewed_at', 'review_remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_damaged' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
            // Set once, when the MAO hands the report to a technician.
            // The technician dashboard counts today's work off this.
            'assigned_at' => 'datetime',
            'archived_at' => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    /**
     * Each farmer's reports are numbered from DR-0001 on their own. A farmer's
     * ID (FARM-005) is the only thing that is unique by itself, so the number
     * alone is only meaningful beside it - see getFullReferenceAttribute().
     *
     * The number is stored (farmer_report_no), so archiving or deleting a
     * report never renumbers the ones after it.
     */
    protected static function booted(): void
    {
        static::creating(function (DamageReport $report) {
            if (! $report->farmer_report_no && $report->farmer_id) {
                $report->farmer_report_no = 1 + (int) static::query()
                    ->where('farmer_id', $report->farmer_id)
                    ->max('farmer_report_no');
            }
        });
    }

    public function getReferenceAttribute(): string
    {
        return 'DR-' . str_pad((string) ($this->farmer_report_no ?: $this->id), 4, '0', STR_PAD_LEFT);
    }

    /** "FARM-005 / DR-0001": the form that identifies one report on its own. */
    public function getFullReferenceAttribute(): string
    {
        return 'FARM-' . str_pad((string) $this->farmer_id, 3, '0', STR_PAD_LEFT) . ' / ' . $this->reference;
    }

    /**
     * The cause as a person reads it. When the farmer chose "Other" we show
     * what they typed instead of the word "Other", which tells nobody anything.
     */
    public function getDamageCauseLabelAttribute(): string
    {
        if ($this->damage_cause === 'other' && filled($this->damage_cause_other)) {
            return $this->damage_cause_other;
        }

        $label = self::CAUSES[$this->damage_cause] ?? 'Not recorded';

        // "Typhoon / Bagyo (Egay)": the name is part of what the cause is.
        if ($this->damage_cause === 'typhoon' && filled($this->typhoon_name)) {
            $label .= ' (' . $this->typhoon_name . ')';
        }

        return $label;
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function assignedTechnician()
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Whoever approved OR rejected the technician's assessment. */
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // The barangay the FARMER picked when reporting. Not necessarily the
    // same as the one on their profile, since a farmer can work more than
    // one field.
    public function reportedBarangay()
    {
        return $this->belongsTo(Barangay::class, 'reported_barangay_id');
    }

    public function crops()
    {
        return $this->hasMany(DamageReportCrop::class);
    }

    public function disasters()
    {
        // linked_by / linked_by_role say who attached this particular
        // disaster to this particular report, and as which role - the
        // farmer at filing, or a technician correcting/adding one during
        // their own inspection (see migration 2024_01_09_000001 and
        // Technician\InspectionController::syncDisasterLinks()).
        return $this->belongsToMany(Disaster::class, 'damage_report_disasters')
            ->withPivot(['linked_by', 'linked_by_role', 'created_at', 'is_primary']);
    }

    public function photos()
    {
        return $this->hasMany(DamageReportPhoto::class);
    }

    public function validation()
    {
        return $this->hasOne(Validation::class);
    }

    public function assistanceDistributions()
    {
        return $this->hasMany(AssistanceDistribution::class);
    }

    /**
     * Rows recording that THIS specific report already made a farmer a
     * beneficiary of some MAO allocation. Used by
     * AssistanceAllocationController::qualifiedReports() to stop counting a
     * report as "still needing an allocation" once it has actually been
     * used to grant one - see section 31.
     */
    public function allocationBeneficiaries()
    {
        return $this->hasMany(AssistanceAllocationBeneficiary::class);
    }

    /**
     * The office's assistance decision on this report, if it has made one.
     *
     * hasOne, enforced by a unique index on the table: one report is one
     * case and carries one current answer. Null means Pending Review, which
     * is the whole reason the row exists - without it the system cannot tell
     * "we decided no" from "nobody has looked yet".
     *
     * Read this through App\Support\AssistanceCase rather than on its own:
     * a decision is one of three things that together make up the status an
     * officer sees.
     */
    public function assistanceDecision()
    {
        return $this->hasOne(AssistanceDecision::class);
    }

    // Adds up the damage cost of every crop on this report.
    // Each crop's total_damage_cost was computed when the report was saved.
    public function getTotalDamageCostAttribute(): float
    {
        return (float) $this->crops()->sum('total_damage_cost');
    }

    /* ------------------------------------------------------------------
     | Assignment and planting match, kept apart from the status
     |
     | The monitoring pages show three separate facts about a report and
     | never fold them into one status:
     |   assignment      Not Assigned / Assigned (has the MAO given it to a
     |                   technician), from assigned_technician_id
     |   planting match  Matching Record Found / No Matching Planting Record / No Planting Record Found, worked out by
     |                   App\Support\PlantingMatch
     |   validation      the technician's inspection and the final decision
     ------------------------------------------------------------------ */

    public function getIsAssignedAttribute(): bool
    {
        return $this->assigned_technician_id !== null;
    }

    public function getAssignmentLabelAttribute(): string
    {
        return $this->is_assigned ? 'Assigned' : 'Not Assigned';
    }

    public function scopeAssignment($query, ?string $which)
    {
        return match ($which) {
            'assigned'   => $query->whereNotNull('assigned_technician_id'),
            'unassigned' => $query->whereNull('assigned_technician_id'),
            default      => $query,
        };
    }

    /** Reports with at least one crop line that has no matching planting record, or no crop lines at all. */
    public function scopeNoPlantingMatch($query)
    {
        return $query->where(fn ($q) => $q
            ->whereHas('crops', fn ($c) => $c->whereRaw(\App\Support\PlantingMatch::unmatchedLineSql()))
            ->orWhereDoesntHave('crops'));
    }

    /** Reports whose dates disagree with their disaster event's official date (see DateCheck). */
    public function scopeDateFlagged($query)
    {
        return $query->whereRaw(\App\Support\DateCheck::reviewSql());
    }

    /**
     * Reports whose crop damage actually happened between two dates.
     *
     * Uses the actual damage date the farmer gave, not the day the report was
     * filed. Reports from before that date was collected have none, so for
     * them alone the day they were created stands in. Either bound may be null.
     */
    public function scopeOccurredBetween($query, $from, $to)
    {
        $when = 'COALESCE(damage_reports.date_damaged, DATE(damage_reports.created_at))';

        return $query
            ->when($from, fn ($q) => $q->whereRaw("{$when} >= ?", [\Illuminate\Support\Carbon::parse($from)->toDateString()]))
            ->when($to, fn ($q) => $q->whereRaw("{$when} <= ?", [\Illuminate\Support\Carbon::parse($to)->toDateString()]));
    }

    /** Flagged, and nobody in the office has reviewed the dates yet. */
    public function scopeDateReview($query)
    {
        return $query->dateFlagged()->whereDoesntHave('dateReviews');
    }

    /** Flagged, and the office has reviewed the dates. */
    public function scopeDateReviewed($query)
    {
        return $query->dateFlagged()->whereHas('dateReviews');
    }

    /** The office's reviews of this report's dates, oldest first. */
    public function dateReviews()
    {
        return $this->hasMany(DamageReportDateReview::class)->orderBy('reviewed_at')->orderBy('id');
    }

    /** Reports where every crop line has a matching planting record. */
    public function scopePlantingMatched($query)
    {
        return $query->whereHas('crops')
            ->whereDoesntHave('crops', fn ($c) => $c->whereRaw(\App\Support\PlantingMatch::unmatchedLineSql()));
    }

    /**
     * The three planting-match results as a list filter: 'matched',
     * 'mismatch' (the farmer has planting records but none matches) or
     * 'none' (the farmer has no planting records at all). Anything else is
     * no filter. Mirrors PlantingMatch::annotate().
     */
    public function scopePlantingResult($query, ?string $state)
    {
        return match ($state) {
            'matched'  => $query->plantingMatched(),
            'none'     => $query->whereRaw('NOT ' . \App\Support\PlantingMatch::farmerHasRecordsSql()),
            'mismatch' => $query->whereRaw(\App\Support\PlantingMatch::farmerHasRecordsSql())
                ->noPlantingMatch(),
            default    => $query,
        };
    }

    /**
     * Review statuses for a submitted assessment (Oct 2026). 'verified' is
     * what the technician's submission leaves behind and 'flagged' is the
     * retired second-look state; both mean the office has not decided yet.
     */
    public const PENDING_REVIEW = ['verified', 'flagged'];

    public function getReviewStateAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            default    => 'pending_review',
        };
    }

    /**
     * May the office still hand this report to a technician (or to a
     * different one)? Not once an inspection exists: the inspection row is
     * one per report, so a second technician could not record theirs, and
     * "who inspected this" must not change after the fact.
     */
    public function getCanBeAssignedAttribute(): bool
    {
        if (! in_array($this->status, ['pending', 'assigned'], true)) {
            return false;
        }

        return $this->relationLoaded('validation')
            ? $this->validation === null
            : $this->validation()->doesntExist();
    }

    /**
     * Can the farmer still change this report?
     *
     * Only while nobody has touched it. Once a technician is assigned, the
     * report is the evidence they are going to inspect, so it has to stop
     * changing underneath them.
     */
    public function getIsEditableByFarmerAttribute(): bool
    {
        return $this->status === 'pending' && $this->assigned_technician_id === null;
    }
}
