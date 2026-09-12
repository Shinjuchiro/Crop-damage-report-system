<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Farmer extends Model
{
    use SoftDeletable;

    /**
     * Proposal section 22: 3 months with no activity means Inactive.
     *
     * Kept as a constant so the number only exists in one place. If the
     * office ever changes the rule we edit it here and nothing else.
     *
     * What counts as an activity is important. Section 21 says logging in,
     * opening pages and browsing do NOT count, and it also says we must not
     * mark a farmer inactive just because they have no damage report,
     * because a farmer with no damage is doing well. So right now only a
     * crop planting record counts.
     */
    public const INACTIVITY_MONTHS = 3;

    protected $table = 'farmers';

    protected $fillable = [
        'user_id', 'association_id', 'barangay_id',
        'first_name', 'middle_name', 'last_name', 'date_of_birth', 'sex',
        'ownership_type', 'landowner_name', 'landowner_contact', 'landowner_location',
        'barangay_certificate_path', 'address', 'farm_size_hectares',
        'activity_status', 'last_activity_date',
    ];

    // Turns these columns into Carbon date objects automatically, so we can
    // call ->format() and ->diffInMonths() on them.
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'last_activity_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function association()
    {
        return $this->belongsTo(Association::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function mainCrops()
    {
        return $this->hasMany(FarmerMainCrop::class);
    }

    public function plantingRecords()
    {
        return $this->hasMany(CropPlantingRecord::class);
    }

    public function damageReports()
    {
        return $this->hasMany(DamageReport::class);
    }

    public function assistanceDistributions()
    {
        return $this->hasMany(AssistanceDistribution::class);
    }

    // Lets us write $farmer->full_name anywhere instead of gluing the
    // three name columns together in every view.
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    /* ==================================================================
     | Active / Inactive status (proposal sections 21 and 22)
     ================================================================== */

    /**
     * How many whole months since the last activity.
     *
     * We work this out from last_activity_date every time instead of saving
     * a "months_inactive" number in the database. If we saved it, it would
     * be wrong the next day unless something updated it, and section 22
     * says the status must be based on real dates and not on values set
     * by hand.
     */
    public function getMonthsInactiveAttribute(): int
    {
        if (! $this->last_activity_date) {
            return 0;
        }

        return (int) $this->last_activity_date->diffInMonths(Carbon::now());
    }

    /**
     * Call this after the farmer does something that counts.
     *
     * Section 22: the timer restarts and an Inactive farmer becomes
     * Active again. At the moment only PlantingController calls this.
     */
    public function recordQualifyingActivity(?Carbon $on = null): void
    {
        $this->forceFill([
            'last_activity_date' => ($on ?? Carbon::now())->toDateString(),
            'activity_status'    => 'active',
        ])->save();
    }

    /**
     * Flip an Active farmer to Inactive if they went past 3 months.
     *
     * We call this when a page loads rather than running a scheduled job,
     * so there is nothing extra to set up on the server. The downside is
     * the status only updates when somebody looks at it, which is fine
     * here because the status is only ever read on a page anyway.
     *
     * Returns true if it actually changed something.
     */
    public function refreshActivityStatus(): bool
    {
        if ($this->activity_status !== 'active') {
            return false;
        }

        if ($this->months_inactive < self::INACTIVITY_MONTHS) {
            return false;
        }

        $this->forceFill(['activity_status' => 'inactive'])->save();

        return true;
    }
}
