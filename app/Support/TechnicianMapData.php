<?php

namespace App\Support;

use App\Models\Association;
use Illuminate\Support\Facades\DB;

/**
 * One bubble per association for a technician's own assignments.
 *
 * Same idea as the office's map (a numbered circle per association, a dark
 * popup with a breakdown), but counting only the reports assigned to this
 * technician:
 *
 *   awaiting   assigned, inspection not started yet
 *   underway   inspection started, not submitted
 *   verified   inspection submitted (verified or approved)
 *
 * The total is every assignment from that association's members and does
 * not change as the technician works through them; only the split moves.
 */
class TechnicianMapData
{
    /**
     * @param  iterable|null  $reportIds  When given, only these reports are
     *         counted: the map page passes the ids left after its filters so
     *         the bubbles agree with the pins and the heatmap. The dashboard
     *         passes nothing and gets every assignment.
     */
    public static function bubbles(int $technicianId, $reportIds = null)
    {
        $rows = DB::table('damage_reports as d')
            ->join('farmers as f', 'f.id', '=', 'd.farmer_id')
            ->leftJoin('validations as v', 'v.damage_report_id', '=', 'd.id')
            ->where('d.assigned_technician_id', $technicianId)
            ->when($reportIds !== null, fn ($q) => $q->whereIn('d.id', collect($reportIds)->all()))
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->whereNotNull('f.association_id')
            ->select(
                'f.association_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(d.status = 'assigned') as awaiting"),
                DB::raw("SUM(d.status = 'under_verification') as underway"),
                DB::raw("SUM(d.status IN ('verified', 'approved')) as verified"),
                DB::raw("MAX(FIELD(v.severity, 'slight', 'moderate', 'partial', 'total')) as severity_rank")
            )
            ->groupBy('f.association_id')
            ->get()
            ->keyBy('association_id');

        return Association::with('barangay')
            ->whereIn('id', $rows->keys())
            ->orderBy('name')
            ->get()
            ->map(function (Association $a) use ($rows) {
                $row  = $rows[$a->id];
                $rank = (int) ($row->severity_rank ?? 0);
                $key  = $rank > 0 ? MapScale::SEVERITY_ORDER[$rank - 1] : null;

                return [
                    'id'       => $a->id,
                    'name'     => $a->name,
                    'psgc'     => $a->barangay?->psgc_code,
                    'total'    => (int) $row->total,
                    'reports'  => (int) $row->total,
                    'awaiting' => (int) $row->awaiting,
                    'underway' => (int) $row->underway,
                    'verified' => (int) $row->verified,
                    'url'      => route('technician.reports.index', ['association_id' => $a->id]),
                ] + MapScale::bubbleColours((int) $row->total, $key);
            })
            ->values();
    }
}
