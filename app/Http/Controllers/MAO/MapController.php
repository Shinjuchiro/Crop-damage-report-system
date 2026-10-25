<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\DamageReport;
use App\Models\Validation;
use App\Support\MapFilters;
use App\Support\MapScale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Spatial view of crop damage across Tanza.
 *
 * Damage is aggregated by FARMERS' ASSOCIATION, since that is the unit the MAO
 * allocates assistance to.
 *
 * Each association is drawn as ONE shaded area, not as a set of shaded
 * barangays: the barangays it covers, from the office's area coverage sheet
 * (see AssociationCoverageSeeder), are merged into a single outline in the
 * browser so the borders between them disappear. What the controller sends is
 * the list of PSGC codes; the merging is in the view, next to the map.
 *
 * The fills are translucent and all eight are drawn at once, so ground several
 * associations work in shows all of their colours layered - which is the real
 * picture: Tres Cruces is covered by five of the eight and Calibuyo by four.
 *
 * On top of the shading each association keeps a bubble, sized by how many
 * reports its members filed and coloured by the count or severity scale, so
 * the damage reading survives the change. The bubble sits at the middle of the
 * association's coverage, not at its office: Tanza Farmers Association's
 * office is in Daang Amaya, which it does not cover.
 *
 * Because the aggregation runs off the farmer's association, not off GPS, the
 * map is populated from the first report and does not wait on field inspections.
 *
 * Boundaries: public/geo/tanza-barangays.json, from faeldon/philippines-json-maps
 * (2019 PSGC), joined to our barangays table on psgc_code.
 */
class MapController extends Controller
{
    /** Roughly the centre of Tanza, used before the boundaries load. */
    public const CENTER = ['lat' => 14.3833, 'lng' => 120.8500, 'zoom' => 12];

    /**
     * Sequential ramp for "how many reports", light to dark. Deliberately a
     * different scale from severity, because a busy association is not the same
     * thing as a badly damaged one.
     */
    public const COUNT_SCALE = MapScale::COUNT_SCALE;

    /**
     * Worst-first ranking for the severity shading, lowest to highest.
     *
     * 'partial' is retired (Oct 2026: the client cut the scale to three)
     * and is not offered in the legend or the filter, which are both built
     * from Validation::SEVERITY_SCALE. It stays in this ranking, and in the
     * FIELD() list below that has to match it position for position,
     * because inspections recorded under the old scale still exist: drop it
     * here and FIELD() returns 0 for those rows, which this code reads as
     * "not yet assessed", so a barangay whose only inspection found heavy
     * damage would shade as though nobody had ever been there.
     */
    private const SEVERITY_ORDER = ['slight', 'moderate', 'partial', 'total'];

    /**
     * One colour per association, for the coverage shading. Eight hues far
     * enough apart to stay apart when two or three are layered over the same
     * barangay at low opacity, which is most of Tres Cruces.
     *
     * Deliberately NOT the green of the system: these say "who", not "how
     * bad", and the count and severity scales own the second question.
     */
    public const COVERAGE_COLORS = [
        '#0ea5e9', // sky
        '#16a34a', // green
        '#f59e0b', // amber
        '#8b5cf6', // violet
        '#e11d48', // rose
        '#14b8a6', // teal
        '#db2777', // pink
        '#64748b', // slate
    ];

