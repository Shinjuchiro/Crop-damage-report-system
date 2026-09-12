<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ReportGeneration extends Model
{
    protected $table = 'report_generations';
    public $timestamps = false;

    protected $fillable = ['association_id', 'year', 'month', 'generated_by', 'generated_at'];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** Null for MAO's office-wide reports; set for an association's own scoped report history. */
    public function association()
    {
        return $this->belongsTo(Association::class);
    }

    /** "September 2026" instead of gluing month and year together on every view. */
    public function getPeriodLabelAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }
}
