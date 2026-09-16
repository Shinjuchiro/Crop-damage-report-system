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
        'typhoon'          => 'Typhoon / Bagyo',
        'flood'            => 'Flood / Baha',
        'drought'          => 'Drought / Tagtuyot',
        'strong_winds'     => 'Strong Winds / Malakas na Hangin',
        'pest_infestation' => 'Pest Infestation / Peste o Insekto',
        'plant_disease'    => 'Plant Disease / Sakit ng Halaman',
        'heat_stress'      => 'Extreme Heat / Sobrang Init',
        'other'            => 'Other / Iba pa',
    ];

    /**
     * The causes that the MAO declares as an event. For these, and only these,
     * the farmer is asked to pick the declared event as well, and even then
     * only when the office has actually declared one of that type.
     */
    public const WEATHER_CAUSES = ['typhoon', 'flood', 'drought', 'strong_winds'];

    protected $table = 'damage_reports';

    protected $fillable = [
        'farmer_id', 'damage_cause', 'damage_cause_other',
        'assigned_technician_id', 'assigned_at', 'farm_location_description',
        'reported_barangay_id',
        'description', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            // Set once, when the MAO hands the report to a technician.
            // The technician dashboard counts today's work off this.
            'assigned_at' => 'datetime',
            'archived_at' => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    /**
     * The reference number people say out loud: DR-0007.
     *
     * Every page used to build this by hand with str_pad, which meant one
     * screen could easily end up formatting it differently from another.
     * Now there is a single place that decides it, so if the office ever
     * asks for a different prefix or a year inside it, this is the only
     * line that has to change.
     */
    public function getReferenceAttribute(): string
    {
        return 'DR-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
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

        return self::CAUSES[$this->damage_cause] ?? 'Not recorded';
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
            ->withPivot(['linked_by', 'linked_by_role', 'created_at']);
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

    // Adds up the damage cost of every crop on this report.
    // Each crop's total_damage_cost was computed when the report was saved.
    public function getTotalDamageCostAttribute(): float
    {
        return (float) $this->crops()->sum('total_damage_cost');
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
