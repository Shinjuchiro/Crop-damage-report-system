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
    public static function bubbles(int $technicianId)
    {
        $rows = DB::table('damage_reports as d')
            ->join('farmers as f', 'f.id', '=', 'd.farmer_id')
            ->where('d.assigned_technician_id', $technicianId)
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->whereNotNull('f.association_id')
            ->select(
                'f.association_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(d.status = 'assigned') as awaiting"),
                DB::raw("SUM(d.status = 'under_verification') as underway"),
                DB::raw("SUM(d.status IN ('verified', 'approved')) as verified")
            )
            ->groupBy('f.association_id')
            ->get()
            ->keyBy('association_id');

        return Association::with('barangay')
            ->whereIn('id', $rows->keys())
            ->orderBy('name')
            ->get()
            ->map(fn (Association $a) => [
                'id'       => $a->id,
                'name'     => $a->name,
                'psgc'     => $a->barangay?->psgc_code,
                'total'    => (int) $rows[$a->id]->total,
                'awaiting' => (int) $rows[$a->id]->awaiting,
                'underway' => (int) $rows[$a->id]->underway,
                'verified' => (int) $rows[$a->id]->verified,
                'url'      => route('technician.reports.index', ['association_id' => $a->id]),
            ])
            ->values();
    }
}
