<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The charts on Reports & Analytics: planting, damage and assistance over
 * one year. Real counts from the database, nothing typed in.
 *
 * Pass an association id to limit every figure to that association's own
 * members (the officer's view); null is the whole municipality (the
 * office's).
 */
class ReportAnalytics
{
    public static function forYear(int $year, ?int $associationId = null): array
    {
        $members = fn ($q, string $farmerColumn) => $associationId
            ? $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('farmers as f')
                ->whereColumn('f.id', $farmerColumn)->where('f.association_id', $associationId))
            : $q;

        $perMonth = function ($query, string $dateColumn) {
            $rows = $query->selectRaw("MONTH($dateColumn) as m, COUNT(*) as total")
                ->groupBy('m')->pluck('total', 'm');

            return collect(range(1, 12))->map(fn ($m) => (int) ($rows[$m] ?? 0))->all();
        };

        $planting = $perMonth(
            DB::table('crop_planting_records as r')->whereNull('r.deleted_at')->whereNull('r.archived_at')
                ->whereYear('r.date_submitted', $year)
                ->tap(fn ($q) => $members($q, 'r.farmer_id')),
            'r.date_submitted'
        );

        $damage = $perMonth(
            DB::table('damage_reports as d')->whereNull('d.deleted_at')->whereNull('d.archived_at')
                ->whereYear('d.created_at', $year)
                ->tap(fn ($q) => $members($q, 'd.farmer_id')),
            'd.created_at'
        );

        $allocated = $perMonth(
            DB::table('assistance_allocations as a')->whereYear('a.allocated_at', $year)
                ->when($associationId, fn ($q) => $q->whereExists(fn ($s) => $s->select(DB::raw(1))
                    ->from('assistance_allocation_beneficiaries as b')
                    ->join('farmers as f', 'f.id', '=', 'b.farmer_id')
                    ->whereColumn('b.assistance_allocation_id', 'a.id')
                    ->where('f.association_id', $associationId))),
            'a.allocated_at'
        );

        $distributed = $perMonth(
            DB::table('assistance_distributions as x')->whereYear('x.distributed_at', $year)
                ->tap(fn ($q) => $members($q, 'x.farmer_id')),
            'x.distributed_at'
        );

        $byCrop = DB::table('damage_report_crops as c')
            ->join('damage_reports as d', 'd.id', '=', 'c.damage_report_id')
            ->leftJoin('crops as cr', 'cr.id', '=', 'c.crop_id')
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->whereYear('d.created_at', $year)
            ->tap(fn ($q) => $members($q, 'd.farmer_id'))
            ->selectRaw("COALESCE(cr.name, 'Other') as label, COUNT(DISTINCT d.id) as total")
            ->groupBy('label')->orderByDesc('total')->limit(6)->pluck('total', 'label');

        $byCause = DB::table('damage_reports as d')
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->whereYear('d.created_at', $year)
            ->tap(fn ($q) => $members($q, 'd.farmer_id'))
            ->selectRaw('d.damage_cause as label, COUNT(*) as total')
            ->groupBy('label')->orderByDesc('total')->pluck('total', 'label')
            ->mapWithKeys(fn ($total, $cause) => [
                \App\Models\DamageReport::CAUSES[$cause] ?? ucwords(str_replace('_', ' ', (string) $cause)) => $total,
            ]);

        return [
            'year'        => $year,
            'months'      => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'planting'    => $planting,
            'damage'      => $damage,
            'allocated'   => $allocated,
            'distributed' => $distributed,
            'by_crop'     => $byCrop,
            'by_cause'    => $byCause,
            'totals'      => [
                'planting'    => array_sum($planting),
                'damage'      => array_sum($damage),
                'allocated'   => array_sum($allocated),
                'distributed' => array_sum($distributed),
            ],
        ];
    }
}
