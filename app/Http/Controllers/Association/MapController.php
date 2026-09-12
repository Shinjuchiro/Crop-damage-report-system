<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\Farmer;
use App\Models\Validation;
use Illuminate\Http\Request;

/**
 * Maps and Visualization, scoped to one Farmers' Association.
 *
 * The MAO map (mao/map/index.blade.php) aggregates damage by association,
 * drawing each one as a single bubble at its office barangay - that view
 * would reduce this very association to one dot on a municipality-wide map.
 * This is deliberately not that. It is the association's own working map:
 * only its own members' reports, plotted as individual pins, the same way
 * the Technician's personal map only ever shows that technician's own
 * assignments. Proposal section 2 keeps the map simple - no heatmap here
 * either, just the two pin layers section 49 calls for.
 *
 * An association can span more than one barangay (its members are not all
 * from a single one), so - unlike the Technician's map - this adds a
 * barangay filter alongside status.
 *
 * There is no single-report show page in the Association module (proposal
 * section 61 only asks for a farmer-detail view), so a pin's "view report"
 * link opens the existing Damage Reports monitoring list pre-filtered to
 * that farmer, rather than a page that does not exist.
 */
class MapController extends Controller
{
    use ResolvesAssociation;

    /** Roughly the centre of Tanza, used before any pins are plotted. */
    public const CENTER = ['lat' => 14.3833, 'lng' => 120.8500, 'zoom' => 12];

    public function index(Request $request)
    {
        $association = $this->association();

        $memberIds = Farmer::where('association_id', $association->id)->select('id');

        $reports = DamageReport::whereIn('farmer_id', $memberIds)
            ->where(function ($query) {
                $query->whereNotNull('reported_latitude')
                      ->orWhereHas('validation', fn ($v) => $v->whereNotNull('latitude'));
            })
            ->with(['farmer.barangay', 'reportedBarangay', 'crops.crop', 'validation'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('barangay_id'), function ($q, $barangayId) {
                $q->where(function ($query) use ($barangayId) {
                    $query->where('reported_barangay_id', $barangayId)
                          ->orWhereHas('farmer', fn ($f) => $f->where('barangay_id', $barangayId));
                });
            })
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
                'url'      => route('association.reports.index', ['q' => $report->farmer?->last_name]),
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
                'url'      => route('association.reports.index', ['q' => $report->farmer?->last_name]),
            ])
            ->values();

        // Only the barangays this association's own members actually live or
        // report in, not every barangay in Tanza - the filter should only
        // ever offer choices that can change what's on the map.
        $barangayIds = Farmer::where('association_id', $association->id)->pluck('barangay_id')
            ->merge(DamageReport::whereIn('farmer_id', $memberIds)->pluck('reported_barangay_id'))
            ->filter()
            ->unique();

        return view('association.map.index', [
            'association'  => $association,
            'farmerPins'   => $farmerPins,
            'verifiedPins' => $verifiedPins,
            'center'       => self::CENTER,
            'statuses'     => DamageReport::STATUSES,
            'barangays'    => Barangay::whereIn('id', $barangayIds)->orderBy('name')->get(),
            'filters'      => $request->only(['status', 'barangay_id']),
            'coverage'     => [
                'total'    => $reports->count(),
                'farmer'   => $farmerPins->count(),
                'verified' => $verifiedPins->count(),
            ],
        ]);
    }
}
