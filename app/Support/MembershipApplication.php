<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Farmer;

/**
 * Where a farmer's membership application has got to, and the one place that
 * works it out.
 *
 * Revision step 6: the farmers' association reads an application before the
 * office does, because the association is the body that knows whether the
 * person is one of its members. The four in-flight stages are stored on
 * farmers.membership_stage (migration 2024_01_21_000001); the two outcomes
 * are the account's own status, which is where they already lived.
 *
 * WHY THE OUTCOME IS NOT IN THE STAGE COLUMN
 *
 * Approved and Rejected are users.status 'active' and 'rejected'. Copying
 * them into the stage column would mean every approval writing two columns
 * in step, and the first write path that forgot would leave a farmer who can
 * log in while their application still reads "Forwarded to MAO". Reading the
 * account's status first cannot drift, because each state is stored in
 * exactly one place.
 *
 * The same precedence pattern as App\Support\AssistanceCase, on purpose: one
 * way of answering "which status is this record in" across the system rather
 * than a different trick per module.
 *
 * THE ORDER OF THE CHECKS IS THE RULE
 *
 * A decided account outranks the stage it was decided at. Once the office has
 * approved somebody, nothing about the route they took there changes what
 * they are, and the stage column is left exactly as it was so that it still
 * records how the application reached its outcome.
 */
class MembershipApplication
{
    /* The four stages an application moves through while it is still open.
       These are the values in farmers.membership_stage. */
    public const SUBMITTED               = 'submitted_to_association';
    public const UNDER_REVIEW            = 'under_association_review';
    public const FORWARDED               = 'forwarded_to_mao';
    public const RETURNED                = 'returned_for_correction';

    /* The office sent it back to the ASSOCIATION, not to the farmer. The
       association reads the office's remarks and is the one that returns it
       to the farmer (stage RETURNED), so the farmer only ever hears from
       their own association. */
    public const RETURNED_TO_ASSOCIATION = 'returned_to_association';

    /* The outcomes. Display values, resolved from the account's status,
       never written to the stage column. */
    public const APPROVED                = 'approved';
    public const REJECTED                = 'rejected';
    public const ARCHIVED                = 'archived';

    /**
     * The four open stages, with the label each is shown by, what it means,
     * and whose turn it is.
     *
     * "Whose turn" is the field the queues are built from and the one the
     * office kept asking for out loud: an application sitting still is only
     * a problem if it is sitting on somebody's desk, and this says which
     * desk. It is also what the farmer is shown, so that waiting is never
     * mistaken for having been ignored.
     *
     * Colour is not here. The keys are in x-ui.status's map, which is the
     * one place in this system that decides what colour a status is; render
     * one with <x-ui.status :value="$stage" /> and the colour comes with it.
     *
     * @return array<string, array{label:string, means:string, waiting_on:string}>
     */
    public static function stages(): array
    {
        return [
            self::SUBMITTED => [
                'label'      => 'Submitted to Association',
                'means'      => 'Sent to the farmers\' association. No officer has opened it yet.',
                'waiting_on' => 'association',
            ],
            self::UNDER_REVIEW => [
                'label'      => 'Under Association Review',
                'means'      => 'An officer of the association is reviewing it now.',
                'waiting_on' => 'association',
            ],
            self::FORWARDED => [
                'label'      => 'Forwarded to MAO',
                'means'      => 'The association vouched for this member and sent it to the office.',
                'waiting_on' => 'mao',
            ],
            self::RETURNED_TO_ASSOCIATION => [
                'label'      => 'Returned to Association',
                'means'      => 'The Municipal Agriculture Office sent this back to the association with remarks. The association passes it on to the farmer to correct.',
                'waiting_on' => 'association',
            ],
            self::RETURNED => [
                'label'      => 'Returned for Correction',
                'means'      => 'Something needs fixing. The farmer has to correct it and resubmit.',
                'waiting_on' => 'farmer',
            ],
        ];
    }

    /**
     * Every value that can be displayed, stages and outcomes together, for a
     * filter dropdown. In the order an application passes through them, with
     * the outcomes last.
     *
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        $options = [];

        foreach (self::stages() as $key => $stage) {
            $options[$key] = $stage['label'];
        }

        return $options + [
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::ARCHIVED => 'Archived',
        ];
    }

    /** Just the open stages, for a validation rule. */
    public static function stageKeys(): array
    {
        return array_keys(self::stages());
    }

    public static function label(?string $key): string
    {
        return self::filterOptions()[$key] ?? 'Submitted to Association';
    }

    public static function means(?string $key): string
    {
        return self::stages()[$key]['means'] ?? '';
    }

    /**
     * Which status to show for this farmer.
     *
     * Needs the user relation loaded; a list that does not eager-load it pays
     * a query per row.
     */
    public static function forFarmer(?Farmer $farmer): string
    {
        if (! $farmer) {
            return self::SUBMITTED;
        }

        // A decided account outranks the stage it was decided at.
        $accountStatus = $farmer->user?->status;

        if ($accountStatus === 'active') {
            return self::APPROVED;
        }

        if ($accountStatus === 'rejected') {
            return self::REJECTED;
        }

        // 'inactive' is what archiving an application sets (see
        // MAO\MembershipApplicationController::archive()), not a farmer who
        // has gone quiet - that is farmers.activity_status, a different
        // thing with a confusingly similar name.
        if ($accountStatus === 'inactive') {
            return self::ARCHIVED;
        }

        return $farmer->membership_stage ?? self::SUBMITTED;
    }

