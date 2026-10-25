<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\AssistanceAllocationBeneficiary;
use App\Models\AssistanceDistribution;
use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Assistance Received (Farmer side)
 *
 * Proposal section 65: the farmer can view the assistance they received,
 * and now confirm it.
 *
 * How assistance flows in this system (changed Oct 2026): the MAO selects
 * the farmers who qualify, in a distribution batch, and hands the assistance
 * over itself. A farmer therefore sees two things here: the batches they
 * were SELECTED for (which exist from the moment the MAO finalises the
 * batch, before anything is handed over) and the distribution records
 * addressed to them once a hand-over is recorded.
 *
 * The confirmation matters more than it looks. The association recording
 * "we gave this out" and the farmer saying "I got it" are two different
 * claims, and the database keeps them in two separate columns on purpose.
 * If they ever disagree, the office needs to be able to see that rather than
 * have one silently overwrite the other.
 */
class AssistanceController extends Controller
{
    public function index()
    {
        $farmer = $this->farmer();

        $distributions = AssistanceDistribution::query()
            ->with([
                'allocation.assistance',   // what the assistance actually is
                'allocation.disaster',     // which disaster it was for
                'allocation.association',  // the batch's association, when it has one
                'damageReport',            // the report that made them eligible
                'distributedBy',
            ])
            ->where('farmer_id', $farmer->id)   // only their own
            ->orderByDesc('distributed_at')
            ->paginate(10);

        /* Batches this farmer was selected for that have no hand-over record
           yet. Before this, the page listed distributions only, so a farmer
           who had been selected (and notified) saw "No assistance recorded
           yet". A batch that already has a distribution row for this farmer
           is shown by that row instead, so it is never listed twice, and a
           cancelled batch is not shown at all. */
        $handedOverBatchIds = AssistanceDistribution::where('farmer_id', $farmer->id)
            ->pluck('assistance_allocation_id');

        $selected = AssistanceAllocationBeneficiary::query()
            ->with('allocation')
            ->where('farmer_id', $farmer->id)
            ->whereNotIn('assistance_allocation_id', $handedOverBatchIds)
            ->whereHas('allocation', fn ($a) => $a->where('status', '!=', 'cancelled'))
            ->orderByDesc('created_at')
            ->get();

        // The farmer's payroll number on each batch they are on, for the
        // cards (PAY-2026-0003-007), keyed by batch.
        $payrollRefs = AssistanceAllocationBeneficiary::with('allocation')
            ->where('farmer_id', $farmer->id)
            ->get()
            ->mapWithKeys(fn ($b) => [$b->assistance_allocation_id => $b->payroll_ref]);

        return view('farmer.assistance.index', [
            'payrollRefs'   => $payrollRefs,
            'farmer'        => $farmer,
            'distributions' => $distributions,
            'selected'      => $selected,

            /* Drives the prompt at the top of the page. Nothing shouts at
               the farmer when there is nothing waiting on them.

               It counts the rows the page actually asks about, which means
               the hand-over has to be recorded as well (section 11.4).
               Without the distribution_status clause the banner could say
               "1 item waiting for your answer" above a card with no
               question on it. */
            'awaiting' => AssistanceDistribution::where('farmer_id', $farmer->id)
                ->whereIn('distribution_status', ['distributed', 'completed'])
                ->where('receipt_status', 'pending_confirmation')
                ->count(),
        ]);
    }

    /**
     * "Yes I received this" or "No I did not".
     *
     * Either answer is a valid, useful record. Saying no is not a complaint
     * form, it is how the office finds out that something went missing
     * between the association and the farmer, which is exactly the gap this
     * system exists to make visible.
     *
     * Answered once. After that it is a record, and changing it becomes a
     * conversation with the office rather than a button.
     */
    public function confirmReceipt(Request $request, AssistanceDistribution $distribution)
    {
        $farmer = $this->farmer();

        // Nobody confirms receipt of somebody else's assistance.
        abort_unless($distribution->farmer_id === $farmer->id, 403);

        if ($distribution->receipt_status !== 'pending_confirmation') {
            return back()->withErrors([
                'receipt' => 'You have already answered this one. Please contact the Municipal Agriculture Office if it needs changing.',
            ]);
        }

        /* Section 11.4: nothing can be marked received before the hand-over
           itself is on the record. The association writes 'distributed' when
           it records giving something out, so a row still at
           'pending_distribution' is one nobody has handed over.
           
           The page does not show the question for such a row either, so this
           is unreachable through the interface and is here for the request
           that does not come from the interface. A farmer confirming receipt
           of something never given out would be the one piece of data in
           this module that nobody could later untangle. */
        if (! in_array($distribution->distribution_status, ['distributed', 'completed'], true)) {
            return back()->withErrors([
                'receipt' => 'This assistance has not been handed over yet, so there is nothing to confirm. '
                    . 'Please ask the Municipal Agriculture Office about it.',
            ]);
        }

        $data = $request->validate([
            'receipt_status' => ['required', Rule::in(['confirmed_received', 'not_received'])],

            // Required when saying no, because "I did not get it" with no
            // explanation gives the office nothing to act on.
            'receipt_note' => [
                Rule::requiredIf(fn () => $request->input('receipt_status') === 'not_received'),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'receipt_status.required' => 'Please choose whether you received this.',
            'receipt_note.required'   => 'Please tell the office briefly what happened, so they can follow it up.',
        ]);

        DB::transaction(function () use ($distribution, $data, $farmer) {
            $distribution->update([
                'receipt_status'       => $data['receipt_status'],
                'receipt_note'         => $data['receipt_note'] ?? null,
                'receipt_confirmed_at' => now(),
            ]);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => $farmer->full_name . ' marked assistance distribution #' . $distribution->id
                                  . ' as ' . str_replace('_', ' ', $data['receipt_status']),
                'target_table' => 'assistance_distributions',
                'target_id'    => $distribution->id,
                'created_at'   => now(),
            ]);
        });

