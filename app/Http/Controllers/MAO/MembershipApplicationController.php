<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Models\User;
use App\Notifications\FarmerRegistrationApprovedNotification;
use App\Support\MembershipApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MAO reviews farmer registrations and approves or rejects them.
 * A farmer cannot use the system properly until approved.
 */
class MembershipApplicationController extends Controller
{
    /**
     * The office's own two filter options, in front of the stages and
     * outcomes that App\Support\MembershipApplication supplies.
     *
     * 'awaiting_mao' is the default and is the office's actual queue: the
     * applications a farmers' association has reviewed and handed over.
     * Before revision step 6 the default was every pending application,
     * which was the same list, because every application went straight to
     * the office. It is not the same list any more, and defaulting to the
     * wider one would put applications the office cannot yet act on at the
     * top of the page it opens every morning.
     *
     * 'open' is that wider list, kept one click away, because the office
     * does need it: it is how they see which associations are sitting on a
     * queue, and how they answer a farmer who rings to ask where their
     * application has got to.
     */
    public const FILTERS = [
        'awaiting_mao' => 'Awaiting Your Decision',
        'open'         => 'All Open Applications',
    ];

    public function index(Request $request)
    {
        /* "all" is a real value in the dropdown, not an empty option, and
           that is load bearing.

           It used to be the select's placeholder, which submits status= with
           nothing after it. ConvertEmptyStringsToNull, which runs on every
           request including the query string, then turned that into null,
           and query('status', 'pending') resolves null to its default. So
           choosing "All Status" came back as Pending: the list stayed
           filtered and the dropdown reset itself, which is exactly what the
           office reported.

           Reading a filter whose "show everything" option is empty through
           a defaulted getter is the trap. With a named value there is
           nothing to convert and nothing to default. */
        $status = (string) $request->query('status', 'awaiting_mao');

        if ($status === '' || ! $this->isKnownFilter($status)) {
            $status = 'awaiting_mao';
        }

        $applications = Farmer::query()
            ->notDeleted()
            ->with(['user', 'association', 'barangay', 'returnedBy'])
            /* The office's queue: forwarded by an association and not yet
               decided. Both halves matter - an approved farmer's stage
               column still says 'forwarded_to_mao', because that is the
               record of how their application reached its outcome and is
               deliberately left alone (see the migration), so filtering on
               the stage alone would keep every approved farmer in the
               queue forever. */
            ->when($status === 'awaiting_mao', fn ($query) => $query
                ->where('membership_stage', MembershipApplication::FORWARDED)
                ->whereHas('user', fn ($user) => $user->where('status', 'pending')))
            ->when($status === 'open', fn ($query) => $query
                ->whereHas('user', fn ($user) => $user->where('status', 'pending')))
            ->when(in_array($status, MembershipApplication::stageKeys(), true), fn ($query) => $query
                ->where('membership_stage', $status)
                ->whereHas('user', fn ($user) => $user->where('status', 'pending')))
            // The outcomes are the account's own status, which is where they
            // are stored; see App\Support\MembershipApplication.
            ->when($status === MembershipApplication::APPROVED, fn ($query) => $query
                ->whereHas('user', fn ($user) => $user->where('status', 'active')))
            ->when($status === MembershipApplication::REJECTED, fn ($query) => $query
                ->whereHas('user', fn ($user) => $user->where('status', 'rejected')))
            ->when($status === MembershipApplication::ARCHIVED, fn ($query) => $query
                ->whereHas('user', fn ($user) => $user->where('status', 'inactive')))
            ->when($request->filled('barangay_id'),
                fn ($query) => $query->where('barangay_id', $request->barangay_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';

                $query->where(function ($sub) use ($term) {
                    $sub->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereHas('user', fn ($user) => $user->where('username', 'like', $term));
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // List + detail panel (Sept 2026): "View" loads the application
        // inline in the right-hand panel via ?selected=<id> instead of
        // navigating to a separate review page.
        $selected = $request->filled('selected')
            ? Farmer::query()->notDeleted()
                ->with(['user', 'association', 'barangay', 'mainCrops.crop',
                        'returnedBy', 'forwardedBy', 'associationReviewer', 'decidedBy', 'maoReturnedBy'])
                ->find($request->selected)
            : null;

        return view('mao.membership-applications.index', [
            'applications' => $applications,
            'selected'     => $selected,
            'status'       => $status,
            'filters'      => self::FILTERS + MembershipApplication::filterOptions(),
            'barangays'    => Barangay::orderBy('name')->get(),
            /* The open application's own history, loaded only when one is
               open: it is a second query, and the list itself has no use for
               it. App\Support\MembershipApplication::history() reads the
               existing audit log rather than a second table. */
            'history'      => MembershipApplication::history($selected),
        ]);
    }

    private function isKnownFilter(string $filter): bool
    {
        return array_key_exists($filter, self::FILTERS)
            || array_key_exists($filter, MembershipApplication::filterOptions())
            || $filter === 'all';
    }

    /**
     * The office may only decide an application a farmers' association has
     * forwarded (revision step 6).
     *
     * Checked here rather than only in the view. The office can SEE every
     * application from the moment it is submitted, which is deliberate, so
     * the detail panel for one still with an association renders with its
     * buttons hidden; hiding a button is not a rule, and a page left open
     * while the stage moves underneath it still has its form.
     */
    private function assertOfficesTurn(Farmer $farmer): void
    {
        abort_unless(
            MembershipApplication::isOfficesTurn($farmer),
            403,
            'This application is still with the farmers\' association. It has to be forwarded before the office can decide it.'
        );
    }

    public function show(Farmer $farmer)
    {
        $farmer->load([
            'user', 'association', 'barangay', 'mainCrops.crop',
            // Who moved it and what they said, at each handover: the
            // association verification block and the decision block both
            // read these.
            'associationReviewer', 'forwardedBy', 'returnedBy', 'decidedBy',
        ]);

        // Opening an application takes it out of the sidebar's "new to you" count.
        \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'membership', $farmer->id);

        return view('mao.membership-applications.show', [
            'farmer'  => $farmer,
            'history' => MembershipApplication::history($farmer),
        ]);
    }

    /**
     * Approve the application and activate the membership.
     *
     * CHECKED TWICE, THE SECOND TIME UNDER A LOCK
     *
     * assertOfficesTurn() above is the cheap check that rejects the obvious
     * cases: an application still with the association, or one already
     * decided. It is not enough on its own, because between that check and
     * the write there is a gap, and two officers clicking Approve on the
     * same application at the same moment can both pass it. The second
     * check happens inside the transaction with the account row locked, so
     * the two approvals queue and the second one reads status 'active',
     * fails isOfficesTurn(), and rolls back with a 403.
     *
     * That is what stops a duplicate membership activation rather than a
     * uniqueness constraint, because there is no second row to be unique
     * about: a farmer, their application and their membership are one
     * record, which is also why no duplicate membership can be created by
     * approving twice. The risk was never two memberships; it was two
     * approval notifications, two audit entries and a decided_by that
     * records whichever officer lost the race.
     *
     * The lock is taken on users, not farmers, because users.status is the
     * fact being contended for.
     */
    public function approve(Request $request, Farmer $farmer)
    {
        $this->assertOfficesTurn($farmer);

        /* Optional on an approval. The office approving somebody is the
           expected outcome and usually needs no explanation; a required
           field here would collect a column of full stops. */
        $data = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ], [], ['approval_note' => 'note']);

        $note = trim((string) ($data['approval_note'] ?? '')) ?: null;

        DB::transaction(function () use ($farmer, $note) {
            $fresh = $this->lockForDecision($farmer);

            $from = MembershipApplication::forFarmer($fresh);

            $fresh->user->update(['status' => 'active']);

            /* APPROVAL NO LONGER TOUCHES THE INACTIVITY CLOCK
               (revision section 15, Oct 2026)

               This used to set activity_status to 'active' and
               last_activity_date to today. Both were wrong, and the second
               one quietly mattered.

               Section 15: "Only a new valid Crop Planted Report resets the
               inactivity period", and a farmer who has never filed one is
               measured "from the registration date". Approval is neither. So
               a farmer who registered in January, was approved in March and
               never planted anything was being measured from March - and the
               drift was however long the office took to approve them, which
               is exactly the stretch the rule wants counted.

               activity_status went with it. The farmer's activity status is
               derived from their planting records by sweepInactive(), and
               setting it here would have asserted "active" about somebody
               whose records may say otherwise - a claim this method is not in
               a position to make, and one the next sweep would overturn
               anyway.

               Nothing replaces these two writes. Registration already starts
               the account Active, and the clock already runs from the
               registration date via the model's fallback. */

            $fresh->recordDecision(Auth::id(), $note);

            $this->log(
                'Approved farmer registration: ' . $fresh->full_name,
                $fresh->id,
                $from,
                MembershipApplication::APPROVED,
                $note
            );
        });

        $this->notifyApproved($farmer);

        return redirect()->route('mao.membership-applications.index')
            ->with('status', 'Farmer registration approved successfully.');
    }

    /**
     * Re-read the application with its account row locked, and re-assert
     * that it is still the office's to decide.
     *
     * Shared by approve() and reject(), because the race is the same one and
     * a guard written twice is a guard that gets fixed once.
     *
     * The returned Farmer carries the locked user as its loaded relation, so
     * everything downstream reads the row that is actually locked rather
     * than a second, unlocked copy of it.
     */
    private function lockForDecision(Farmer $farmer): Farmer
    {
        $user = User::query()->whereKey($farmer->user_id)->lockForUpdate()->first();

        abort_if($user === null, 404);

        $fresh = Farmer::query()->whereKey($farmer->id)->first();

        abort_if($fresh === null, 404);

        $fresh->setRelation('user', $user);

        $this->assertOfficesTurn($fresh);

        return $fresh;
    }

    /**
     * Tell the newly-approved farmer on every channel: in-app, SMS, and
     * email. Runs after the approval transaction above has already
     * committed, since none of this is something a failed send should be
     * allowed to roll back - the farmer is approved either way.
     *
     * In-app + SMS reuse the existing NotificationBroadcast pipeline
     * (App\Models\NotificationBroadcast::dispatchToRecipients()) instead of
     * writing to the notifications table directly, because
     * notification_broadcast_id is a required column there - this is the
     * one supported way to create a notifications row. 'urgent' priority is
     * what makes that pipeline also queue the SMS (see
     * NotificationBroadcast::PRIORITIES); it silently no-ops the SMS half
     * if SEMAPHORE_API_KEY is not configured yet, so approval itself is
     * never blocked by SMS being unset up.
     *
     * A failure in either channel is logged, not thrown - the farmer is
     * already approved and this page has already redirected with a success
     * message by the time this runs from approve() above.
     */
    private function notifyApproved(Farmer $farmer): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Registration Approved',
                'message'     => 'Your farmer registration has been reviewed and approved. '
                    . 'Your account is now active - you can log in and start recording your crop planting activities.',
                'category'    => 'system',
                'priority'    => 'urgent',
                'target_type' => 'specific_farmer',
                'target_id'   => $farmer->id,
                'link_type'   => 'membership_application',
                'link_id'     => $farmer->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not send approval in-app/SMS notification for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }

