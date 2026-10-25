<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\Validation;
use App\Support\MapFilters;
use App\Support\MapScale;
use App\Support\TechnicianMapData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Maps and Visualization, scoped to one technician.
 *
 * The MAO map (mao/map/index.blade.php) shows crop damage across the whole
 * municipality, aggregated by association. This is deliberately not a copy
 * of that. It is a technician's own working map: only the reports assigned
 * to them, so they can see where their own farms sit relative to each other
 * while planning field visits. No association bubbles, no heatmap, no
 * municipality-wide filters - proposal section 2 is clear that the map
 * should stay simple, and a personal tool that shows work belonging to
 * other technicians would stop being personal.
 *
 * Filters and display options follow the shared definitions in
 * App\Support\MapFilters and the x-map components. The bubbles, the verified
 * pins, the heatmap and the summary cards are all built from the same
 * filtered list of reports.
 *
 * Verified pins: the Technician-Verified Location. Farmer damage reports no
 * longer collect GPS coordinates of their own (barangay + a written
 * description only), so a report only gets an exact pin once this
 * technician has actually visited and verified it - there is nothing to
 * plot before that.
 */
class MapController extends Controller
{
    /** Roughly the centre of Tanza, used before any pins are plotted. */
    public const CENTER = ['lat' => 14.3833, 'lng' => 120.8500, 'zoom' => 12];

    public function index(Request $request)
    {
        $technicianId = Auth::id();
        $shading      = MapScale::shading($request->query('shading'));

        // Date range, disaster, severity, validation status. Always limited
        // to this technician's own assignments first; the filters can only
        // narrow that, never widen it.
        $filters = MapFilters::from($request, 'technician');

        $reports = MapFilters::apply(
            DamageReport::active()->where('assigned_technician_id', $technicianId),
            $filters
        )->with(['farmer.barangay', 'reportedBarangay', 'validation'])->get();

        $verifiedPins = $reports
            ->filter(fn ($report) => $report->validation?->latitude !== null)
            ->map(fn ($report) => [
                'lat'      => (float) $report->validation->latitude,
                'lng'      => (float) $report->validation->longitude,
                'report'   => $report->full_reference,
                'farmer'   => $report->farmer?->full_name ?? 'Unknown',
                'barangay' => $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Unknown barangay',
                'severity' => Validation::severityLabel($report->validation->severity) ?? 'Not yet assessed',
                'color'    => $report->validation->severity
                    ? $report->validation->severity_color
                    : '#94a3b8',
                'assessed' => $report->validation->assessed_damage_percent,
                'url'      => route('technician.reports.show', $report),
            ])
            ->values();

        $mapBubbles = TechnicianMapData::bubbles($technicianId, $reports->pluck('id'));

        return view('technician.map.index', [
            'mapBubbles'   => $mapBubbles,
            'verifiedPins' => $verifiedPins,
            'center'       => self::CENTER,
            'filters'      => $filters,
            'shading'      => $shading,
            'legends'      => MapScale::legends($mapBubbles),
            'coverage'     => [
                'reports'  => $reports->count(),
                'verified' => $reports->filter(fn ($r) => in_array($r->status, ['verified', 'approved'], true))->count(),
                'pinned'   => $verifiedPins->count(),
            ],
        ]);
    }
}
