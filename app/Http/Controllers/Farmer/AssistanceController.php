<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\AssistanceDistribution;
use App\Models\AuditLog;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Assistance Received (Farmer side)
 *
 * Proposal section 65: the farmer can view the assistance they received,
 * and now confirm it.
 *
 * How assistance flows in this system: MAO allocates a pool to an
 * ASSOCIATION, and the association distributes it to its members. So what a
 * farmer sees here are the distribution records addressed to them, not the
 * allocation.
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
                'allocation.association',  // who handed it over
                'damageReport',            // the report that made them eligible
                'distributedBy',
            ])
            ->where('farmer_id', $farmer->id)   // only their own
            ->orderByDesc('distributed_at')
            ->paginate(10);

        return view('farmer.assistance.index', [
            'farmer'        => $farmer,
            'distributions' => $distributions,

            // Drives the prompt at the top of the page. Nothing shouts at the
            // farmer when there is nothing waiting on them.
            'awaiting' => AssistanceDistribution::where('farmer_id', $farmer->id)
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
                'receipt' => 'You have already answered this one. Please contact your association if it needs changing.',
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

        return back()->with('status', $data['receipt_status'] === 'confirmed_received'
            ? 'Thank you. Your receipt has been recorded. Salamat po.'
            : 'Recorded. Your association and the Municipal Agriculture Office can now follow this up.');
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
