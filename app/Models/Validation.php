<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Validation extends Model
{
    /**
     * The severity scale, in order. Kept here so the dashboard donut, the map
     * legend and any future report all use exactly the same labels and colours.
     *
     * Colours run light to dark as severity rises, so the scale still reads
     * correctly for colour-blind viewers.
     */
    public const SEVERITY_SCALE = [
        'slight'   => ['label' => 'Slight',   'range' => '1 - 25%',  'color' => '#22c55e'],
        'moderate' => ['label' => 'Moderate', 'range' => '26 - 50%', 'color' => '#ca8a04'],
        'partial'  => ['label' => 'Partial',  'range' => '51 - 99%', 'color' => '#e11d48'],
        'total'    => ['label' => 'Total',    'range' => '100%',     'color' => '#6b21a8'],
    ];

    protected $table = 'validations';
    public $timestamps = false;

    protected $fillable = [
        'damage_report_id', 'technician_id', 'inspection_started_at',
        'severity', 'assessed_damage_percent', 'notes',
        'latitude', 'longitude', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'inspection_started_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function photos()
    {
        return $this->hasMany(ValidationPhoto::class);
    }

    public function getSeverityColorAttribute(): string
    {
        return self::SEVERITY_SCALE[$this->severity]['color'] ?? '#94a3b8';
    }
}
