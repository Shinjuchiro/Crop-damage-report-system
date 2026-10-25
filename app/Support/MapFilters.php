<?php

namespace App\Support;

use App\Models\Validation;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The filters on the Maps and Visualization pages, one definition for all
 * three roles.
 *
 *   MAO           date range, association, disaster, severity
 *   Technician    date range, disaster, severity, validation status
 *   Association   date range, disaster, severity
 *
 * Only the filters a role is allowed to use are read from the request, so a
 * technician cannot widen the map to other technicians' work, nor an
 * association officer to other associations, by adding ?association_id=
 * to the address. Scoping to the technician or association itself is done by
 * the controller before apply() is called; nothing here can undo it.
 *
 * The date range is the date the farmer SUBMITTED the report.
 */
class MapFilters
{
    public const RANGES = [
        'all'        => 'All Time',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year'  => 'This Year',
        'custom'     => 'Custom Date Range',
    ];

    /** Matches DamageReport.damage_cause; 'other' also covers pests, disease and heat. */
    public const DISASTERS = [
        'typhoon'      => 'Typhoon',
        'flood'        => 'Flood',
        'drought'      => 'Drought',
        'strong_winds' => 'Strong Winds',
        'other'        => 'Other',
    ];

    /** Technician only. Each label covers the report statuses the technician map already groups together. */
    public const VALIDATION = [
        'assigned' => ['label' => 'Assigned for Inspection', 'statuses' => ['assigned', 'under_verification']],
        'verified' => ['label' => 'Verified',                'statuses' => ['verified', 'approved']],
    ];

    /**
     * Read and clean the filters for one role ('mao', 'technician' or 'association').
     */
    public static function from(Request $request, string $role): array
    {
        $range = (string) $request->query('range', 'all');
        $range = array_key_exists($range, self::RANGES) ? $range : 'all';

        $start = $end = null;
        $notice = null;

        if ($range === 'custom') {
            $start = self::date($request->query('start'));
            $end   = self::date($request->query('end'));

            if ($start && $end && $end->lt($start)) {
                [$start, $end] = [$end, $start];
                $notice = 'The end date was before the start date, so the two were swapped.';
            }
        }

        $disaster = (string) $request->query('disaster', '');
        $disaster = array_key_exists($disaster, self::DISASTERS) ? $disaster : '';

        $severity = (string) $request->query('severity', '');
        $severity = array_key_exists($severity, Validation::SEVERITY_SCALE) ? $severity : '';

        $association = $role === 'mao' ? (int) $request->query('association_id', 0) : 0;

        $validation = (string) $request->query('validation', '');
        $validation = ($role === 'technician' && array_key_exists($validation, self::VALIDATION)) ? $validation : '';

        return [
            'role'           => $role,
            'range'          => $range,
            'start'          => $start?->toDateString(),
            'end'            => $end?->toDateString(),
            'disaster'       => $disaster,
            'severity'       => $severity,
            'association_id' => $association ?: null,
            'validation'     => $validation,
            'notice'         => $notice,
        ];
    }

    /** Apply the cleaned filters to a DamageReport query. */
    public static function apply($query, array $f)
    {
        [$from, $to] = self::window($f);

        return $query
            ->when($from, fn ($q) => $q->where('damage_reports.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('damage_reports.created_at', '<=', $to))
            ->when($f['disaster'] !== '', function ($q) use ($f) {
                $f['disaster'] === 'other'
                    ? $q->where(fn ($w) => $w->whereNull('damage_reports.damage_cause')
                        ->orWhereNotIn('damage_reports.damage_cause', ['typhoon', 'flood', 'drought', 'strong_winds']))
                    : $q->where('damage_reports.damage_cause', $f['disaster']);
            })
            ->when($f['severity'] !== '', fn ($q) => $q
                ->whereHas('validation', fn ($v) => $v->where('severity', $f['severity'])))
            ->when($f['validation'] !== '', fn ($q) => $q
                ->whereIn('damage_reports.status', self::VALIDATION[$f['validation']]['statuses']))
            ->when($f['association_id'], fn ($q, $id) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $id)));
    }

    /** [from, to] as Carbon (either may be null) for the chosen range. */
    public static function window(array $f): array
    {
        $now = now();

        return match ($f['range']) {
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'     => [
                $f['start'] ? Carbon::parse($f['start'])->startOfDay() : null,
                $f['end'] ? Carbon::parse($f['end'])->endOfDay() : null,
            ],
            default      => [null, null],
        };
    }

    /** True when anything narrows the map, for the "Filters applied" label. */
    public static function active(array $f): bool
    {
        return $f['range'] !== 'all' || $f['disaster'] !== '' || $f['severity'] !== ''
            || $f['validation'] !== '' || ! empty($f['association_id']);
    }

    private static function date($value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