    public function index(Request $request)
    {
        $shading = MapScale::shading($request->query('shading'));

        // Date range, association, disaster and severity: the MAO's four
        // map filters (App\Support\MapFilters). Everything below, the
        // bubbles, the pins, the heatmap and the summary cards, is built
        // from this one list of report ids, so they cannot disagree.
        $filters   = MapFilters::from($request, 'mao');
        $reportIds = $this->filteredReports($filters)->pluck('id');

        /*
        |------------------------------------------------------------------
        | Per-association totals
        |------------------------------------------------------------------
        */
        $totals = collect();
        $areas  = collect();

        if ($reportIds->isNotEmpty()) {
            $totals = DB::table('damage_reports as dr')
                ->join('farmers as f', 'f.id', '=', 'dr.farmer_id')
                ->leftJoin('validations as v', 'v.damage_report_id', '=', 'dr.id')
                ->whereIn('dr.id', $reportIds)
                ->select(
                    'f.association_id',
                    DB::raw('COUNT(DISTINCT dr.id) as reports'),
                    DB::raw('COUNT(DISTINCT dr.farmer_id) as farmers'),
                    // The popup's breakdown. Real counts from the same filtered
                    // set as the total; assigning or verifying a report moves
                    // it between these, and never changes the total.
                    DB::raw("COUNT(DISTINCT CASE WHEN dr.status = 'pending' THEN dr.id END) as awaiting"),
                    DB::raw("COUNT(DISTINCT CASE WHEN dr.status IN ('assigned', 'under_verification') THEN dr.id END) as assigned"),
                    DB::raw("COUNT(DISTINCT CASE WHEN dr.status IN ('verified', 'approved') THEN dr.id END) as verified"),
                    DB::raw("MAX(FIELD(v.severity, 'slight', 'moderate', 'partial', 'total')) as severity_rank")
                )
                ->groupBy('f.association_id')
                ->get()
                ->keyBy('association_id');

            $areas = DB::table('damage_report_crops as drc')
                ->join('damage_reports as dr', 'dr.id', '=', 'drc.damage_report_id')
                ->join('farmers as f', 'f.id', '=', 'dr.farmer_id')
                ->whereIn('dr.id', $reportIds)
                ->select('f.association_id', DB::raw('SUM(drc.damaged_area_hectares) as area'))
                ->groupBy('f.association_id')
                ->pluck('area', 'association_id');
        }

        // Membership is derived, never stored: it is simply how many farmers
        // registered and chose this association.
        $registered = DB::table('farmers')
            ->select('association_id', DB::raw('COUNT(*) as total'))
            ->groupBy('association_id')
            ->pluck('total', 'association_id');

        // The legend and the severity filter are built from this, so both
        // offer the three current levels only. Retired severities are read
        // through Validation::severityLabel()/severityColor() where an old
        // record has to be displayed, not offered as something to pick.
        $severityScale = Validation::SEVERITY_SCALE;

        $associations = Association::with(['barangay', 'coverage'])
            ->orderBy('name')
            ->get()
            ->values()
            ->map(function (Association $association, int $index) use ($totals, $areas, $registered) {
                $row     = $totals->get($association->id);
                $reports = (int) ($row->reports ?? 0);
                $rank    = (int) ($row->severity_rank ?? 0);
                $key     = $rank > 0 ? self::SEVERITY_ORDER[$rank - 1] : null;

                return [
                    'id'             => $association->id,
                    'name'           => $association->name,
                    'short'          => $association->short_name,
                    'location'       => $association->barangay?->name ?? $association->location,
                    'psgc'           => $association->barangay?->psgc_code,
                    'members'        => (int) ($registered[$association->id] ?? 0),
                    'reports'        => $reports,
                    'farmers'        => (int) ($row->farmers ?? 0),
                    'awaiting'       => (int) ($row->awaiting ?? 0),
                    'assigned'       => (int) ($row->assigned ?? 0),
                    'verified_count' => (int) ($row->verified ?? 0),
                    'area'           => round((float) ($areas[$association->id] ?? 0), 2),
                    'severity'       => $key,
                    // Through severityLabel()/severityColor() rather than
                    // $severityScale[$key], because $key can be a retired
                    // severity from an old inspection and the scale no
                    // longer holds those - a direct lookup would print an
                    // empty label and lose the pin's colour.
                    'severity_label' => $key ? Validation::severityLabel($key) : 'Not yet assessed',
                    'count_color'    => $this->countColor($reports),
                    'severity_color' => $key ? Validation::severityColor($key) : '#cbd5e1',
                    'url'            => route('mao.damage-reports.index', ['association_id' => $association->id]),

                    // The shading. 'coverage' is the PSGC codes of the
                    // barangays this association works in, which is how the
                    // boundary file is keyed; 'color' is its own colour.
                    // Ordered by name above, so the colour a given
                    // association gets is the same on every page load.
                    'color'          => self::COVERAGE_COLORS[$index % count(self::COVERAGE_COLORS)],
                    'coverage'       => $association->coverage
                                            ->pluck('psgc_code')->filter()->values(),
                    'coverage_names' => $association->coverage
                                            ->pluck('name')->sort()->values()->join(', ') ?: 'No coverage set',
                ];
            });

        /*
        |------------------------------------------------------------------
        | Technician-verified pins, drawn on top
        |------------------------------------------------------------------
        */
        $points = Validation::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereIn('damage_report_id', $reportIds)
            ->with(['damageReport.farmer.association', 'damageReport.farmer.barangay', 'damageReport.crops.crop'])
            ->get()
            ->map(function (Validation $validation) {
                $report = $validation->damageReport;
                $farmer = $report?->farmer;

                return [
                    'lat'         => (float) $validation->latitude,
                    'lng'         => (float) $validation->longitude,
                    'color'       => Validation::severityColor($validation->severity),
                    'label'       => Validation::severityLabel($validation->severity) ?? 'Not assessed',
                    'report'      => 'DR-' . str_pad($report->id, 4, '0', STR_PAD_LEFT),
                    'farmer'      => $farmer?->full_name ?? 'Unknown',
                    'association' => $farmer?->association?->name ?? 'No association',
                    // So the chips under the map can take a pin off with the
                    // association it belongs to, rather than leaving the
                    // reports of a hidden association sitting on the map.
                    'association_id' => $farmer?->association_id,
                    'barangay'    => $farmer?->barangay?->name ?? 'Unknown barangay',
                    'crops'       => $report->crops->map(fn ($c) => $c->crop?->name)->filter()->join(', ') ?: 'Not specified',
                    'assessed'    => $validation->assessed_damage_percent,
                    'url'         => route('mao.damage-reports.show', $report),
                ];
            })->values();

        /*
        |------------------------------------------------------------------
        | What actually goes on the map
        |------------------------------------------------------------------
        | This is a coverage map, and coverage comes from the office's area
        | coverage sheet. An association with no coverage recorded has no
        | area to shade, so it has no place here: it was drawing a bubble at
        | its office barangay and claiming a colour in the key, which put
        | rows that are not on the sheet - a half-finished entry, a test
        | record - on the map beside the eight that are.
        |
        | The one exception is an association that HAS damage reports. The
        | map's first job is showing damage, and dropping an association
        | whose members have filed reports would hide real figures to tidy
        | up the key. Those still appear, and the amber notice above the map
        | counts every association missing coverage either way, so nothing
        | goes missing quietly.
        */
        $withCoverage = $associations->filter(
            fn ($row) => $row['coverage']->isNotEmpty() || $row['reports'] > 0
        )->values();

        /*
        |------------------------------------------------------------------
        | Legends and coverage
        |------------------------------------------------------------------
        */
        $countLegend = collect(self::COUNT_SCALE)->map(function ($band, $index) use ($withCoverage) {
            $next = self::COUNT_SCALE[$index + 1]['min'] ?? null;

            $band['total'] = $withCoverage->filter(fn ($row) => $row['reports'] >= $band['min']
                && ($next === null || $row['reports'] < $next))->count();

            return $band;
        });

        $severityLegend = collect($severityScale)->map(fn ($row, $key) => $row + [
            'key'   => $key,
            'total' => $withCoverage->where('severity', $key)->count(),
        ])->values();

        $coverage = [
            'reports'      => $reportIds->count(),
            'associations' => $withCoverage->where('reports', '>', 0)->count(),
            'total_assoc'  => $withCoverage->count(),
            'area'         => $withCoverage->sum('area'),
            'pinned'       => $points->count(),
            // Counted over ALL associations, not just the drawn ones: the
            // whole point of this notice is to name the ones that are
            // missing from the map.
            'unplaced'     => $associations->filter(fn ($row) => $row['coverage']->isEmpty())->count(),
        ];

        return view('mao.map.index', [
            'associationStats' => $withCoverage,
            'points'           => $points,
            'shading'          => $shading,
            'countLegend'      => $countLegend,
            'severityLegend'   => $severityLegend,
            'coverage'         => $coverage,
            'center'           => self::CENTER,
            'filters'          => $filters,
            'associations'     => Association::orderBy('name')->get(),
        ]);
    }

    private function filteredReports(array $filters)
    {
        // active(): archived and deleted reports are off every list, so they
        // are off the map too (same rule as the Association and Technician maps).
        return MapFilters::apply(DamageReport::active(), $filters);
    }

    private function countColor(int $reports): string
    {
        return MapScale::countColor($reports);
    }
}
