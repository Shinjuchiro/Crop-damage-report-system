<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\AssistanceAllocation;
use App\Models\AssistanceAllocationBeneficiary;
use App\Models\AssistanceDistribution;
use App\Models\DamageReport;
use App\Models\Farmer;
use Illuminate\Support\Facades\DB;

/**
 * Farmers' Association Dashboard  (proposal section 60)
 *
 * What an officer needs to know when they open the system:
 * how many members they have, how many of them were hit, and what
 * assistance the office has set aside for this association's members and
 * not yet handed over.
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

        /* "Affected" means the member has filed at least one damage report
           that still stands. A report the office archived or deleted must not
           keep a member counted as affected here while their own list no
           longer shows it (section 16). */
        $affectedMembers = Farmer::where('association_id', $association->id)
            ->whereHas('damageReports', fn ($report) => $report->active())
            ->count();

        /* ---------- Damage reports ---------- */

        /* Written out rather than using the model's active() scope, because
           this is a query builder call and not an Eloquent one. Same two
           columns, same meaning: the figures on this dashboard have to match
           the Crop Damage list, and that list drops archived and deleted
           reports. */
        $reportsByStatus = DB::table('damage_reports')
            ->join('farmers', 'farmers.id', '=', 'damage_reports.farmer_id')
            ->where('farmers.association_id', $association->id)
            ->whereNull('damage_reports.archived_at')
            ->whereNull('damage_reports.deleted_at')
            ->select('damage_reports.status', DB::raw('COUNT(*) as total'))
            ->groupBy('damage_reports.status')
            ->pluck('total', 'status');

        /* ---------- Assistance ---------- */

        /* "Allocations with one of my members on them", not "allocations
           addressed to me" (Oct 2026): an allocation is a batch of farmers
           chosen across the municipality now, so association_id is null on
           anything new. Same change as Association\AssistanceController. */
        $allocations = AssistanceAllocation::query()
            ->whereHas('beneficiaries.farmer',
                fn ($farmer) => $farmer->where('association_id', $association->id));

        /* COUNTS OF PEOPLE, NOT SUMS OF GOODS (Oct 2026, client decision)

           These were allocated_quantity and the quantity handed out, two
           figures. There are no figures: how much a named farmer received
           is a financial detail about an identifiable person and is out of
           this system. The same two questions, asked about members:

           designated - how many of my members the office has selected
           handedTo   - how many of them have actually been given theirs

           Both scoped to this association's own members, because a batch
           can span several and an officer should see neither other
           associations' members nor a total that includes them. */
        $designatedMembers = AssistanceAllocationBeneficiary::whereHas('allocation',
                fn ($allocation) => $allocation->where('status', '!=', 'cancelled'))
            ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $association->id))
            ->distinct('farmer_id')
            ->count('farmer_id');

        $membersHandedTo = AssistanceDistribution::whereIn(
                'assistance_allocation_id',
                (clone $allocations)->select('assistance_allocations.id')
            )
            ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $association->id))
            ->distinct('farmer_id')
            ->count('farmer_id');

        // Allocations not yet finished. The dashboard's call to action, so
        // it links straight to the list.
        $openAllocations = (clone $allocations)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        // Distributions the farmer has not confirmed receiving yet. Section
        // 65: recording that we gave it out is not the same as the farmer
        // saying they got it, and the schema keeps the two apart.
        $awaitingConfirmation = AssistanceDistribution::whereIn(
                'assistance_allocation_id',
                (clone $allocations)->select('assistance_allocations.id')
            )
            ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $association->id))
            ->where('receipt_status', 'pending_confirmation')
            ->count();

        /* ---------- Two short lists ---------- */

        $recentReports = DamageReport::active()
            ->whereIn(
                'farmer_id',
                Farmer::where('association_id', $association->id)->select('id')
            )
            ->with(['farmer', 'reportedBarangay', 'crops.crop', 'validation'])
            ->latest()
            ->limit(5)
            ->get();

        /* Was "allocations still needing distribution", with a running
           quantity total. The association does not distribute any more (the
           office does), and there is no quantity, so this is simply the
           open batches its members are on, with its own members counted. */
        $needsDistribution = (clone $allocations)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->withCount([
                'beneficiaries' => fn ($q) => $q->whereHas('farmer',
                    fn ($f) => $f->where('association_id', $association->id)),
                'distributions' => fn ($q) => $q->whereHas('farmer',
                    fn ($f) => $f->where('association_id', $association->id)),
            ])
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
                'designated_members'    => $designatedMembers,
                'members_handed_to'     => $membersHandedTo,
                'awaiting_confirmation' => $awaitingConfirmation,
            ],

            'map'               => \App\Support\AssociationMapData::build($association),
            'recentReports'     => $recentReports,
            'needsDistribution' => $needsDistribution,
        ]);
    }
}
