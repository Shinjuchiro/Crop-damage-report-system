<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\AssistanceAllocation;
use App\Models\AssistanceDistribution;
use App\Models\DamageReport;
use App\Models\Farmer;
use Illuminate\Support\Facades\DB;

/**
 * Farmers' Association Dashboard  (proposal section 60)
 *
 * What an officer needs to know when they open the system:
 * how many members they have, how many of them were hit, and what
 * assistance is sitting with the association still waiting to be handed out.
 *
 * That last number is the one that matters most. Assistance goes
 * MAO -> Association -> Farmer, so anything allocated but not yet
 * distributed is aid that has arrived and not reached anybody.
 *
 * Every figure here is a query. Nothing is stored or hardcoded, including
 * the member count (see BUILD-STATUS decision 5).
 */
class DashboardController extends Controller
{
    use ResolvesAssociation;

    public function index()
    {
        $association = $this->association();

        /* ---------- Members ---------- */

        // Section 22's 3-month rule: keep every member's Active/Inactive
        // status current before counting them below - see
        // Farmer::sweepInactive().
        Farmer::sweepInactive();

        $membersByStatus = DB::table('farmers')
            ->where('association_id', $association->id)
            ->select('activity_status', DB::raw('COUNT(*) as total'))
            ->groupBy('activity_status')
            ->pluck('total', 'activity_status');

        $totalMembers = (int) $membersByStatus->sum();

        // "Affected" means the member has filed at least one damage report.
        $affectedMembers = Farmer::where('association_id', $association->id)
            ->whereHas('damageReports')
            ->count();

        /* ---------- Damage reports ---------- */

        $reportsByStatus = DB::table('damage_reports')
            ->join('farmers', 'farmers.id', '=', 'damage_reports.farmer_id')
            ->where('farmers.association_id', $association->id)
            ->select('damage_reports.status', DB::raw('COUNT(*) as total'))
            ->groupBy('damage_reports.status')
            ->pluck('total', 'status');

        /* ---------- Assistance ---------- */

        $allocations = AssistanceAllocation::where('association_id', $association->id);

        $allocatedQuantity = (float) (clone $allocations)->sum('allocated_quantity');

        $distributedQuantity = (float) AssistanceDistribution::whereIn(
            'assistance_allocation_id',
            (clone $allocations)->select('id')
        )->sum('quantity');

        // Allocations that still have something left to hand out. This is the
        // dashboard's call to action, so it links straight to the list.
        $openAllocations = (clone $allocations)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        // Distributions the farmer has not confirmed receiving yet. Section
        // 65: recording that we gave it out is not the same as the farmer
        // saying they got it, and the schema keeps the two apart.
        $awaitingConfirmation = AssistanceDistribution::whereIn(
                'assistance_allocation_id',
                (clone $allocations)->select('id')
            )
            ->where('receipt_status', 'pending_confirmation')
            ->count();

        /* ---------- Two short lists ---------- */

        $recentReports = DamageReport::whereIn(
                'farmer_id',
                Farmer::where('association_id', $association->id)->select('id')
            )
            ->with(['farmer', 'reportedBarangay', 'crops.crop', 'validation'])
            ->latest()
            ->limit(5)
            ->get();

        $needsDistribution = (clone $allocations)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['assistance', 'disaster'])
            ->withSum('distributions as distributed_so_far', 'quantity')
            ->orderBy('allocated_at')
            ->limit(5)
            ->get();

        return view('association.dashboard', [
            'association' => $association,

            'summary' => [
                'total_members'    => $totalMembers,
                'active_members'   => (int) ($membersByStatus['active'] ?? 0),
                'inactive_members' => (int) ($membersByStatus['inactive'] ?? 0),
                'affected_members' => $affectedMembers,

                'total_reports'    => (int) $reportsByStatus->sum(),
                'pending_reports'  => (int) ($reportsByStatus['pending'] ?? 0)
                                      + (int) ($reportsByStatus['assigned'] ?? 0)
                                      + (int) ($reportsByStatus['under_verification'] ?? 0),
                'verified_reports' => (int) ($reportsByStatus['verified'] ?? 0)
                                      + (int) ($reportsByStatus['approved'] ?? 0),

                'open_allocations'      => $openAllocations,
                'allocated_quantity'    => $allocatedQuantity,
                'distributed_quantity'  => $distributedQuantity,
                'awaiting_confirmation' => $awaitingConfirmation,
            ],

            'recentReports'     => $recentReports,
            'needsDistribution' => $needsDistribution,
        ]);
    }
}
