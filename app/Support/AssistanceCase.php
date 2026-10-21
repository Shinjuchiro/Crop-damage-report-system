<?php

namespace App\Support;

use App\Models\AssistanceDecision;
use App\Models\DamageReport;

/**
 * The four assistance statuses, and the one place that works out which one a
 * farmer's case is in.
 *
 * Revision section 11.1: exactly four main statuses, and no others. Not
 * "Distribution Recorded", not "Awaiting Acknowledgment", not "Receipt
 * Acknowledged". Distribution and confirmation are events that happen along
 * the way, recorded on their own rows with their own dates and their own
 * authors, and the status stays one of these four.
 *
 * WHY A RESOLVER RATHER THAN A COLUMN
 *
 * Because no single table knows the answer. A case is Pending Review when
 * the office has not decided; Designated once it has said yes; Not
 * Designated once it has said no; and Received only when assistance was
 * actually handed over AND the farmer confirmed it. That reads across three
 * places: assistance_decisions, assistance_distributions and the farmer's
 * own receipt answer on the distribution.
 *
 * A fifth status column kept in step by hand would be the obvious
 * alternative and the wrong one: every write path in three modules would
 * have to remember to update it, and the first one that forgot would leave
 * a farmer reading "Designated" on a screen while the office reads
 * "Received" on another. Working it out on demand cannot drift.
 *
 * THE ORDER OF THE CHECKS IS THE RULE
 *
 * Received is checked first and deliberately outranks everything else: once
 * a farmer says they have the assistance in their hands, no earlier state
 * matters. Then the office's own decision. Pending Review is the answer
 * when nothing else applies, which is also the safe answer, because a case
 * nobody has decided should read as waiting rather than as refused.
 *
 * WHAT THIS IS NOT
 *
 * It is not the damage report's own status, and the two must not be
 * conflated. A report can be approved (the office accepts the damage is
 * real) and the farmer still Not Designated (there was not enough to go
 * round). Section 11.2 is explicit that assistance is never automatic
 * because a report was submitted, matched to a planting record, or
 * inspected.
 */
class AssistanceCase
{
    public const PENDING_REVIEW  = 'pending_review';
    public const DESIGNATED      = 'designated';
    public const NOT_DESIGNATED  = 'not_designated';
    public const RECEIVED        = 'received';

    /**
     * The four, in the order the office works through them, with the label
     * each one is shown by and a line saying what it actually means.
     *
     * The labels are the revision's own words. They are longer than a badge
     * would like, and they are kept in full on purpose: "Designated" on its
     * own invites being read as "given", which is the single most expensive
     * misreading this module can produce.
     *
     * COLOUR IS NOT HERE
     *
     * The four keys are in x-ui.status's own map, which is the one place
     * that decides what colour a status is in this system; its docblock
     * asks for exactly that, so that a status cannot be green on one screen
     * and grey on another. Render one of these with
     * <x-ui.status :value="$case" /> and the colour comes with it.
     *
     * @return array<string, array{label:string, means:string}>
     */
    public static function all(): array
    {
        return [
            self::PENDING_REVIEW => [
                'label' => 'Pending Review',
                'means' => 'The office has not yet decided whether this farmer will be assisted.',
            ],
            self::DESIGNATED => [
                'label' => 'Designated for Assistance',
                'means' => 'The office selected this farmer. Nothing has been handed over yet.',
            ],
            self::NOT_DESIGNATED => [
                'label' => 'Not Designated for Assistance',
                'means' => 'The office decided this farmer is not covered by that allocation.',
            ],
            self::RECEIVED => [
                'label' => 'Received',
                'means' => 'Assistance was handed over and the farmer confirmed receiving it.',
            ],
        ];
    }

    /** Just the keys, for a validation rule or a filter dropdown. */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(?string $key): string
    {
        return self::all()[$key]['label'] ?? 'Pending Review';
    }

    public static function means(?string $key): string
    {
        return self::all()[$key]['means'] ?? '';
    }

    /**
     * Which of the four this report's case is in.
     *
     * Reads only what is already loaded where the caller has eager-loaded
     * assistanceDecision and assistanceDistributions; see
     * self::eagerLoad() for the pair to load, because calling this in a
     * loop over an un-eager-loaded list is two queries per row.
     */
    public static function forReport(?DamageReport $report): string
    {
        if (! $report) {
            return self::PENDING_REVIEW;
        }

        // 1. Received outranks everything. "Handed over" alone is NOT enough
        //    (section 11.3): the farmer has to have confirmed it, which is
        //    the receipt answer on the distribution and nothing else.
        $confirmed = $report->assistanceDistributions
            ->contains(fn ($distribution) => $distribution->receipt_status === 'confirmed_received');

        if ($confirmed) {
            return self::RECEIVED;
        }

        // 2. The office's own decision, when it has made one.
        $decision = $report->assistanceDecision;

        if ($decision) {
            return $decision->decision === AssistanceDecision::NOT_DESIGNATED
                ? self::NOT_DESIGNATED
                : self::DESIGNATED;
        }

        /* 3. A farmer can be in an allocation's beneficiary list without a
              decision row: that list is how designation has always been
              recorded here, and it predates this status. Treat being in one
              as the office having said yes, so cases decided before the
              decision table existed read correctly instead of all reverting
              to Pending Review. Cancelled allocations do not count - a
              cancelled allocation designated nobody. */
        if ($report->relationLoaded('allocationBeneficiaries')) {
            $designated = $report->allocationBeneficiaries
                ->contains(fn ($row) => $row->allocation && $row->allocation->status !== 'cancelled');

            if ($designated) {
                return self::DESIGNATED;
            }
        }

        // 4. Nothing decided. Waiting, not refused.
        return self::PENDING_REVIEW;
    }

    /**
     * What to eager-load on a damage report query before asking
     * forReport() about each row.
     *
     * Kept here next to the resolver that needs it, so a new caller cannot
     * forget half of it and quietly get the wrong answer: without
     * allocationBeneficiaries loaded, check 3 above is skipped and an older
     * designated case reads as Pending Review.
     */
    public static function eagerLoad(): array
    {
        return [
            'assistanceDecision',
            'assistanceDistributions',
            'allocationBeneficiaries.allocation',
        ];
    }
}
