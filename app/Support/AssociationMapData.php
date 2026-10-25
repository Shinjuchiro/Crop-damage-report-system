<?php

namespace App\Support;

use App\Models\DamageReport;
use App\Models\Farmer;
use App\Models\Validation;

/**
 * The data for the Association dashboard's map preview: circles (one per barangay
 * the association governs), verified pins and governed barangays. Same
 * rules as Association\MapController (governed barangays, falling back to
 * the office barangay; a report counts for the barangay it was filed in,
 * else the farmer's own), with no filters applied.
 */
class AssociationMapData
{
    public static function build($association): array
    {
        $governed = $association->coverage()->get();
        if ($governed->isEmpty() && $association->barangay) {
            $governed = collect([$association->barangay]);
        }
        $governedIds = $governed->pluck('id');

        $reports = DamageReport::active()
            ->whereIn('farmer_id', Farmer::where('association_id', $association->id)->select('id'))
            ->when($governedIds->isNotEmpty(), fn ($q) => $q->where(function ($query) use ($governedIds) {
                $query->whereIn('reported_barangay_id', $governedIds)
                      ->orWhere(fn ($sub) => $sub->whereNull('reported_barangay_id')
                          ->whereHas('farmer', fn ($f) => $f->whereIn('barangay_id', $governedIds)));
            }))
            ->with(['farmer.barangay', 'reportedBarangay', 'validation'])
            ->get();

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

        $pins = $reports
            ->filter(fn ($report) => $report->validation?->latitude !== null)
            ->map(fn ($report) => [
                'lat'      => (float) $report->validation->latitude,
                'lng'      => (float) $report->validation->longitude,
                'report'   => $report->full_reference,
                'farmer'   => $report->farmer?->full_name ?? 'Unknown',
                'barangay' => $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Unknown barangay',
                'severity' => Validation::severityLabel($report->validation->severity) ?? 'Not yet assessed',
                'color'    => $report->validation->severity ? $report->validation->severity_color : '#94a3b8',
                'assessed' => $report->validation->assessed_damage_percent,
                'url'      => route('association.reports.index', ['q' => $report->farmer?->last_name]),
            ])
            ->values();

        return [
            'bubbles'  => $bubbles,
            'pins'     => $pins,
            'governed' => $governed->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'psgc' => $b->psgc_code])->values(),
        ];
    }
}