        try {
            $farmer->user->notify(new FarmerRegistrationApprovedNotification($farmer));
        } catch (\Throwable $e) {
            Log::warning('Could not send approval email for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }

        $this->notifyAssociationOfNewMember($farmer);
    }

    /**
     * Let the farmer's association know a new member just joined them - the
     * association only monitors its members (proposal Role 3), it does not
     * approve them, so this is purely informational and in-app only. It is
     * skipped entirely for a farmer who registered without an association,
     * since there is nobody to tell.
     *
     * 'normal' priority is deliberate: section 68 reserves SMS for Urgent
     * and Critical matters, and a routine membership update is neither.
     * Reuses the same NotificationBroadcast pipeline as notifyApproved()
     * above. 'specific_association' reaches that association's officer
     * accounts only (see NotificationBroadcast::recipientIds()). It used to
     * reach every member farmer too, which meant ordinary members were sent
     * instructions only an officer could act on, and in the damage-report
     * case were told by name which of their co-members had filed a report.
     * The approved farmer is told separately, by notifyApproved().
     */
    private function notifyAssociationOfNewMember(Farmer $farmer): void
    {
        if (! $farmer->association_id) {
            return;
        }

        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'New Member Approved',
                'message'     => $farmer->full_name . ' has been approved by the Municipal Agriculture Office '
                    . 'and is now an active member of your association.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'specific_association',
                'target_id'   => $farmer->association_id,
                'link_type'   => 'membership_application',
                'link_id'     => $farmer->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify association ' . $farmer->association_id . ' of new member ' . $farmer->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Reject the application. The membership is NOT activated.
     *
     * THE REASON IS NOW REQUIRED (changed Oct 2026)
     *
     * It used to be 'nullable', and when it was given it was concatenated
     * into the audit log's action text and into the notification body, so
     * the application itself never held the reason it was refused. Both
     * halves of that were wrong:
     *
     *   - A farmer told only "your registration was not approved" has
     *     nothing to act on and no way to know whether to try again. The
     *     revision requires a reason for exactly this reason, and so does
     *     the office: rejecting somebody is the one decision here that ends
     *     an application, and it should cost a sentence to make.
     *   - A reason that lives only in a notification body is a reason that
     *     cannot be looked up later, and the audit text it was glued into
     *     was a VARCHAR(255) column that a long reason overflowed. See
     *     migration 2024_01_23_000001.
     *
     * min:10 rather than just 'required', because "no" passes required and
     * explains nothing. Same rule as the correction note on both sides of
     * the workflow.
     *
     * The reason is stored on the application (decision_note), written to
     * the audit trail's own remarks column, and sent to the farmer. One
     * value, three places that need it, no concatenation.
     */
    public function reject(Request $request, Farmer $farmer)
    {
        $this->assertOfficesTurn($farmer);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [], ['rejection_reason' => 'rejection reason']);

        DB::transaction(function () use ($farmer, $data) {
            $fresh = $this->lockForDecision($farmer);

            $from = MembershipApplication::forFarmer($fresh);

            $fresh->user->update(['status' => 'rejected']);

            $fresh->recordDecision(Auth::id(), $data['rejection_reason']);

            $this->log(
                'Rejected farmer registration: ' . $fresh->full_name,
                $fresh->id,
                $from,
                MembershipApplication::REJECTED,
                $data['rejection_reason']
            );
        });

        $this->notifyRejected($farmer, $data['rejection_reason']);

        return redirect()->route('mao.membership-applications.index')
            ->with('status', 'Farmer registration rejected.');
    }

    /**
     * The rejection counterpart to notifyApproved() above - proposal section
     * 66 lists "Registration rejected" as one of the things a farmer should
     * be told, and this was previously missing (only approval notified
     * anyone). In-app only ('normal' priority, section 68 reserves SMS for
     * urgent/critical matters): disappointing news, but not an emergency.
     * Runs after the transaction above has already committed, and never
     * throws - the rejection itself must never appear to fail just because a
     * notification could not be sent.
     */
    private function notifyRejected(Farmer $farmer, string $reason): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Registration Rejected',
                'message'     => 'Your farmer registration has been reviewed and was not approved by the '
                    . 'Municipal Agriculture Office.' . ($reason ? ' Reason: ' . $reason : '')
                    . ' Please contact the office if you have questions.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'specific_farmer',
                'target_id'   => $farmer->id,
                'link_type'   => 'membership_application',
                'link_id'     => $farmer->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not send rejection notification for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Send a forwarded application back to the ASSOCIATION to be corrected.
     *
     * THE OFFICE DOES NOT WRITE TO THE FARMER (changed Oct 2026)
     *
     * This used to return the application straight to the farmer. It now
     * goes to the farmers' association first, with the office's remarks, and
     * the association is the one that returns it to the farmer. The farmer
     * only ever hears from their own association, and the association sees
     * what the office found before anything reaches the farmer.
     *
     * The remarks are required, the same as a rejection, because an
     * association handed an application with no reason has nothing to pass
     * on. Returning is not rejecting and must not be read as a soft
     * rejection: the account stays pending and every detail the farmer
     * entered is kept.
     *
     * The route is still the office's "return" action and still takes
     * return_note; only where it lands has changed.
     */
    public function returnForCorrection(Request $request, Farmer $farmer)
    {
        $this->assertOfficesTurn($farmer);

        $data = $request->validate([
            'return_note' => ['required', 'string', 'min:10', 'max:1000'],
        ], [], ['return_note' => 'return remarks']);

        $from = MembershipApplication::forFarmer($farmer);

        DB::transaction(function () use ($farmer, $data, &$from) {
            // Same lock and re-check as approve() and reject(): two officers
            // acting on one application cannot both succeed.
            $fresh = $this->lockForDecision($farmer);

            $from = MembershipApplication::forFarmer($fresh);

            $fresh->returnToAssociation(Auth::id(), $data['return_note']);

            // Remarks in their own column, not concatenated into the action
            // text (migration 2024_01_23_000001).
            $this->log(
                'Returned membership application to the association: ' . $fresh->full_name,
                $fresh->id,
                $from,
                MembershipApplication::RETURNED_TO_ASSOCIATION,
                $data['return_note']
            );
        });

        $this->notifyReturned($farmer, $data['return_note']);

        return redirect()->route('mao.membership-applications.index')
            ->with('status', 'Application returned to the association with your remarks.');
    }

    /**
     * Tell the association, and only the association.
     *
     * The farmer is deliberately not told here: the association passes the
     * correction on in its own words, and a farmer who got a message from
     * the office and then another from their association would be told the
     * same thing twice. 'important' priority because an officer has to act
     * on it; section 68 keeps SMS for urgent and critical matters, and this
     * is neither. Never throws, because the application has already moved.
     */
    private function notifyReturned(Farmer $farmer, string $note): void
    {
        if (! $farmer->association_id) {
            return;
        }

        try {
            NotificationBroadcast::create([
                'title'       => 'Application Returned by the Office',
                'message'     => 'The Municipal Agriculture Office returned ' . $farmer->full_name
                    . '\'s membership application to your association. Please review the office\'s remarks '
                    . 'and return it to the farmer for correction. Remarks: ' . $note,
                'category'    => 'system',
                'priority'    => 'important',
                'target_type' => 'specific_association',
                'target_id'   => $farmer->association_id,
                'link_type'   => 'membership_application',
                'link_id'     => $farmer->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ])->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify association ' . $farmer->association_id
                . ' of a returned application: ' . $e->getMessage());
        }
    }

