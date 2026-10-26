<?php

namespace App\Support;

use App\Models\CropPlantingRecord;
use App\Models\DamageReport;
use App\Models\Farmer;
use Illuminate\Support\Facades\DB;

/**
 * The eight summary figures for Crop Planted, Crop Damage and Validation
 * Monitoring (Oct 2026).
 *
 * ONE DEFINITION, TWO AUDIENCES
 *
 * The MAO dashboard calls this for the whole municipality; the association's
 * pages call it with their own association id, so the same figure means the
 * same thing on both and an association can only ever be handed numbers about
 * its own members. Nothing is stored or hardcoded: every value is a live
 * query, so it moves the moment a record or a status changes.
 *
 * THE RULES EACH FIGURE FOLLOWS
 *
 * - Archived and deleted records are left out everywhere, exactly like the
 *   lists these figures sit above, so a card never disagrees with its table.
 * - Total Crop Planted counts planting REPORTS (one per submission), not the
 *   crops inside them; a submission with rice and corn is one report.
 * - Total Area Planted adds the area of each planted crop row once. A row is
 *   one crop on one planting report, so nothing is counted twice.
 * - Total Assigned Reports counts reports that have a technician assigned
 *   right now (the current assignment on the report), once each. It is not
 *   built from history, so a report reassigned three times is still one.
 * - Total Area Damaged adds the reported damaged area of each damaged crop
 *   row once. It is the area the farmers reported, not derived from planted
 *   area.
 * - Completed Inspection counts reports whose technician has SUBMITTED the
 *   inspection (validated_at is set). Assigned, waiting or in-progress
 *   inspections are not completed.
 * - Approved and Rejected count the office's actual review decision on a
 *   submitted assessment. A technician finishing an inspection is neither.
 */
class MonitorStats
{
    public static function all(?int $associationId = null): array
    {
        return [
            'planting'   => self::planting($associationId),
            'damage'     => self::damage($associationId),
            'validation' => self::validation($associationId),
        ];
    }

    /** @return array{total: int, area: float} */
    public static function planting(?int $associationId = null): array
    {
        $records = CropPlantingRecord::query()
            ->active()
            ->when($associationId, fn ($q) => $q->whereIn('farmer_id', self::memberIds($associationId)));

        $area = DB::table('crop_planting_record_crops as c')
            ->join('crop_planting_records as r', 'r.id', '=', 'c.crop_planting_record_id')
            ->whereNull('r.archived_at')
            ->whereNull('r.deleted_at')
            ->when($associationId, fn ($q) => $q->whereIn('r.farmer_id', self::memberIds($associationId)))
            ->sum('c.area_hectares');

        return [
            'total' => (int) $records->count(),
            'area'  => (float) $area,
        ];
    }

    /** @return array{total: int, assigned: int, area: float} */
    public static function damage(?int $associationId = null): array
    {
        $reports = fn () => DamageReport::query()
            ->active()
            ->when($associationId, fn ($q) => $q->whereIn('farmer_id', self::memberIds($associationId)));

        $area = DB::table('damage_report_crops as c')
            ->join('damage_reports as d', 'd.id', '=', 'c.damage_report_id')
            ->whereNull('d.archived_at')
            ->whereNull('d.deleted_at')
            ->when($associationId, fn ($q) => $q->whereIn('d.farmer_id', self::memberIds($associationId)))
            ->sum('c.damaged_area_hectares');

        return [
            'total'    => (int) $reports()->count(),
            'assigned' => (int) $reports()->whereNotNull('assigned_technician_id')->count(),
            'area'     => (float) $area,
        ];
    }

    /** @return array{completed: int, approved: int, rejected: int, pending: int} */
    public static function validation(?int $associationId = null): array
    {
        $completed = fn () => DamageReport::query()
            ->active()
            ->when($associationId, fn ($q) => $q->whereIn('farmer_id', self::memberIds($associationId)))
            ->whereHas('validation', fn ($v) => $v->whereNotNull('validated_at'));

        return [
            'completed' => (int) $completed()->count(),
            'approved'  => (int) $completed()->where('status', 'approved')->count(),
            'rejected'  => (int) $completed()->where('status', 'rejected')->count(),
            'pending'   => (int) $completed()->whereIn('status', DamageReport::PENDING_REVIEW)->count(),
        ];
    }

    private static function memberIds(int $associationId)
    {
        return Farmer::query()->where('association_id', $associationId)->select('id');
    }
}
