<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Validation extends Model
{
    /**
     * The severity scale, in order. Kept here so the inspection form, the
     * dashboard donut, the map legend, the filters and the reports all use
     * exactly the same labels, bands and colours.
     *
     * Colours run light to dark as severity rises, so the scale still reads
     * correctly for colour-blind viewers.
     *
     * THREE LEVELS, NOT FOUR (Oct 2026)
     *
     * It used to be Slight 1-25, Moderate 26-50, Partial 51-99, Total 100.
     * The client asked for three, so Partial is retired and the bands were
     * redrawn to cover 1 to 100 with no gap: Slight 1-30, Moderate 31-80,
     * Total 81-100. The bands live here as min and max rather than only as
     * a printed range, because the inspection form, its Alpine copy and the
     * server-side check all used to carry their own copy of the numbers,
     * which is three places to forget.
     */
    public const SEVERITY_SCALE = [
        'slight'   => ['label' => 'Slight',   'range' => '1 - 30%',   'min' => 1,  'max' => 30,  'color' => '#22c55e'],
        'moderate' => ['label' => 'Moderate', 'range' => '31 - 80%',  'min' => 31, 'max' => 80,  'color' => '#ca8a04'],
        'total'    => ['label' => 'Total',    'range' => '81 - 100%', 'min' => 81, 'max' => 100, 'color' => '#6b21a8'],
    ];

    /**
     * Severities no longer offered, kept only so old inspections still read
     * correctly.
     *
     * Nothing new may be saved with one of these: SEVERITY_SCALE is what
     * the form offers and what the controller validates against. They are
     * NOT converted into one of the three (an inspection that found 51%
     * damage was not a total loss, and rewriting history to say otherwise
     * would be a lie in the office's own records), and by the office's
     * decision they are not offered in filters or legends either, so a
     * severity breakdown covers the three current levels only.
     *
     * severityLabel() and severityColor() look here as well, which is what
     * keeps an old Partial inspection showing "Partial" on its own pages.
     *
     * The database column is left alone on purpose. validations.severity is
     * an enum of the original four, and the three in use are a subset of
     * it, so nothing has to migrate. Narrowing the enum to three would mean
     * rewriting or deleting every inspection that recorded Partial, which
     * is the one thing the office asked not to happen.
     */
    public const RETIRED_SEVERITIES = [
        'partial' => ['label' => 'Partial', 'range' => '51 - 99%', 'color' => '#e11d48'],
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
        return self::severityColor($this->severity);
    }

    /* ==================================================================
     | The severity scale, read from one place
     ================================================================== */

    /**
     * key => [min, max], for the agreement check between the chosen
     * severity and the assessed percentage.
     */
    public static function severityBands(): array
    {
        return array_map(fn (array $band) => [$band['min'], $band['max']], self::SEVERITY_SCALE);
    }

    /**
     * key => label, for the inspection form's own Alpine copy.
     */
    public static function severityLabels(): array
    {
        return array_map(fn (array $band) => $band['label'], self::SEVERITY_SCALE);
    }

    /**
     * The label for any severity ever recorded, retired ones included, so a
     * report printed today still describes an old inspection the way the
     * technician left it.
     */
    public static function severityLabel(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::SEVERITY_SCALE[$key]['label']
            ?? self::RETIRED_SEVERITIES[$key]['label']
            ?? ucfirst($key);
    }

    public static function severityColor(?string $key): string
    {
        return self::SEVERITY_SCALE[$key]['color']
            ?? self::RETIRED_SEVERITIES[$key]['color']
            ?? '#94a3b8';
    }

    /**
     * The colour for a bare percentage, where there is no severity to read:
     * the technician dashboard's pin, which colours whichever figure it has
     * (the assessed percentage, or the farmer's estimate until then).
     *
     * Walks the scale from the top so it cannot disagree with the bands.
     */
    public static function colorForPercent(float|int|null $percent): string
    {
        if ($percent === null) {
            return '#94a3b8';
        }

        foreach (array_reverse(self::SEVERITY_SCALE) as $band) {
            if ($percent >= $band['min']) {
                return $band['color'];
            }
        }

        return '#94a3b8';
    }

    /**
     * The severity band a bare percentage falls in: 'slight', 'moderate' or
     * 'total'. Used to show a severity beside the FARMER'S estimate, before
     * any technician has assessed the report. It is a reading of the figure
     * against the same bands, never a stored verdict, and the technician's
     * own severity is always shown separately.
     */
    public static function severityKeyForPercent(float|int|null $percent): ?string
    {
        if ($percent === null || $percent < 1) {
            return null;
        }

        foreach (array_reverse(self::SEVERITY_SCALE, true) as $key => $band) {
            if ($percent >= $band['min']) {
                return $key;
            }
        }

        return null;
    }
}
