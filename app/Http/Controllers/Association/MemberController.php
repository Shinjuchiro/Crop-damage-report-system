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

        // Section 22's inactivity rule, made current BEFORE the query below
        // runs - not after, the way this page used to do it. Refreshing the
        // already-fetched page afterwards (the old $members->each->... line)
        // could leave a farmer showing an "Inactive" badge while still
        // being the reason a "status=active" filter matched them, since the
        // filter itself ran against the stale column. Sweeping first means
        // the ?status= filter and the badge always agree.
        Farmer::sweepInactive();

        /*
         | The extra relations are for the expanded row (revision section 8):
         | a member's row opens in place to show them at a glance, without
         | leaving the list. Eager-loaded rather than fetched when a row is
         | opened, because the alternative is an AJAX endpoint and a loading
         | state for data that is four small queries for the whole page.
         |
         | latestPlantingRecord and latestDamageReport are one-of-many
         | relations (see the note on them in App\Models\Farmer), so this
         | stays four queries whether the page shows one member or fifteen.
         */
        /* APPROVED MEMBERS ONLY (revision step 6)

           This used to be every farmer who had picked this association,
           whatever had become of their application. That was already
           misleading - somebody the office had rejected sat in the officer's
           Members list as though they were a member - and the new
           Membership Applications screen makes it worse, because an
           applicant would now appear on two screens at once with two
           different statuses.

           users.status is the account's own state, not the farming-activity
           status this page filters on: 'active' means the office approved
           them, 'pending' means their application is still somewhere in the
           workflow, 'rejected' means it was turned down, and 'inactive'
           means the record was archived. Only the first is a member.
           farmers.activity_status, the Active/Inactive badge on the rows
           below, is a different column with a confusingly similar name and
           is untouched by this. */
        $members = Farmer::where('association_id', $association->id)
            ->whereHas('user', fn ($user) => $user->where('status', 'active'))
            ->with([
                'barangay',
                'user',
                'mainCrops.crop',
                'latestPlantingRecord.crops.crop',
                'latestDamageReport.crops.crop',
                'latestDamageReport.validation',
            ])
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
            /* The approved-members filter from the list query above is
               repeated here on purpose, and for the reason section 49
               recorded about archived records: a record an officer cannot
               see in the list must not be reachable by typing its id into
               the address bar either. Without it, ?selected= would open the
               full member detail for somebody whose application is still
               being reviewed. */
            $candidate = Farmer::whereHas('user', fn ($user) => $user->where('status', 'active'))
                ->find($request->query('selected'));

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

            // Only barangays this association actually has members in -
            // approved members, matching the list itself, so the dropdown
            // cannot offer a barangay that filters the page to nothing.
            'barangays' => Barangay::whereIn('id', Farmer::where('association_id', $association->id)
                    ->whereHas('user', fn ($user) => $user->where('status', 'active'))
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
            'damageReports.validation.technician',
            'damageReports.reportedBarangay',
            'assistanceDistributions.allocation.assistance',
            'assistanceDistributions.damageReport',
        ];
    }

    /** The profile's tabs (revision section 9), in the order they appear. */
    public const TABS = ['overview', 'planting', 'damage', 'validation', 'assistance', 'history'];

    /**
     * One member's profile: the same six tabs the office's farmer profile
     * has, so a member reads the same way whoever opens them.
     *
     * The tab is a query parameter rather than script, so each one has its
     * own URL and the Back button behaves. Everything is loaded whichever
     * tab is open, because the tab strip shows a count beside each tab and
     * needs all of it anyway.
     */
    public function show(Request $request, Farmer $member)
    {
        // The check that matters. Without it, changing the id in the URL
        // would show a farmer from a different association.
        $this->assertBelongsToAssociation($member->association_id);

        // And the same approved-only rule the list and the panel apply. An
        // applicant under review belongs on the Membership Applications
        // screen, where the officer sees only what an application carries,
        // not on a member page built to show planting records and damage
        // reports they cannot have filed yet.
        abort_unless($member->user?->status === 'active', 404);

        $member->refreshActivityStatus();

        // Same relations the panel loads: see detailRelations() above.
        $member->load(self::detailRelations());

        $tab = $request->query('tab');

        return view('association.members.show', [
            'association' => $this->association(),
            'member'      => $member,
            'tab'         => in_array($tab, self::TABS, true) ? $tab : 'overview',
        ]);
    }
}
