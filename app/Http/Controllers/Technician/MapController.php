<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\Validation;
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
 * Two pins per report where both exist (section 49): where the farmer said
 * the damage was, and where this technician actually verified it once they
 * stood on the ground. The farmer's pin is never overwritten, so the two
 * can legitimately sit apart.
 */
class MapController extends Controller
{
    /** Roughly the centre of Tanza, used before any pins are plotted. */
    public const CENTER = ['lat' => 14.3833, 'lng' => 120.8500, 'zoom' => 12];

    public function index(Request $request)
    {
        $technicianId = Auth::id();

        $reports = DamageReport::where('assigned_technician_id', $technicianId)
            ->where(function ($query) {
                $query->whereNotNull('reported_latitude')
                      ->orWhereHas('validation', fn ($v) => $v->whereNotNull('latitude'));
            })
            ->with(['farmer.barangay', 'reportedBarangay', 'crops.crop', 'validation'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->get();

        $farmerPins = $reports
            ->filter(fn ($report) => $report->has_reported_coordinates)
            ->map(fn ($report) => [
                'lat'      => (float) $report->reported_latitude,
                'lng'      => (float) $report->reported_longitude,
                'report'   => $report->reference,
                'farmer'   => $report->farmer?->full_name ?? 'Unknown',
                'barangay' => $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Unknown barangay',
                'crops'    => $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->filter()->join(', ') ?: 'Not specified',
                'status'   => DamageReport::STATUSES[$report->status] ?? $report->status,
                'url'      => route('technician.reports.show', $report),
            ])
            ->values();

        $verifiedPins = $reports
            ->filter(fn ($report) => $report->validation?->latitude !== null)
            ->map(fn ($report) => [
                'lat'      => (float) $report->validation->latitude,
                'lng'      => (float) $report->validation->longitude,
                'report'   => $report->reference,
                'farmer'   => $report->farmer?->full_name ?? 'Unknown',
                'barangay' => $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Unknown barangay',
                'severity' => $report->validation->severity
                    ? Validation::SEVERITY_SCALE[$report->validation->severity]['label']
                    : 'Not yet assessed',
                'color'    => $report->validation->severity
                    ? $report->validation->severity_color
                    : '#94a3b8',
                'assessed' => $report->validation->assessed_damage_percent,
                'url'      => route('technician.reports.show', $report),
            ])
            ->values();

        return view('technician.map.index', [
            'farmerPins'   => $farmerPins,
            'verifiedPins' => $verifiedPins,
            'center'       => self::CENTER,
            'statuses'     => DamageReport::STATUSES,
            'filters'      => $request->only(['status']),
            'coverage'     => [
                'total'    => $reports->count(),
                'farmer'   => $farmerPins->count(),
                'verified' => $verifiedPins->count(),
            ],
        ]);
    }
}
