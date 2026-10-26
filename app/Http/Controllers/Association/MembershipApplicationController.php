<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Support\MembershipApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Membership Applications, the association's side (revision step 6).
 *
 * The one screen in this role that is not read-only, and the reason the
 * workflow exists. A farmer picks their association when they register, and
 * that association is the only body that actually knows whether the person
 * is one of its members; the office does not. So the application comes here
 * first, an officer confirms the member is theirs, and then it goes to the
 * Municipal Agriculture Office for the decision.
 *
 * WHAT AN OFFICER CAN AND CANNOT DO
 *
 * Forward it, or send it back to the farmer to fix something. That is all.
 * Approving and rejecting stay with the office, which was the developer's
 * decision when this was specified: the association vouches, the office
 * decides membership. An officer who believes somebody should not be a
 * member forwards nothing and says so in a return note, or rings the
 * office; they cannot end an application themselves, so there is no way for
 * a farmer to be turned away without the office ever seeing the application.
 *
 * EVERY ACTION IS GATED TWICE
 *
 * Once on the association (ResolvesAssociation, the whole security model for
 * this role), and once on the stage
 * (MembershipApplication::isAssociationsTurn()). The stage check is in the
 * controller and not only in the view on purpose: a page drawn five minutes
 * ago still has its buttons, and by then the office may have returned the
 * same application itself.
 */
class MembershipApplicationController extends Controller
{
    use ResolvesAssociation;

    /**
     * The filter's own options, with 'open' first because that is what an
     * officer comes here for.
     *
     * Every one of these has a real value. An option whose value is empty
     * submits the field with nothing after it, ConvertEmptyStringsToNull
     * turns that into null, and a defaulted getter then hands back the
     * default, which is how the office's own Membership Applications filter
     * came to show Pending whenever somebody chose All Status. Nothing here
     * can do that.
     */
    public const FILTERS = ['open' => 'Open Applications', 'all' => 'All Applications'];

    public function index(Request $request)
    {
        $association = $this->association();

        $filter = (string) $request->query('stage', 'open');

        if ($filter === '' || ! $this->isKnownFilter($filter)) {
            $filter = 'open';
        }

        $applications = $this->scoped()
            ->with(['user', 'barangay', 'mainCrops.crop', 'returnedBy', 'associationReviewer'])
            ->when($filter === 'open', fn ($query) => $query->whereHas('user',
                fn ($user) => $user->where('status', 'pending')))
            ->when(in_array($filter, MembershipApplication::stageKeys(), true),
                fn ($query) => $query->where('membership_stage', $filter)
                    ->whereHas('user', fn ($user) => $user->where('status', 'pending')))
            ->when($filter === MembershipApplication::APPROVED, fn ($query) => $query->whereHas('user',
                fn ($user) => $user->where('status', 'active')))
            ->when($filter === MembershipApplication::REJECTED, fn ($query) => $query->whereHas('user',
                fn ($user) => $user->where('status', 'rejected')))
            ->when($filter === MembershipApplication::ARCHIVED, fn ($query) => $query->whereHas('user',
                fn ($user) => $user->where('status', 'inactive')))
            ->when($request->filled('barangay_id'),
                fn ($query) => $query->where('barangay_id', $request->barangay_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';

                $query->where(fn ($sub) => $sub->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term));
            })
            ->orderByRaw($this->queueOrder())
            ->latest()
            ->paginate(12)
            ->withQueryString();

        /* The one opened with ?selected=<id>. Found through the scoped query
           rather than checked afterwards, so an id from another association
           simply is not there: the same approach the rest of this role uses,
           and the reason there is no separate ownership check to forget. */
        $selected = $request->filled('selected')
            ? $this->scoped()
                ->with(['user', 'barangay', 'mainCrops.crop', 'returnedBy', 'associationReviewer', 'forwardedBy', 'maoReturnedBy'])
                ->find($request->selected)
            : null;

