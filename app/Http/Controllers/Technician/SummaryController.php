<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reports (sidebar item) - a technician's own performance summary.
 *
 * Not to be confused with the MAO's Monthly Reports (proposal sections
 * 74-76), which cover the whole office and export to PDF and Excel. This
 * page is one technician looking at their own work: how many inspections
 * they have finished, how long each one takes them, and what kind of
 * damage they have been assessing. On screen only - nothing here is
 * downloaded, because it never leaves this technician's own account.
 */
class SummaryController extends Controller
{
    /**
     * The period selector. Defaults to This Year rather than This Month,
     * because a technician's own trend is more useful to see across a
     * longer stretch than the dashboard's day-to-day queue is.
     */
    public const PERIODS = [
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year'  => 'This Year',
        'all'        => 'All Time',
    ];

    public function index(Request $request)
    {
        $technicianId = Auth::id();

        $period = array_key_exists((string) $request->query('period'), self::PERIODS)
            ? $request->query('period')
            : 'this_year';

        [$from, $to] = $this->periodRange($period);

        $base = Validation::where('technician_id', $technicianId)
            ->whereNotNull('validated_at')
            ->when($from, fn ($q) => $q->whereBetween('validated_at', [$from, $to]));

        $completed = (clone $base)->count();

        /*
        |----------------------------------------------------------------
        | Average turnaround: Start Inspection to Confirm & Submit.
        |
        | In whole hours rather than days, because a technician does not
        | always finish a field visit the same day it was started, and
        | "1 day" would read as an overnight delay that never happened.
        |----------------------------------------------------------------
        */
        $avgHours = (clone $base)
            ->whereNotNull('inspection_started_at')
            ->get(['inspection_started_at', 'validated_at'])
            ->map(fn ($v) => $v->inspection_started_at->diffInHours($v->validated_at))
            ->avg();

        /*
        |----------------------------------------------------------------
        | How far this technician's assessments tend to sit from what the
        | farmer originally estimated. Not a score - just a fact worth
        | seeing about their own pattern of assessment.
        |----------------------------------------------------------------
        */
        $avgGap = (clone $base)
            ->with('damageReport.crops')
            ->get()
            ->map(function ($v) {
                $estimate = $v->damageReport?->crops->avg('estimated_damage_percent');

                return $estimate === null ? null : abs($estimate - (float) $v->assessed_damage_percent);
            })
            ->filter(fn ($gap) => $gap !== null)
            ->avg();

        /*
        |----------------------------------------------------------------
        | Severity breakdown for the selected period.
        |----------------------------------------------------------------
        */
        $severityCounts = (clone $base)
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $severityBreakdown = collect(Validation::SEVERITY_SCALE)
            ->map(fn ($band, $key) => $band + [
                'key'   => $key,
                'total' => (int) ($severityCounts[$key] ?? 0),
            ])
            ->values();

        /*
        |----------------------------------------------------------------
        | Inspections completed by month, the last 6 calendar months.
        |
        | Deliberately not filtered by the period selector above - this
        | chart's whole purpose is to show the trend across months, which
        | a "this month only" filter would erase.
        |----------------------------------------------------------------
        */
        $months = collect(range(5, 0))
            ->map(fn ($i) => Carbon::now()->subMonthsNoOverflow($i)->startOfMonth());

        $monthlyCounts = Validation::where('technician_id', $technicianId)
            ->whereNotNull('validated_at')
            ->where('validated_at', '>=', $months->first())
            ->select(
                DB::raw('YEAR(validated_at) as y'),
                DB::raw('MONTH(validated_at) as m'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($row) => $row->y . '-' . $row->m);

        $byMonth = $months->map(function ($month) use ($monthlyCounts) {
            $row = $monthlyCounts->get($month->year . '-' . $month->month);

            return [
                'label' => $month->format('M Y'),
                'total' => (int) ($row->total ?? 0),
            ];
        })->values();

        return view('technician.summaries.index', [
            'period'  => $period,
            'periods' => self::PERIODS,
            'summary' => [
                'completed' => $completed,
                'avg_hours' => $avgHours,
                'avg_gap'   => $avgGap,
            ],
            'severityBreakdown' => $severityBreakdown,
            'byMonth'           => $byMonth,
        ]);
    }

    private function periodRange(string $period): array
    {
        return match ($period) {
            'last_month' => [
                Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                Carbon::now()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            'all'       => [null, null],
            default     => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };
    }
}
