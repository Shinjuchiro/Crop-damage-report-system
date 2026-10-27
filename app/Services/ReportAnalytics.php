<?php

namespace App\Services;

use App\Models\DamageReport;
use Illuminate\Support\Facades\DB;

/**
 * The cards and charts on Reports & Analytics, for one year. Real counts from
 * the database, nothing typed in.
 *
 * Pass an association id to limit every figure to that association's own
 * members (the officer's view); null is the whole municipality (the
 * office's).
 *
 * WHAT IS COUNTED (Oct 2026 revision), kept apart on purpose
 *
 *   farmers         people. "Designated" and "confirmed receiving" are counts
 *                   of distinct farmers, never of rows.
 *   reports         individual planting records and damage reports.
 *   allocations     assistance allocation batches (one row in
 *                   assistance_allocations; a batch names many farmers).
 *   distributions   distribution batches: a batch counts once however many
 *                   of its farmers were handed assistance.
 *
 * Which date decides the month:
 *   planting records   the date the record was filed (date_submitted)
 *   damage reports     the date the report was filed (submitted_at, falling
 *                      back to created_at on older rows)
 *   allocations        allocated_at
 *   distributions      distributed_at
 *   confirmed receipt  receipt_confirmed_at
 *   validated          validated_at (when the technician submitted)
 */
class ReportAnalytics
{
    public static function forYear(int $year, ?int $associationId = null): array
    {
        $members = fn ($q, string $farmerColumn) => $associationId
            ? $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('farmers as f')
                ->whereColumn('f.id', $farmerColumn)->where('f.association_id', $associationId))
            : $q;

        $perMonth = function ($query, string $dateExpr, string $count = 'COUNT(*)') {
            $rows = $query->selectRaw("MONTH($dateExpr) as m, $count as total")
                ->groupBy('m')->pluck('total', 'm');

            return collect(range(1, 12))->map(fn ($m) => (int) ($rows[$m] ?? 0))->all();
        };

        $filedAt = 'COALESCE(d.submitted_at, d.created_at)';

        $damageBase = fn () => DB::table('damage_reports as d')
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->whereRaw("YEAR($filedAt) = ?", [$year])
            ->tap(fn ($q) => $members($q, 'd.farmer_id'));

        /* ---------- planting and damage, by month ---------- */
        $planting = $perMonth(
            DB::table('crop_planting_records as r')->whereNull('r.deleted_at')->whereNull('r.archived_at')
                ->whereYear('r.date_submitted', $year)
                ->tap(fn ($q) => $members($q, 'r.farmer_id')),
            'r.date_submitted'
        );

        $damage = $perMonth($damageBase(), $filedAt);

        /* ---------- assistance ---------- */
        $allocationsBase = fn () => DB::table('assistance_allocations as a')
            ->where('a.status', '!=', 'cancelled')
            ->whereYear('a.allocated_at', $year)
            ->when($associationId, fn ($q) => $q->whereExists(fn ($s) => $s->select(DB::raw(1))
                ->from('assistance_allocation_beneficiaries as b')
                ->join('farmers as f', 'f.id', '=', 'b.farmer_id')
                ->whereColumn('b.assistance_allocation_id', 'a.id')
                ->where('f.association_id', $associationId)));

        $allocated = $perMonth($allocationsBase(), 'a.allocated_at');

        // Distribution BATCHES: one allocation counts once however many of its
        // farmers were handed something.
        $distributionsBase = fn () => DB::table('assistance_distributions as x')
            ->whereYear('x.distributed_at', $year)
            ->tap(fn ($q) => $members($q, 'x.farmer_id'));

        $distributed = $perMonth($distributionsBase(), 'x.distributed_at', 'COUNT(DISTINCT x.assistance_allocation_id)');

        $distributionBatches = (int) $distributionsBase()->distinct()->count('x.assistance_allocation_id');

        // FARMERS designated for assistance, and FARMERS who confirmed receiving
        // it, by month. People, not rows.
        $designatedByMonth = $perMonth(
            DB::table('assistance_allocation_beneficiaries as b')
                ->join('assistance_allocations as a', 'a.id', '=', 'b.assistance_allocation_id')
                ->where('a.status', '!=', 'cancelled')
                ->whereYear('a.allocated_at', $year)
                ->tap(fn ($q) => $members($q, 'b.farmer_id')),
            'a.allocated_at',
            'COUNT(DISTINCT b.farmer_id)'
        );