        $this->notifyAssociationOfReceiptAnswer($distribution, $farmer, $data['receipt_status'], $data['receipt_note'] ?? null);

        if ($data['receipt_status'] === 'not_received') {
            $this->notifyMaoOfNonReceipt($distribution, $farmer, $data['receipt_note'] ?? null);
        }

        return back()->with('status', $data['receipt_status'] === 'confirmed_received'
            ? 'Thank you. Your receipt has been recorded. Salamat po.'
            : 'Recorded. The Municipal Agriculture Office can now follow this up.');
    }

    /**
     * The farmer's answer on a batch they were selected for, before any
     * hand-over has been recorded.
     *
     * The office hands the assistance over itself and no longer records a
     * hand-over first (Oct 2026), so the farmer's own confirmation is what
     * creates the record. "Yes" writes a hand-over dated today and
     * already confirmed. "No" writes a row that is NOT counted as handed
     * over but is marked not received, which is exactly what puts it on the
     * office's list of disputes and raises the notices.
     *
     * Answered once per batch: a row for this farmer and batch is the
     * answer, and a second request is refused.
     */
    public function confirmSelection(Request $request, AssistanceAllocationBeneficiary $selection)
    {
        $farmer = $this->farmer();

        // Nobody answers for somebody else.
        abort_unless($selection->farmer_id === $farmer->id, 403);

        $allocation = $selection->allocation;
        abort_unless($allocation && $allocation->status !== 'cancelled', 404);

        $data = $request->validate([
            'receipt_status' => ['required', Rule::in(['confirmed_received', 'not_received'])],
            'receipt_note'   => [
                Rule::requiredIf(fn () => $request->input('receipt_status') === 'not_received'),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'receipt_status.required' => 'Please choose whether you received this.',
            'receipt_note.required'   => 'Please tell the office briefly what happened, so they can follow it up.',
        ]);

        $received = $data['receipt_status'] === 'confirmed_received';

        $distribution = DB::transaction(function () use ($allocation, $selection, $farmer, $data, $received) {
            $already = AssistanceDistribution::where('assistance_allocation_id', $allocation->id)
                ->where('farmer_id', $farmer->id)
                ->lockForUpdate()
                ->exists();

            if ($already) {
                return null;
            }

            $distribution = AssistanceDistribution::create([
                'assistance_allocation_id' => $allocation->id,
                'farmer_id'                => $farmer->id,
                'damage_report_id'         => $selection->damage_report_id,
                // The office gave it, so the office's user is on the record.
                'distributed_by'           => $allocation->allocated_by ?? Auth::id(),
                // NOT NULL on the table. For a "no" this is the date the
                // farmer answered; distribution_status below is what says
                // whether anything was actually handed over.
                'distributed_at'           => now(),
                'remarks'                  => $received ? 'Confirmed by the farmer.' : null,
                'distribution_status'      => $received ? 'distributed' : 'pending_distribution',
                'receipt_status'           => $data['receipt_status'],
                'receipt_note'             => $data['receipt_note'] ?? null,
                'receipt_confirmed_at'     => now(),
            ]);

            if ($received) {
                $designated = $allocation->beneficiaries()->count();
                $handedTo   = $allocation->distributions()
                    ->whereIn('distribution_status', ['distributed', 'completed'])
                    ->distinct('farmer_id')
                    ->count('farmer_id');

                $allocation->update([
                    'status' => ($designated > 0 && $handedTo >= $designated) ? 'completed' : 'distributed',
                    'distributed_to_association_at' => $allocation->distributed_to_association_at ?? now(),
                    'distributed_by'                => $allocation->distributed_by ?? $allocation->allocated_by ?? Auth::id(),
                ]);
            }

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => $farmer->full_name . ' marked assistance from batch AA-'
                                  . str_pad((string) $allocation->id, 3, '0', STR_PAD_LEFT) . ' as '
                                  . str_replace('_', ' ', $data['receipt_status']),
                'target_table' => 'assistance_distributions',
                'target_id'    => $distribution->id,
                'created_at'   => now(),
            ]);

            return $distribution;
        });

        if (! $distribution) {
            return back()->withErrors(['receipt' => 'You have already answered this one.']);
        }

        $this->notifyAssociationOfReceiptAnswer($distribution, $farmer, $data['receipt_status'], $data['receipt_note'] ?? null);

        if (! $received) {
            $this->notifyMaoOfNonReceipt($distribution, $farmer, $data['receipt_note'] ?? null);
        }

        return back()->with('status', $received
            ? 'Thank you. Your receipt has been recorded. Salamat po.'
            : 'Recorded. The Municipal Agriculture Office can now follow this up.');
    }

    /**
     * Proposal section 66: "member assistance confirmations/non-receipt
     * reports" should reach the association either way - a confirmation
     * closes the loop, a non-receipt report needs following up. Runs after
     * the transaction above has already committed, and never throws - the
     * farmer's answer must never appear to fail just because a notification
     * could not be sent. Skipped when the distribution has no association on
     * it, which should not normally happen (every allocation belongs to one).
     *
     * A confirmation is 'normal' priority (good news, informational); a
     * non-receipt report is 'important' (in-app only - section 68 reserves
     * SMS for urgent/critical matters) since something has gone wrong
     * between the association and the farmer and needs following up.
     * link_type/link_id let the bell open the allocation this distribution
     * came from (NotificationBroadcast::linkUrl()).
     */
    private function notifyAssociationOfReceiptAnswer(
        AssistanceDistribution $distribution,
        Farmer $farmer,
        string $receiptStatus,
        ?string $note
    ): void {
        $distribution->loadMissing('allocation');
        $allocationId  = $distribution->allocation?->id;
        $associationId = $distribution->allocation?->association_id;

        if (! $associationId) {
            return;
        }

        // display_name is never null (it returns the one word), so the old
        // in_kind_description fallback behind it was unreachable once the
        // assistance item was removed in Oct 2026.
        $what = $distribution->allocation?->display_name ?? 'Assistance';

        [$title, $message, $priority] = $receiptStatus === 'confirmed_received'
            ? [
                'Member Confirmed Receipt',
                $farmer->full_name . ' confirmed receiving ' . $what . '.',
                'normal',
            ]
            : [
                'Non-Receipt Reported',
                $farmer->full_name . ' reported NOT receiving ' . $what
                    . ($note ? ': ' . $note : '.') . ' Please follow this up.',
                'important',
            ];

        try {
            $alert = NotificationBroadcast::create([
                'title'       => $title,
                'message'     => $message,
                'category'    => 'assistance',
                'priority'    => $priority,
                'target_type' => 'specific_association',
                'target_id'   => $associationId,
                'link_type'   => 'assistance_allocation',
                'link_id'     => $allocationId,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify association ' . $associationId . ' of receipt answer on '
                . 'distribution ' . $distribution->id . ': ' . $e->getMessage());
        }
    }

    /**
     * MAO only hears about the non-receipt case, not an ordinary confirmation -
     * "assistance allocation/distribution updates" for MAO (section 66) is
     * about things going wrong or needing a decision, not routine good news
     * the association already sees. Runs after the transaction above has
     * already committed, and never throws, for the same reason as above.
     */
    private function notifyMaoOfNonReceipt(AssistanceDistribution $distribution, Farmer $farmer, ?string $note): void
    {
        $distribution->loadMissing('allocation');
        $allocationId = $distribution->allocation?->id;
        // display_name is never null (it returns the one word), so the old
        // in_kind_description fallback behind it was unreachable once the
        // assistance item was removed in Oct 2026.
        $what = $distribution->allocation?->display_name ?? 'Assistance';

        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Non-Receipt Reported',
                'message'     => $farmer->full_name . ' reported NOT receiving ' . $what
                    . ($note ? ': ' . $note : '.') . ' Please follow this up.',
                'category'    => 'assistance',
                'priority'    => 'important',
                'target_type' => 'all_mao',
                'link_type'   => 'assistance_allocation',
                'link_id'     => $allocationId,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify MAO of non-receipt report on distribution '
                . $distribution->id . ': ' . $e->getMessage());
        }
    }

    /**
     * The signed in farmer. Cached for the request because more than one
     * method needs it and there is no reason to query twice.
     */
    private function farmer(): Farmer
    {
        static $farmer;

        return $farmer ??= Farmer::where('user_id', Auth::id())->firstOrFail();
    }
}