    /**
     * Whose desk is it on: 'association', 'mao', 'farmer', or null once it
     * has been decided.
     */
    public static function waitingOn(?Farmer $farmer): ?string
    {
        $status = self::forFarmer($farmer);

        return self::stages()[$status]['waiting_on'] ?? null;
    }

    /**
     * Is this application the association's to act on?
     *
     * The association may act at the two stages before it hands the
     * application over, and never after. Used by the association's
     * controller as the gate, not only by its view: a stage that has moved
     * on since the page was drawn must not be actionable because the button
     * is still on screen.
     */
    public static function isAssociationsTurn(?Farmer $farmer): bool
    {
        /* NO FARMER MEANS NO, NOT "YES, IT IS EARLY" (fixed Oct 2026)

           Without this line, null fell through to forFarmer(), which returns
           SUBMITTED for a null farmer - a sensible default for DISPLAY, and
           the wrong answer entirely for a permission check, because SUBMITTED
           is one of the two stages this method says yes to. So handing this
           guard nothing got you permission.

           Nothing could reach it: the association's controller re-fetches
           through findOrFail() and 404s first, and the views test $selected
           before calling. It was a trap rather than a hole, and the trap is
           that the next call site to forget a null check inherits a
           permissive answer from a function whose whole job is to refuse.
           isOfficesTurn() below was already safe, but only by luck -
           SUBMITTED simply is not FORWARDED - so it says so explicitly now
           too rather than relying on that.

           A guard fails closed. Caught by the workflow harness. */
        if ($farmer === null) {
            return false;
        }

        return in_array(self::forFarmer($farmer), [
            self::SUBMITTED, self::UNDER_REVIEW, self::RETURNED_TO_ASSOCIATION,
        ], true);
    }

    /**
     * May the association verify and forward this application?
     *
     * Narrower than isAssociationsTurn() on purpose. An application the
     * office sent back to the association can only be returned to the
     * farmer for correction: forwarding it again unchanged would just put
     * the same application back in front of the office with nothing fixed.
     * The same applies to Start Review, which only makes sense before the
     * association has handed anything over.
     */
    public static function canForward(?Farmer $farmer): bool
    {
        if ($farmer === null) {
            return false;
        }

        return in_array(self::forFarmer($farmer), [self::SUBMITTED, self::UNDER_REVIEW], true);
    }

    /**
     * Is this application the office's to act on?
     *
     * Only once it has been forwarded. The office can SEE every application
     * from the moment it is submitted, which is deliberate (an officer can
     * answer a farmer who rings up, and the office can see which
     * associations are sitting on their queue), but it cannot approve,
     * reject or return one that the association has not handed over. The
     * association stage is a gate, not a suggestion.
     */
    public static function isOfficesTurn(?Farmer $farmer): bool
    {
        // Explicit for the same reason as isAssociationsTurn() above. This
        // one already answered false for null, but by coincidence rather
        // than by decision, and a guard should not rely on a coincidence.
        if ($farmer === null) {
            return false;
        }

        return self::forFarmer($farmer) === self::FORWARDED;
    }

    /**
     * The application's history: every recorded move, newest first.
     *
     * READS THE EXISTING AUDIT LOG, DOES NOT ADD A SECOND ONE
     *
     * The revision asks for a history showing, per event, the action, the
     * previous status, the new status, who did it, their remarks and the
     * time - and says in the same breath not to build a second history
     * system if one already exists. audit_logs is that system and every
     * transition in this workflow has always written to it. What it could
     * not do before migration 2024_01_23_000001 was say which status a move
     * came FROM, because that fact only existed inside a sentence.
     *
     * HOW WORKFLOW ROWS ARE PICKED OUT OF A SHARED TABLE
     *
     * audit_logs holds every important action in the system, and plenty of
     * rows about a farmer are not application moves ("Archived farmer
     * application" aside, think of edits made elsewhere). The filter is
     * `to_status is not null`: a row has that column filled in only if
     * whoever wrote it was recording a transition. So this needs no list of
     * magic action strings to keep in step with the controllers, and a new
     * kind of audit entry about a farmer cannot accidentally appear in the
     * history.
     *
     * Rows written before October 2026 have no transition columns, so an
     * application decided before then shows the moves it has made since and
     * nothing earlier. That is a gap, and it is an honest one: the data to
     * fill it does not exist and the migration deliberately did not invent
     * it.
     *
     * Eager-loads the user, because the whole point of a history is to say
     * who, and a list of ten entries would otherwise be ten extra queries.
     */
    public static function history(?Farmer $farmer)
    {
        if (! $farmer) {
            return collect();
        }

        return AuditLog::query()
            ->about('farmers', $farmer->id)
            ->whereNotNull('to_status')
            ->with('user')
            ->get();
    }

    /**
     * A short label for one end of a transition, for the history list.
     *
     * Separate from label() because a transition's ends include states that
     * are not application statuses at all: a brand new application has no
     * previous status, and that reads better as "New" than as a blank.
     */
    public static function transitionLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return 'New';
        }

        return self::filterOptions()[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
