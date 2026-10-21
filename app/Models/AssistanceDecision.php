<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The office's assistance decision on one damage report: designated, or not.
 *
 * See the migration that creates assistance_decisions for why this is its
 * own table and not a column on damage_reports. In short: the report, the
 * inspection, the office's decision on the report and the assistance
 * designation are four distinct stages (section 10.5), and a report being
 * approved is not the same as its farmer being given assistance.
 *
 * The four statuses an officer actually sees are worked out in
 * App\Support\AssistanceCase, which reads this table together with the
 * beneficiary list and the distribution record. Nothing should decide a
 * status by reading this model alone.
 */
class AssistanceDecision extends Model
{
    protected $table = 'assistance_decisions';

    public const DESIGNATED     = 'designated';
    public const NOT_DESIGNATED = 'not_designated';

    protected $fillable = [
        'damage_report_id', 'farmer_id', 'decision',
        'decided_by', 'decided_at', 'remarks',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function getIsDesignatedAttribute(): bool
    {
        return $this->decision === self::DESIGNATED;
    }
}
