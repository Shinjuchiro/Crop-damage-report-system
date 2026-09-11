<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\NotificationBroadcast;
use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MAO / Super Admin executive dashboard.
 *
 * Every figure is aggregated live from the database. Nothing is hardcoded.
 */
class DashboardController extends Controller
{
    /** Period filter options shown in the header dropdown. */
    public const PERIODS = [
        'month'   => 'This Month',
        'quarter' => 'Last 3 Months',
        'year'    => 'This Year',
        'all'     => 'All Time',
    ];

    public function index(Request $request)
    {
        $period = array_key_exists($request->query('period'), self::PERIODS)
            ? $request->query('period')
            : 'month';

        $since = match ($period) {
            'quarter' => Carbon::now()->startOfMonth()->subMonths(2),
            'year'    => Carbon::now()->startOfYear(),
            'all'     => null,
            default   => Carbon::now()->startOfMonth(),
        };

        /*
        |------------------------------------------------------------------
        | Headline figures
        |------------------------------------------------------------------
        */
        $reportsInPeriod = DamageReport::query()
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since));

        $totalReports = (clone $reportsInPeriod)->count();

        $affectedFarmers = (clone $reportsInPeriod)->distinct('farmer_id')->count('farmer_id');

        $activeReports = (clone $reportsInPeriod)
            ->whereIn('status', ['pending', 'assigned', 'under_verification', 'flagged'])
            ->count();

        $validatedReports = (clone $reportsInPeriod)
            ->whereIn('status', ['verified', 'approved'])
            ->count();

        $beneficiaries = DB::table('assistance_distributions')
            ->where('receipt_status', 'confirmed_received')
            ->distinct('farmer_id')
            ->count('farmer_id');

        $headline = [
            'affected_farmers' => $affectedFarmers,
            'active_reports'   => $activeReports,
            'validated_rate'   => $totalReports > 0 ? round($validatedReports / $totalReports * 100) : 0,
            'assistance_rate'  => $affectedFarmers > 0 ? round($beneficiaries / $affectedFarmers * 100) : 0,
            'period_label'     => self::PERIODS[$period],
        ];

        /*
        |------------------------------------------------------------------
        | Reports summary: submitted vs validated, last 5 months
        |------------------------------------------------------------------
        */
        $windowStart = Carbon::now()->startOfMonth()->subMonths(4);

        $submittedByMonth = DamageReport::query()
            ->where('created_at', '>=', $windowStart)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"), DB::raw('COUNT(*) as total'))
            ->groupBy('period')
            ->pluck('total', 'period');

        $validatedByMonth = Validation::query()
            ->whereNotNull('validated_at')
            ->where('validated_at', '>=', $windowStart)
            ->select(DB::raw("DATE_FORMAT(validated_at, '%Y-%m') as period"), DB::raw('COUNT(*) as total'))
            ->groupBy('period')
            ->pluck('total', 'period');

        $monthly = collect(range(4, 0))->map(function ($back) use ($submittedByMonth, $validatedByMonth) {
            $month = Carbon::now()->startOfMonth()->subMonths($back);
            $key   = $month->format('Y-m');

            return [
                'label'     => $month->format('M'),
                'submitted' => (int) $submittedByMonth->get($key, 0),
                'validated' => (int) $validatedByMonth->get($key, 0),
            ];
        });

        /*
        |------------------------------------------------------------------
        | Damage by severity, from the technician's own assessment
        |------------------------------------------------------------------
        */
        $severityCounts = Validation::query()
            ->whereNotNull('severity')
            ->when($since, fn ($query) => $query->where('inspection_started_at', '>=', $since))
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        // The scale itself lives on the Validation model, so the donut here and
        // the map legend can never drift apart.
        $severity = collect(Validation::SEVERITY_SCALE)
            ->map(fn ($row, $key) => $row + [
                'key'   => $key,
                'total' => (int) $severityCounts->get($key, 0),
            ])
            ->values();

        $severityTotal = $severity->sum('total');

        /*
        |------------------------------------------------------------------
        | Alerts and notifications
        |------------------------------------------------------------------
        */
        $alerts = NotificationBroadcast::query()
            ->latest()
            ->take(3)
            ->get();

        return view('dashboards.mao', compact(
            'headline',
            'monthly',
            'severity',
            'severityTotal',
            'alerts',
            'period'
        ) + ['periods' => self::PERIODS]);
    }
}
