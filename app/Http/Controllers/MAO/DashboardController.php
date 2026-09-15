<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\DamageReport;
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

        // Office-wide, not scoped to the period filter above - a dispute
        // from three months ago is just as unresolved today as a new one.
        // See MAO/AssistanceAllocationController::disputes() for the list
        // this links to.
        $disputeCount = DB::table('assistance_distributions')
            ->where('receipt_status', 'not_received')
            ->count();

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
        | Map widget: the same association bubbles as the full Map page
        | (MAO\MapController / mao.map.index), unfiltered and shaded by
        | report count only. This is a preview, not a second map - no
        | filters, no severity toggle, no verified-pin or heatmap layers.
        | See MAO\MapController::COUNT_SCALE, the scale both places share.
        |------------------------------------------------------------------
        */
        $mapAssociations = $this->mapAssociationBubbles();

        $mapCountLegend = collect(MapController::COUNT_SCALE)->map(function ($band, $index) use ($mapAssociations) {
            $next = MapController::COUNT_SCALE[$index + 1]['min'] ?? null;

            $band['total'] = $mapAssociations->filter(fn ($row) => $row['reports'] >= $band['min']
                && ($next === null || $row['reports'] < $next))->count();

            return $band;
        });

        return view('dashboards.mao', compact(
            'headline',
            'monthly',
            'severity',
            'severityTotal',
            'period',
            'disputeCount',
            'mapAssociations',
            'mapCountLegend'
        ) + ['periods' => self::PERIODS, 'mapCenter' => MapController::CENTER]);
    }

    /**
     * One row per association, with its total damage-report count and the
     * count-scale colour it falls into. Unfiltered - always the whole
     * municipality, regardless of the dashboard's period selector, since the
     * map widget is a spatial snapshot rather than a period figure.
     *
     * A trimmed-down duplicate of the aggregation in MAO\MapController -
     * deliberately not calling into that controller, so the already-working
     * full Map page (filters, severity shading, verified pins, heatmap)
     * stays untouched by this dashboard widget.
     */
    private function mapAssociationBubbles()
    {
        $totals = DB::table('damage_reports as dr')
            ->join('farmers as f', 'f.id', '=', 'dr.farmer_id')
            ->select('f.association_id', DB::raw('COUNT(DISTINCT dr.id) as reports'))
            ->groupBy('f.association_id')
            ->pluck('reports', 'association_id');

        return Association::with('barangay')
            ->orderBy('name')
            ->get()
            ->map(function (Association $association) use ($totals) {
                $reports = (int) ($totals[$association->id] ?? 0);

                return [
                    'id'      => $association->id,
                    'name'    => $association->name,
                    'short'   => $association->short_name,
                    'psgc'    => $association->barangay?->psgc_code,
                    'reports' => $reports,
                    'color'   => $this->countColor($reports),
                ];
            })
            ->values();
    }

    private function countColor(int $reports): string
    {
        $color = MapController::COUNT_SCALE[0]['color'];

        foreach (MapController::COUNT_SCALE as $band) {
            if ($reports >= $band['min']) {
                $color = $band['color'];
            }
        }

        return $color;
    }
}
