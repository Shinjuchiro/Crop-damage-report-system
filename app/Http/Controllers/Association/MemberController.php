<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Farmer;
use Illuminate\Http\Request;

/**
 * Members  (proposal section 61)
 *
 * The officer's directory of their own members, and the page for one member.
 *
 * Read only, deliberately. The association monitors; it does not edit farmer
 * records. Changing a farmer's details is the farmer's own job on their
 * profile, and approving a registration is the MAO's.
 */
class MemberController extends Controller
{
    use ResolvesAssociation;

    public function index(Request $request)
    {
        $association = $this->association();

        // Section 22's 3-month rule, made current BEFORE the query below
        // runs - not after, the way this page used to do it. Refreshing the
        // already-fetched page afterwards (the old $members->each->... line)
        // could leave a farmer showing an "Inactive" badge while still
        // being the reason a "status=active" filter matched them, since the
        // filter itself ran against the stale column. Sweeping first means
        // the ?status= filter and the badge always agree.
        Farmer::sweepInactive();

        $members = Farmer::where('association_id', $association->id)
            ->with(['barangay', 'user'])
            ->withCount(['damageReports', 'plantingRecords'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('activity_status', $status))
            ->when($request->query('affected') === 'yes', fn ($q) => $q->whereHas('damageReports'))
            ->when($request->query('barangay'), fn ($q, $id) => $q->where('barangay_id', $id))
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', "%{$term}%")
                          ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('association.members.index', [
            'association' => $association,
            'members'     => $members,
            'filters'     => $request->only(['status', 'affected', 'barangay', 'q']),

            // Only barangays this association actually has members in.
            'barangays' => Barangay::whereIn('id', Farmer::where('association_id', $association->id)
                    ->select('barangay_id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * One member: profile, planting history, damage reports, assistance.
     */
    public function show(Farmer $member)
    {
        // The check that matters. Without it, changing the id in the URL
        // would show a farmer from a different association.
        $this->assertBelongsToAssociation($member->association_id);

        $member->refreshActivityStatus();

        $member->load([
            'user', 'barangay', 'association', 'mainCrops.crop',
            'plantingRecords.crops.crop',
            'damageReports.crops.crop',
            'damageReports.disasters',
            'damageReports.validation',
            'damageReports.reportedBarangay',
            'assistanceDistributions.allocation.assistance',
            'assistanceDistributions.damageReport',
        ]);

        return view('association.members.show', [
            'association' => $this->association(),
            'member'      => $member,
        ]);
    }
}
