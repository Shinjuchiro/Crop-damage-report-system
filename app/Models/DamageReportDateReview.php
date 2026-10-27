<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One MAO review of a report's date discrepancies. Append-only: a later review
 * is a new row, so the history stays. See the create migration.
 */
class DamageReportDateReview extends Model
{
    protected $table = 'damage_report_date_reviews';

    public $timestamps = false;

    protected $fillable = ['damage_report_id', 'reviewed_by', 'remarks', 'flags_snapshot', 'official_date', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'flags_snapshot' => 'array',
            'official_date'  => 'date',
            'reviewed_at'    => 'datetime',
        ];
    }

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