        $confirmedByMonth = $perMonth(
            DB::table('assistance_distributions as x')
                ->where('x.receipt_status', 'confirmed_received')
                ->whereYear('x.receipt_confirmed_at', $year)
                ->tap(fn ($q) => $members($q, 'x.farmer_id')),
            'x.receipt_confirmed_at',
            'COUNT(DISTINCT x.farmer_id)'
        );

        /* ---------- damage reports by crop and by cause ---------- */
        $byCrop = DB::table('damage_report_crops as c')
            ->join('damage_reports as d', 'd.id', '=', 'c.damage_report_id')
            ->leftJoin('crops as cr', 'cr.id', '=', 'c.crop_id')
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->whereRaw("YEAR($filedAt) = ?", [$year])
            ->tap(fn ($q) => $members($q, 'd.farmer_id'))
            ->selectRaw("COALESCE(cr.name, 'Other') as label, COUNT(DISTINCT d.id) as total")
            ->groupBy('label')->orderByDesc('total')->limit(6)->pluck('total', 'label');

        $byCause = $damageBase()
            ->selectRaw('d.damage_cause as label, COUNT(*) as total')
            ->groupBy('label')->orderByDesc('total')->pluck('total', 'label')
            ->mapWithKeys(fn ($total, $cause) => [
                DamageReport::CAUSES[$cause] ?? ucwords(str_replace('_', ' ', (string) $cause)) => $total,
            ]);

        /* ---------- the association's own extras ---------- */
        $extras = [];

        if ($associationId) {
            $memberIds = fn () => DB::table('farmers as f')
                ->join('users as u', 'u.id', '=', 'f.user_id')
                ->whereNull('f.deleted_at')
                ->where('u.status', 'active')
                ->where('f.association_id', $associationId);

            $verified = DB::table('validations as v')
                ->join('damage_reports as d', 'd.id', '=', 'v.damage_report_id')
                ->whereNull('d.deleted_at')->whereNull('d.archived_at')
                ->whereNotNull('v.validated_at')
                ->whereYear('v.validated_at', $year)
                ->tap(fn ($q) => $members($q, 'd.farmer_id'))
                ->count();

            // The reports' real statuses, as they stand now, for reports filed
            // in the year. Only statuses that actually occur are listed.
            $statusCounts = $damageBase()
                ->selectRaw('d.status as label, COUNT(*) as total')
                ->groupBy('label')->pluck('total', 'label');

            $byStatus = collect(DamageReport::STATUSES)
                ->map(fn ($label, $key) => ['label' => $label, 'total' => (int) ($statusCounts[$key] ?? 0)])
                ->filter(fn ($row) => $row['total'] > 0)
                ->values();

            // Members designated in the year: did they confirm receiving it?
            $designated = DB::table('assistance_allocation_beneficiaries as b')
                ->join('assistance_allocations as a', 'a.id', '=', 'b.assistance_allocation_id')
                ->where('a.status', '!=', 'cancelled')
                ->whereYear('a.allocated_at', $year)
                ->tap(fn ($q) => $members($q, 'b.farmer_id'));

            $designatedCount = (int) (clone $designated)->distinct()->count('b.farmer_id');

            $receivedCount = (int) (clone $designated)
                ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('assistance_distributions as x')
                    ->whereColumn('x.assistance_allocation_id', 'b.assistance_allocation_id')
                    ->whereColumn('x.farmer_id', 'b.farmer_id')
                    ->where('x.receipt_status', 'confirmed_received'))
                ->distinct()->count('b.farmer_id');

            $extras = [
                'total_members' => (int) $memberIds()->count(),
                'verified'      => $verified,
                'by_status'     => $byStatus,
                'receipt'       => [
                    'received'     => $receivedCount,
                    'not_received' => max(0, $designatedCount - $receivedCount),
                    'designated'   => $designatedCount,
                ],
            ];
        }

        return [
            'year'        => $year,
            'months'      => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'planting'    => $planting,
            'damage'      => $damage,
            'allocated'   => $allocated,
            'distributed' => $distributed,
            'designated'  => $designatedByMonth,
            'confirmed'   => $confirmedByMonth,
            'by_crop'     => $byCrop,
            'by_cause'    => $byCause,
            'totals'      => [
                'planting'    => array_sum($planting),
                'damage'      => array_sum($damage),
                'allocated'   => (int) $allocationsBase()->count(),
                'distributed' => $distributionBatches,
            ],
        ] + $extras;
    }
}
