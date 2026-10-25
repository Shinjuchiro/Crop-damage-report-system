<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\Farmer;
use App\Models\Validation;
use App\Support\MapFilters;
use App\Support\MapScale;
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
 * either.
 *
 * One pin layer: the Technician-Verified Location. Farmer damage reports no
 * longer collect GPS coordinates of their own (barangay + a written
 * description only), so a report only gets an exact pin once a technician
 * has actually visited and verified it - there is nothing to plot before
 * that. Barangay/association-level information is unaffected by this: it
 * never depended on farmer GPS in the first place.
 *
 * An association can span more than one barangay, so the circles are one
 * per governed barangay (coloured by report count or worst severity), with
 * the verified pins and a heatmap as optional layers. Filters are Date
 * Range, Disaster and Severity; every layer and the cards are built from
 * the same filtered list of reports.
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

        /* THE BARANGAYS THIS OFFICER GOVERNS

           The map is limited to the association's own coverage (the
           barangays it works in), with its office barangay as the fallback
           when no coverage has been recorded yet. Pins outside those
           barangays are not plotted and other barangays are not drawn. */
        $governed = $association->coverage()->get();
        if ($governed->isEmpty() && $association->barangay) {
            $governed = collect([$association->barangay]);
        }
        $governedIds = $governed->pluck('id');

        /* active() skips reports the office has archived or deleted. The
           lists dropped them in Oct 2026 (section 16) and the map has to
           agree: a pin for a report that is no longer on the officer's own
           list is a pin they cannot click through to or act on.

           Filters (Date Range, Disaster, Severity) come from the shared
           App\Support\MapFilters. There is deliberately no association
           filter: the query is already limited to this officer's own members
           above, and MapFilters ignores association_id for this role. */
        $filters = MapFilters::from($request, 'association');
        $shading = MapScale::shading($request->query('shading'));

        $reports = MapFilters::apply(
            DamageReport::active()
                ->whereIn('farmer_id', $memberIds)
                ->when($governedIds->isNotEmpty(), fn ($q) => $q->where(function ($query) use ($governedIds) {
                    $query->whereIn('reported_barangay_id', $governedIds)
                          ->orWhere(fn ($sub) => $sub->whereNull('reported_barangay_id')
                              ->whereHas('farmer', fn ($f) => $f->whereIn('barangay_id', $governedIds)));
                })),
            $filters
        )->with(['farmer.barangay', 'reportedBarangay', 'validation'])->get();

        /* One bubble per governed barangay that has reports. A report counts
           for the barangay it was filed in, falling back to the farmer's own
           when the report does not name one (same rule as the query above). */
        $bubbles = $governed->map(function ($barangay) use ($reports) {
            $rows = $reports->filter(fn ($r) => (int) ($r->reported_barangay_id ?? $r->farmer?->barangay_id) === (int) $barangay->id);
            if ($rows->isEmpty()) {
                return null;
            }

            $count = $rows->count();
            $worst = MapScale::worstSeverity($rows->map(fn ($r) => $r->validation?->severity));

            return [
                'id'       => $barangay->id,
                'name'     => $barangay->name,
                'psgc'     => $barangay->psgc_code,
                'total'    => $count,
                'reports'  => $count,
                'farmers'  => $rows->pluck('farmer_id')->unique()->count(),
                'awaiting' => $rows->whereIn('status', ['pending', 'assigned'])->count(),
                'underway' => $rows->where('status', 'under_verification')->count(),
                'verified' => $rows->whereIn('status', ['verified', 'approved'])->count(),
                'url'      => route('association.reports.index'),
            ] + MapScale::bubbleColours($count, $worst);
        })->filter()->values();

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
                'url'      => route('association.reports.index', ['q' => $report->farmer?->last_name]),
            ])
            ->values();

        return view('association.map.index', [
            'association'  => $association,
            'verifiedPins' => $verifiedPins,
            'bubbles'      => $bubbles,
            'center'       => self::CENTER,
            'filters'      => $filters,
            'shading'      => $shading,
            'legends'      => MapScale::legends($bubbles),
            'governed'     => $governed->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'psgc' => $b->psgc_code])->values(),
            'coverage'     => [
                'reports'   => $reports->count(),
                'farmers'   => $reports->pluck('farmer_id')->unique()->count(),
                'pinned'    => $verifiedPins->count(),
                'barangays' => $bubbles->count(),
            ],
        ]);
    }
}