        return view('association.membership-applications.index', [
            'applications' => $applications,
            'selected'     => $selected,
            'stage'        => $filter,
            'filters'      => self::FILTERS + MembershipApplication::filterOptions(),
            'barangays'    => Barangay::orderBy('name')->get(),
            'awaitingUs'   => $this->awaitingUsCount(),
        ]);
    }

    /**
     * An officer opened the application and is reviewing it.
     *
     * The same shape as the technician's Start Inspection, and for the same
     * reason: the stage change is the record that a named person took
     * responsibility for looking at this, and the office can see which
     * applications are genuinely being worked on rather than only which ones
     * have arrived.
     *
     * Nobody is notified. This is internal to the association and tells the
     * farmer nothing they can act on.
     */
    public function startReview(Farmer $farmer)
    {
        $farmer = $this->findOrFail($farmer->id);

        // canForward(), not isAssociationsTurn(): an application the office
        // sent back is not "started" again, it is returned to the farmer.
        abort_unless(MembershipApplication::canForward($farmer), 403,
            'This application is no longer waiting for you to start a review.');

        if (MembershipApplication::forFarmer($farmer) === MembershipApplication::UNDER_REVIEW) {
            return back()->with('status', 'This application is already under review.');
        }

        $from = MembershipApplication::forFarmer($farmer);

        DB::transaction(function () use ($farmer, $from) {
            $farmer->startAssociationReview(Auth::id());

            $this->log(
                'Started association review of membership application: ' . $farmer->full_name,
                $farmer->id,
                $from,
                MembershipApplication::UNDER_REVIEW
            );
        });

        return back()->with('status', 'Review started. You can now forward this application or return it for correction.');
    }

    /**
     * The association verified this member. Off to the office.
     *
     * VERIFYING AND FORWARDING ARE ONE ACT, NOT TWO
     *
     * The revision describes the officer's action as "Verify and Forward to
     * MAO", and there is no separate verified flag because there is no state
     * in which an application is verified but still sitting here. An officer
     * who is not satisfied returns it; an officer who is satisfied forwards
     * it, and forwarding IS the verification. forwarded_by and forwarded_at
     * are the record of who verified it and when, which is what the office
     * reads on the other side.
     *
     * A second boolean column would have been a column that is always true
     * wherever it is set, and always null where it is not, which is the
     * definition of a column that says nothing the stage does not.
     *
     * The remarks are optional, and that is a judgement rather than an
     * oversight. A return has to say what is wrong, because the farmer
     * cannot act without knowing. A forward does not: most are routine, and
     * a required field on a routine action produces a column full of "ok"
     * and teaches the office to stop reading it. The office's screen says
     * "No remarks given" when there are none, which is honest and unmissable.
     */
    public function forward(Request $request, Farmer $farmer)
    {
        $farmer = $this->findOrFail($farmer->id);

        /* Not isAssociationsTurn(): an application the office returned to
           this association can only go back to the farmer. Forwarding it
           again unchanged would put the same application back in front of
           the office with nothing corrected. */
        abort_unless(MembershipApplication::canForward($farmer), 403,
            'This application can no longer be forwarded. If the office returned it, return it to the farmer for correction instead.');

        $data = $request->validate([
            'forward_note' => ['nullable', 'string', 'max:1000'],
        ], [], ['forward_note' => 'verification remarks']);

        $note = trim((string) ($data['forward_note'] ?? '')) ?: null;
        $from = MembershipApplication::forFarmer($farmer);

        DB::transaction(function () use ($farmer, $note, $from) {
            $farmer->forwardToMao(Auth::id(), $note);

            $this->log(
                'Verified and forwarded membership application to MAO: ' . $farmer->full_name,
                $farmer->id,
                $from,
                MembershipApplication::FORWARDED,
                $note
            );
        });

        $this->notifyOfficeOfForwardedApplication($farmer, $note);
        $this->notifyFarmer(
            $farmer,
            'Application Forwarded to the Municipal Agriculture Office',
            'Your Farmers\' Association has verified your application and forwarded it to the '
                . 'Municipal Agriculture Office. The office will review it and you will be told the outcome.'
        );

        return redirect()
            ->route('association.membership-applications.index')
            ->with('status', 'Application verified and forwarded to the Municipal Agriculture Office.');
    }

    /**
     * Sent back to the farmer with something to fix.
     *
     * The note is required and that is not a formality: a return with no
     * reason is a farmer told their application is wrong without being told
     * what is wrong with it, which costs them a trip to the office to ask.
     */
    public function returnForCorrection(Request $request, Farmer $farmer)
    {
        $farmer = $this->findOrFail($farmer->id);

        abort_unless(MembershipApplication::isAssociationsTurn($farmer), 403,
            'This application is no longer with your association.');

        $data = $request->validate([
            'return_note' => ['required', 'string', 'min:10', 'max:1000'],
        ], [], ['return_note' => 'correction note']);

        $from = MembershipApplication::forFarmer($farmer);

        // Did the office send this back to us? That changes what the farmer
        // is told: the request ultimately came from the office, and the
        // farmer should know the correction is not only the association's
        // opinion.
        $fromOffice = $from === MembershipApplication::RETURNED_TO_ASSOCIATION;

        DB::transaction(function () use ($farmer, $data, $from) {
            $farmer->returnForCorrection(Auth::id(), 'association', $data['return_note']);

            /* The note goes in the remarks column, not glued onto the action
               text as it used to be. That was a VARCHAR(255) column and the
               note validates at max:1000, so a detailed explanation raised
               "Data too long for column 'action'" inside this very
               transaction and the return failed. See migration
               2024_01_23_000001. */
            $this->log(
                'Returned membership application for correction: ' . $farmer->full_name,
                $farmer->id,
                $from,
                MembershipApplication::RETURNED,
                $data['return_note']
            );
        });

        $this->notifyFarmer(
            $farmer,
            'Your Application Needs Correction',
            ($fromOffice
                ? 'Your Farmers\' Association, following a review by the Municipal Agriculture Office, has asked '
                    . 'you to correct something in your registration before it can be decided. What needs fixing: '
                : 'Your Farmers\' Association has asked you to correct something in your registration before '
                    . 'it can be forwarded to the Municipal Agriculture Office. What needs fixing: ')
                . $data['return_note']
        );

        return redirect()
            ->route('association.membership-applications.index')
            ->with('status', 'Application returned to the farmer for correction.');
    }

    /* =====================================================================
     | Scoping and lookups
     ===================================================================== */

    /**
     * Every query in this controller starts here: this association's farmers
     * and nobody else's, and nothing that has been deleted.
     */
    private function scoped()
    {
        return Farmer::query()
            ->notDeleted()
            ->where('association_id', $this->association()->id);
    }

    /**
     * The farmer, re-fetched through the scoped query.
     *
     * The route gives us a Farmer resolved from the id with no scope on it
     * at all, so re-fetching is what turns "this id exists" into "this id is
     * one of ours". A farmer from another association is simply not found,
     * which is a 404 rather than a message confirming that the record exists
     * somewhere.
     */
    private function findOrFail(int $id): Farmer
    {
        $farmer = $this->scoped()->with('user')->find($id);

        abort_if($farmer === null, 404);

        return $farmer;
    }

    private function isKnownFilter(string $filter): bool
    {
        return array_key_exists($filter, self::FILTERS)
            || array_key_exists($filter, MembershipApplication::filterOptions());
    }

    /** How many applications are on this association's desk right now. */
    private function awaitingUsCount(): int
    {
        return $this->scoped()
            ->whereIn('membership_stage', [
                MembershipApplication::RETURNED_TO_ASSOCIATION,
                MembershipApplication::SUBMITTED,
                MembershipApplication::UNDER_REVIEW,
            ])
            ->whereHas('user', fn ($user) => $user->where('status', 'pending'))
            ->count();
    }

    /**
     * The queue order: what the officer has to act on, first.
     *
     * Applications the office sent back come first (the office is waiting
     * on them), then Submitted and Under Association Review, which are
     * theirs too; then the ones waiting on the farmer; then the ones already with
     * the office, which they can see but not touch. Written as FIELD()
     * rather than sorted in PHP because the list is paginated, and a page of
     * twelve sorted after it was fetched would put the wrong twelve on page
     * one.
     */
    private function queueOrder(): string
    {
        return "FIELD(membership_stage, '" . MembershipApplication::RETURNED_TO_ASSOCIATION . "', '"
            . MembershipApplication::SUBMITTED . "', '"
            . MembershipApplication::UNDER_REVIEW . "', '"
            . MembershipApplication::RETURNED . "', '"
            . MembershipApplication::FORWARDED . "')";
    }

    /* =====================================================================
     | Notifications
     |
     | All in-app and 'normal' priority. Section 68 reserves SMS for urgent
     | and critical matters, and none of this is either: an application
     | moving between two desks is routine, and a farmer who has to correct
     | something is not in an emergency.
     |
     | Every one of these runs after its transaction has committed and
     | swallows its own failure. The application has already moved by the
     | time these are called, and a notification that could not be sent must
     | never make a completed action look as though it failed.
     ===================================================================== */

    private function notifyOfficeOfForwardedApplication(Farmer $farmer, ?string $note): void
    {
        $this->send(
            'Membership Application Forwarded',
            $farmer->full_name . ' has been verified by ' . $this->association()->name
                . ' and forwarded for your final decision.'
                . ($note ? ' Association remarks: ' . $note : ''),
            'all_mao',
            null,
            'MAO',
            $farmer->id
        );
    }

    private function notifyFarmer(Farmer $farmer, string $title, string $message): void
    {
        $this->send($title, $message, 'specific_farmer', $farmer->id, 'farmer ' . $farmer->id, $farmer->id);
    }

    private function send(string $title, string $message, string $targetType, ?int $targetId, string $who, ?int $farmerId = null): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => $title,
                'message'     => $message,
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'link_type'   => $farmerId ? 'membership_application' : null,
                'link_id'     => $farmerId,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify ' . $who . ' about a membership application: ' . $e->getMessage());
        }
    }

    /**
     * One audit row per move (proposal section 81, and the revision's
     * application-history requirement).
     *
     * $from and $to are what make this a history rather than a list of
     * sentences: App\Support\MembershipApplication::history() picks workflow
     * rows out of the shared audit table by looking for a non-null
     * to_status, so anything logged with them appears on the application's
     * history and anything logged without them does not.
     *
     * $remarks is stored separately from $action and never concatenated into
     * it. See migration 2024_01_23_000001 for why that matters.
     */
    private function log(
        string $action,
        ?int $farmerId,
        ?string $from = null,
        ?string $to = null,
        ?string $remarks = null
    ): void {
        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'from_status'  => $from,
            'to_status'    => $to,
            'remarks'      => $remarks,
            'target_table' => 'farmers',
            'target_id'    => $farmerId,
            'created_at'   => now(),
        ]);
    }
}
