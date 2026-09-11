<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Spatial view of crop damage across Tanza.
 *
 * Damage is aggregated by FARMERS' ASSOCIATION, since that is the unit the MAO
 * allocates assistance to. Each association is drawn at the barangay where its
 * office sits, sized by how many reports its members filed. The barangay
 * outlines underneath are context only.
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
    public const COUNT_SCALE = [
        ['min' => 0,  'label' => 'No reports', 'color' => '#cbd5e1'],
        ['min' => 1,  'label' => '1 - 2',      'color' => '#fecdd3'],
        ['min' => 3,  'label' => '3 - 5',      'color' => '#fb7185'],
        ['min' => 6,  'label' => '6 - 10',     'color' => '#e11d48'],
        ['min' => 11, 'label' => '11 or more', 'color' => '#881337'],
    ];

    private const SEVERITY_ORDER = ['slight', 'moderate', 'partial', 'total'];

    public function index(Request $request)
    {
        $shading = $request->query('shading') === 'severity' ? 'severity' : 'count';

        $reportIds = $this->filteredReports($request)->pluck('id');

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

        $severityScale = Validation::SEVERITY_SCALE;

        $associations = Association::with('barangay')
            ->orderBy('name')
            ->get()
            ->map(function (Association $association) use ($totals, $areas, $registered, $severityScale) {
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
                    'area'           => round((float) ($areas[$association->id] ?? 0), 2),
                    'severity'       => $key,
                    'severity_label' => $key ? $severityScale[$key]['label'] : 'Not yet assessed',
                    'count_color'    => $this->countColor($reports),
                    'severity_color' => $key ? $severityScale[$key]['color'] : '#cbd5e1',
                    'url'            => route('mao.damage-reports.index', ['association_id' => $association->id]),
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
            ->map(function (Validation $validation) use ($severityScale) {
                $report = $validation->damageReport;
                $farmer = $report?->farmer;

                return [
                    'lat'         => (float) $validation->latitude,
                    'lng'         => (float) $validation->longitude,
                    'color'       => $severityScale[$validation->severity]['color'] ?? '#94a3b8',
                    'label'       => $severityScale[$validation->severity]['label'] ?? 'Not assessed',
                    'report'      => 'DR-' . str_pad($report->id, 4, '0', STR_PAD_LEFT),
                    'farmer'      => $farmer?->full_name ?? 'Unknown',
                    'association' => $farmer?->association?->name ?? 'No association',
                    'barangay'    => $farmer?->barangay?->name ?? 'Unknown barangay',
                    'crops'       => $report->crops->map(fn ($c) => $c->crop?->name)->filter()->join(', ') ?: 'Not specified',
                    'assessed'    => $validation->assessed_damage_percent,
                    'url'         => route('mao.damage-reports.show', $report),
                ];
            })->values();

        /*
        |------------------------------------------------------------------
        | Legends and coverage
        |------------------------------------------------------------------
        */
        $countLegend = collect(self::COUNT_SCALE)->map(function ($band, $index) use ($associations) {
            $next = self::COUNT_SCALE[$index + 1]['min'] ?? null;

            $band['total'] = $associations->filter(fn ($row) => $row['reports'] >= $band['min']
                && ($next === null || $row['reports'] < $next))->count();

            return $band;
        });

        $severityLegend = collect($severityScale)->map(fn ($row, $key) => $row + [
            'key'   => $key,
            'total' => $associations->where('severity', $key)->count(),
        ])->values();

        $coverage = [
            'reports'      => $reportIds->count(),
            'associations' => $associations->where('reports', '>', 0)->count(),
            'total_assoc'  => $associations->count(),
            'area'         => $associations->sum('area'),
            'pinned'       => $points->count(),
            'unplaced'     => $associations->whereNull('psgc')->count(),
        ];

        $years = DamageReport::query()
            ->select(DB::raw('DISTINCT YEAR(created_at) as year'))
            ->orderByDesc('year')
            ->pluck('year');

        if ($years->isEmpty()) {
            $years = collect([now()->year]);
        }

        return view('mao.map.index', [
            'associationStats' => $associations->values(),
            'points'           => $points,
            'shading'          => $shading,
            'countLegend'      => $countLegend,
            'severityLegend'   => $severityLegend,
            'coverage'         => $coverage,
            'center'           => self::CENTER,
            'years'            => $years,
            'barangays'        => Barangay::orderBy('name')->get(),
            'associations'     => Association::orderBy('name')->get(),
            'crops'            => Crop::orderBy('name')->get(),
            'disasters'        => Disaster::orderByDesc('date_start')->get(),
            'statuses'         => DamageReportMonitorController::STATUSES,
        ]);
    }

    private function filteredReports(Request $request)
    {
        return DamageReport::query()
            ->when($request->filled('status'),
                fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('year'),
                fn ($query) => $query->whereYear('created_at', $request->year))
            ->when($request->filled('month'),
                fn ($query) => $query->whereMonth('created_at', $request->month))
            ->when($request->filled('disaster_id'), fn ($query) => $query
                ->whereHas('disasters', fn ($d) => $d->where('disasters.id', $request->disaster_id)))
            ->when($request->filled('crop_id'), fn ($query) => $query
                ->whereHas('crops', fn ($c) => $c->where('crop_id', $request->crop_id)))
            ->when($request->filled('severity'), fn ($query) => $query
                ->whereHas('validation', fn ($v) => $v->where('severity', $request->severity)))
            ->whereHas('farmer', function ($farmer) use ($request) {
                $farmer
                    ->when($request->filled('barangay_id'),
                        fn ($query) => $query->where('barangay_id', $request->barangay_id))
                    ->when($request->filled('association_id'),
                        fn ($query) => $query->where('association_id', $request->association_id));
            });
    }

    private function countColor(int $reports): string
    {
        $color = self::COUNT_SCALE[0]['color'];

        foreach (self::COUNT_SCALE as $band) {
            if ($reports >= $band['min']) {
                $color = $band['color'];
            }
        }

        return $color;
    }
}
