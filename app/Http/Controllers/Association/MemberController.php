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

        /*
         | The member shown in the right-hand panel, when a row has been
         | picked with ?selected=<id>.
         |
         | The ownership check is the important line here and is the same one
         | show() makes. Without it, editing the number in the query string
         | would pull up a farmer from another association, which is exactly
         | the leak the rest of this controller is careful to avoid. It runs
         | against the id before anything is loaded or rendered.
         */
        $selected = null;

        if ($request->filled('selected')) {
            $candidate = Farmer::find($request->query('selected'));

            if ($candidate) {
                $this->assertBelongsToAssociation($candidate->association_id);

                $candidate->refreshActivityStatus();
                $selected = $candidate->load(self::detailRelations());
            }
        }

        return view('association.members.index', [
            'association' => $association,
            'members'     => $members,
            'selected'    => $selected,

            // Where "Back to list" goes on a phone, where the panel takes
            // over the screen: this same page with the selection dropped but
            // every filter and the page number kept.
            'backUrl'     => $request->fullUrlWithoutQuery('selected'),
            'filters'     => $request->only(['status', 'affected', 'barangay', 'q']),

            // Only barangays this association actually has members in.
            'barangays' => Barangay::whereIn('id', Farmer::where('association_id', $association->id)
                    ->select('barangay_id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Everything the member detail needs, in one place.
     *
     * Shared by index() for the panel and show() for the full page, so the
     * two can never drift into loading different things and leaving one of
     * them firing N+1 queries.
     */
    public static function detailRelations(): array
    {
        return [
            'user', 'barangay', 'association', 'mainCrops.crop',
            'plantingRecords.crops.crop',
            'damageReports.crops.crop',
            'damageReports.disasters',
            'damageReports.validation',
            'damageReports.reportedBarangay',
            'assistanceDistributions.allocation.assistance',
            'assistanceDistributions.damageReport',
        ];
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

        // Same relations the panel loads: see detailRelations() above.
        $member->load(self::detailRelations());

        return view('association.members.show', [
            'association' => $this->association(),
            'member'      => $member,
        ]);
    }
}
