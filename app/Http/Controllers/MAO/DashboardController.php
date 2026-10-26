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
        /* The dropdown is safe from the trap that caught Membership
           Applications' status filter: "All Time" is a named value ('all'),
           not an empty option, so ConvertEmptyStringsToNull has nothing to
           turn into null. The cast is here only so an absent or emptied
           period is compared as a string rather than as null, and falls to
           'month' on purpose - that is the intended default, not a filter
           silently refusing to clear. */
        $requested = (string) $request->query('period');

        $period = array_key_exists($requested, self::PERIODS) ? $requested : 'month';

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
        /* active() is what keeps this page agreeing with the lists it
           summarises: it drops archived and deleted reports, the same way
           Damage Reports, Validation, the Map and the monthly report all do
           (Oct 2026 decision). Without it the dashboard was the one screen
           in the system still counting records the office had taken out of
           circulation, so a figure here could be higher than the list it
           sits above. */
        $reportsInPeriod = DamageReport::query()
            ->active()
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since));

        $totalReports = (clone $reportsInPeriod)->count();

        $affectedFarmers = (clone $reportsInPeriod)->distinct('farmer_id')->count('farmer_id');

        $activeReports = (clone $reportsInPeriod)
            ->whereIn('status', ['pending', 'assigned', 'under_verification', 'flagged'])
            ->count();

        $validatedReports = (clone $reportsInPeriod)
            ->whereIn('status', ['verified', 'approved'])
            ->count();

        /* THE NUMERATOR HAS TO COME OUT OF THE DENOMINATOR
           (fixed Oct 2026: the card read 200%)

           This counts the farmers behind "Assistance Distributed", which the
           card prints as a percentage "of affected farmers". For that
           sentence to be true, every farmer counted here has to also be one
           of the $affectedFarmers above - same period, same set of reports.

           It used to be neither. The old version counted every confirmed
           receipt the office had ever recorded, with no period and no link
           back to the reports in view, and divided it by the farmers
           affected in the selected period. In a month with one affected
           farmer and two historical receipts on file, that is 200%, which
           is what the office saw. It is not a rounding problem: the two
           populations were different in kind, because a farmer can receive
           assistance for a report filed months before the one in view.

           Joining through damage_reports fixes it at the root rather than
           by clamping the result at 100, which would have hidden the
           mismatch instead of removing it. The period filter and the
           archived/deleted exclusions come along for free, because they are
           the ones already applied to $reportsInPeriod.

           Two details that make this provably a subset rather than merely
           a closer guess. The join is an inner one and loses nothing:
           damage_report_id on assistance_distributions is NOT NULL and
           constrained (migration 2024_01_01_000020), so every distribution
           has exactly one report behind it - "which report made them
           eligible", in that migration's own words. And it counts
           dr.farmer_id, not ad.farmer_id: those should always be the same
           person, but it is dr.farmer_id that $affectedFarmers counted, so
           taking it from the same column is what guarantees the ratio
           cannot climb above 100% even if a row is ever written with the
           two disagreeing. */
        $beneficiaries = DB::table('assistance_distributions as ad')
            ->join('damage_reports as dr', 'dr.id', '=', 'ad.damage_report_id')
            ->whereNull('dr.archived_at')
            ->whereNull('dr.deleted_at')
            ->where('ad.receipt_status', 'confirmed_received')
            ->when($since, fn ($query) => $query->where('dr.created_at', '>=', $since))
            ->distinct('dr.farmer_id')
            ->count('dr.farmer_id');

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
            ->active()
            ->where('created_at', '>=', $windowStart)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"), DB::raw('COUNT(*) as total'))
            ->groupBy('period')
            ->pluck('total', 'period');

        /* An inspection is counted through its report, not on its own: if
           the report has been archived or deleted it is not on the Damage
           Reports list any more, so counting its inspection here would draw
           a validated line above a submitted line that no longer includes
           it. Same reasoning, and the same whereHas, as
           MonthlyReportBuilder::inspections(). */
        $validatedByMonth = Validation::query()
            ->whereHas('damageReport', fn ($report) => $report->active())
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
            ->whereHas('damageReport', fn ($report) => $report->active())
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

        /* The eight Crop Planted / Crop Damage / Validation figures. All-time
           and office-wide on purpose, not tied to the period dropdown: they
           are totals of what is in the system, defined once in MonitorStats
           so each one matches the monitoring page it links to. */
        $flashcards = \App\Support\MonitorStats::all();

        return view('dashboards.mao', compact(
            'headline',
            'monthly',
            'severity',
            'severityTotal',
            'period',
            'disputeCount',
            'mapAssociations',
            'mapCountLegend',
            'flashcards'
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
            // Written out rather than ->active(), because this is the query
            // builder and not the model. Same two columns the scope checks.
            ->whereNull('dr.archived_at')
            ->whereNull('dr.deleted_at')
            ->select(
                'f.association_id',
                DB::raw('COUNT(DISTINCT dr.id) as reports'),
                DB::raw("COUNT(DISTINCT CASE WHEN dr.status = 'pending' THEN dr.id END) as awaiting"),
                DB::raw("COUNT(DISTINCT CASE WHEN dr.status IN ('assigned', 'under_verification') THEN dr.id END) as assigned"),
                DB::raw("COUNT(DISTINCT CASE WHEN dr.status IN ('verified', 'approved') THEN dr.id END) as verified")
            )
            ->groupBy('f.association_id')
            ->get()
            ->keyBy('association_id');

        return Association::with('barangay')
            ->orderBy('name')
            ->get()
            ->map(function (Association $association) use ($totals) {
                $counts  = $totals->get($association->id);
                $reports = (int) ($counts->reports ?? 0);

                return [
                    'id'      => $association->id,
                    'name'    => $association->name,
                    'short'   => $association->short_name,
                    'psgc'    => $association->barangay?->psgc_code,
                    'reports' => $reports,
                    'awaiting'       => (int) ($counts->awaiting ?? 0),
                    'assigned'       => (int) ($counts->assigned ?? 0),
                    'verified_count' => (int) ($counts->verified ?? 0),
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