    /**
     * Archiving keeps the record but takes the application out of the queue.
     * Nothing is deleted.
     */
    public function archive(Farmer $farmer)
    {
        $from = MembershipApplication::forFarmer($farmer);

        $farmer->user->update(['status' => 'inactive']);

        $this->log(
            'Archived farmer application: ' . $farmer->full_name,
            $farmer->id,
            $from,
            MembershipApplication::ARCHIVED
        );

        return back()->with('status', 'Application archived.');
    }

    /**
     * Bring an archived farmer back. Used from the MAO Archive page.
     *
     * Deliberately not the same as approve(): approve() is the office's
     * decision on a brand new application, and this is only undoing a filing
     * action. Restoring returns the account to the review queue and changes
     * nothing else.
     *
     * (This note used to add that approve() "also resets activity_status and
     * last_activity_date, which would be wrong here". It no longer does
     * either - revision section 15 took both writes out of approve(), since
     * approval is not a Crop Planted Report. So that difference between the
     * two methods has gone, and the remaining one is the only one: approve()
     * decides, restore() un-archives.)
     *
     * RESTORED TO PENDING, NOT ACTIVE (fixed Oct 2026)
     *
     * This used to set the account straight to 'active'. archive() sets
     * 'inactive' from whatever the account was before, pending included, so
     * restoring an archived application that had never been decided
     * APPROVED it: a farmer nobody had approved got full access to the
     * system from a button whose label is "Restore".
     *
     * That was wrong before revision step 6 and is worse after it, because
     * the farmers' association never vouched for them either. So a restored
     * farmer goes back to pending and into the queue, and the office
     * approves them with the Approve button, which is the only thing that
     * should ever grant access.
     *
     * The cost is that restoring a farmer who really had been approved
     * means approving them a second time. That is one extra click on a rare
     * action, and it is the right way round: the failure mode is a farmer
     * waiting for a click, not a farmer let in without one. Their stage
     * column still reads 'forwarded_to_mao' - archiving never touched it -
     * so the application is already in the office's own queue and needs
     * nothing from the association.
     */
    public function restore(Farmer $farmer)
    {
        if ($farmer->is_deleted) {
            return back()->withErrors([
                'farmer' => 'This farmer was permanently deleted and can no longer be restored.',
            ]);
        }

        $from = MembershipApplication::forFarmer($farmer);

        $farmer->user->update(['status' => 'pending']);

        $farmer->refresh()->load('user');

        $this->log(
            'Restored farmer to the review queue: ' . $farmer->full_name,
            $farmer->id,
            $from,
            MembershipApplication::forFarmer($farmer)
        );

        return back()->with('status',
            'Farmer restored and returned to the review queue. Approve the application to give them access again.');
    }

    /**
     * One audit row per move (proposal section 81, and the revision's
     * application-history requirement).
     *
     * $from and $to are what turn this table into a history rather than a
     * list of sentences, and they are also the filter:
     * App\Support\MembershipApplication::history() picks this workflow's
     * rows out of the shared audit table by looking for a non-null
     * to_status. So an entry logged with them shows on the application's
     * history and one logged without them does not, with no list of magic
     * action strings to keep in step.
     *
     * $remarks is stored in its own column and never concatenated into
     * $action. That concatenation is what made a long correction note crash
     * a VARCHAR(255) column inside a transaction; see migration
     * 2024_01_23_000001.
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
