<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Latest 3 per farmer" for the grouped Crop Management lists.
 *
 * A farmer who files often would otherwise fill whole pages of a grouped
 * list. The list keeps each farmer's newest few records and the farmer's
 * full history is one tap away in the record's View panel.
 *
 * The limit only applies to the plain grouped list. As soon as the office
 * searches or filters, or switches to the flat list, every match is shown,
 * because hiding older matches would make a filter lie.
 */
class LatestPerFarmer
{
    public const LIMIT = 3;

    public static function applies(Request $request, string $view): bool
    {
        return $view === 'grouped'
            && ! $request->hasAny(['search', 'q', 'status', 'disaster_id', 'technician_id', 'association_id',
                'barangay_id', 'crop_id', 'activity_status', 'month', 'year', 'severity']);
    }

    /** Damage reports (and validations, which are damage reports that were inspected). */
    public static function damageReports($query, bool $inspectedOnly = false)
    {
        $extra = $inspectedOnly
            ? ' AND EXISTS (SELECT 1 FROM validations v2 WHERE v2.damage_report_id = d2.id AND v2.validated_at IS NOT NULL)'
            : '';

        return $query->whereRaw(
            '(SELECT COUNT(*) FROM damage_reports d2
               WHERE d2.farmer_id = damage_reports.farmer_id
                 AND d2.deleted_at IS NULL AND d2.archived_at IS NULL' . $extra . '
                 AND (d2.created_at > damage_reports.created_at
                      OR (d2.created_at = damage_reports.created_at AND d2.id > damage_reports.id))) < ?',
            [self::LIMIT]
        );
    }

    /** Planting lines (one row per planted crop, as the list shows them). */
    public static function plantings($query)
    {
        return $query->whereRaw(
            '(SELECT COUNT(*) FROM crop_planting_record_crops c2
               JOIN crop_planting_records r2 ON r2.id = c2.crop_planting_record_id
               JOIN crop_planting_records r1 ON r1.id = crop_planting_record_crops.crop_planting_record_id
              WHERE r2.farmer_id = r1.farmer_id
                AND r2.deleted_at IS NULL AND r2.archived_at IS NULL
                AND (r2.date_submitted > r1.date_submitted
                     OR (r2.date_submitted = r1.date_submitted AND c2.id > crop_planting_record_crops.id))) < ?',
            [self::LIMIT]
        );
    }

    /** farmer_id => how many records they have in all (for "3 of 7"). */
    public static function damageTotals(bool $inspectedOnly = false)
    {
        return DB::table('damage_reports as d')
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->when($inspectedOnly, fn ($q) => $q->whereExists(fn ($s) => $s->select(DB::raw(1))
                ->from('validations as v')->whereColumn('v.damage_report_id', 'd.id')->whereNotNull('v.validated_at')))
            ->select('d.farmer_id', DB::raw('COUNT(*) as total'))
            ->groupBy('d.farmer_id')
            ->pluck('total', 'farmer_id');
    }

    public static function plantingTotals()
    {
        return DB::table('crop_planting_record_crops as c')
            ->join('crop_planting_records as r', 'r.id', '=', 'c.crop_planting_record_id')
            ->whereNull('r.deleted_at')->whereNull('r.archived_at')
            ->select('r.farmer_id', DB::raw('COUNT(*) as total'))
            ->groupBy('r.farmer_id')
            ->pluck('total', 'farmer_id');
    }
}
