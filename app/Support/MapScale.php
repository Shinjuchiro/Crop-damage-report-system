<?php

namespace App\Support;

use App\Models\Validation;

/**
 * The colour scales shared by every role's map.
 *
 * "By Report Count" and "By Worst Severity" have to look the same on the MAO,
 * Technician and Association maps, so the scales live here once. A busy place
 * is not the same thing as a badly damaged one, so the count ramp is
 * deliberately a different hue from the severity colours.
 */
class MapScale
{
    public const COUNT_SCALE = [
        ['min' => 0,  'label' => 'No reports', 'color' => '#cbd5e1'],
        ['min' => 1,  'label' => '1 - 2',      'color' => '#fecdd3'],
        ['min' => 3,  'label' => '3 - 5',      'color' => '#fb7185'],
        ['min' => 6,  'label' => '6 - 10',     'color' => '#e11d48'],
        ['min' => 11, 'label' => '11 or more', 'color' => '#881337'],
    ];

    /**
     * Lowest to highest. 'partial' is retired but still exists on old
     * inspections, so it keeps its place in the ranking (see the note on
     * MAO\MapController::SEVERITY_ORDER).
     */
    public const SEVERITY_ORDER = ['slight', 'moderate', 'partial', 'total'];

    public static function shading(?string $value): string
    {
        return $value === 'severity' ? 'severity' : 'count';
    }

    public static function countColor(int $reports): string
    {
        $color = self::COUNT_SCALE[0]['color'];

        foreach (self::COUNT_SCALE as $band) {
            if ($reports >= $band['min']) {
                $color = $band['color'];
            }
        }

        return $color;
    }

    /** The worst severity key among the given keys (nulls ignored), or null. */
    public static function worstSeverity(iterable $keys): ?string
    {
        $best = -1;

        foreach ($keys as $key) {
            $rank = array_search($key, self::SEVERITY_ORDER, true);
            if ($rank !== false && $rank > $best) {
                $best = $rank;
            }
        }

        return $best >= 0 ? self::SEVERITY_ORDER[$best] : null;
    }

    /**
     * Fields every bubble carries, whatever role's map it is on, so the
     * browser can colour it without knowing which scale is active.
     */
    public static function bubbleColours(int $reports, ?string $severity): array
    {
        return [
            'severity'       => $severity,
            'severity_label' => $severity ? Validation::severityLabel($severity) : 'Not yet assessed',
            'count_color'    => self::countColor($reports),
            'severity_color' => $severity ? Validation::severityColor($severity) : '#cbd5e1',
        ];
    }

    /** Legends with how many bubbles sit in each band. $rows need 'reports' and 'severity'. */
    public static function legends($rows): array
    {
        $rows = collect($rows);

        $count = collect(self::COUNT_SCALE)->map(function ($band, $i) use ($rows) {
            $next = self::COUNT_SCALE[$i + 1]['min'] ?? null;
            $band['total'] = $rows->filter(fn ($r) => $r['reports'] >= $band['min']
                && ($next === null || $r['reports'] < $next))->count();

            return $band;
        })->all();

        $severity = collect(Validation::SEVERITY_SCALE)->map(fn ($row, $key) => $row + [
            'key'   => $key,
            'total' => $rows->where('severity', $key)->count(),
        ])->values()->all();

        return ['count' => $count, 'severity' => $severity];
    }
}
