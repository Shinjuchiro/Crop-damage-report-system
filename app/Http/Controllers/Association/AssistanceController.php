<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\AssistanceAllocation;
use App\Models\AssistanceAllocationBeneficiary;
use App\Models\AssistanceDistribution;
use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assistance  (proposal sections 64 and 65)
 *
 * This is the missing link in the whole system. Assistance goes
 *
 *     MAO  ->  Association  ->  Farmer
 *
 * The MAO end has existed for a while: the office allocates a pool of
 * assistance to an association. Until this controller there was nowhere to
 * record the second hop, so assistance_distributions stayed empty forever and
 * a farmer's Assistance page was blank no matter how much had been allocated.
 *
 * Three rules are enforced here rather than trusted to the form:
 *
 *  1. An officer only ever touches their own association's allocations.
 *  2. A member is only eligible if MAO specifically named them as a
 *     beneficiary of THIS allocation (AssistanceAllocationBeneficiary) when
 *     they allocated it. This used to just be "any member with a verified
 *     or approved damage report" - which let an association distribute real
 *     assistance to a member MAO never selected, off a report MAO had not
 *     even approved yet, and (worse) off a report a technician had only
 *     verified but MAO could still go on to reject. The beneficiary list
 *     used to be informational only (see BUILD-STATUS decision 18); this
 *     build reverses that and makes it the actual gate.
 *  3. You cannot hand out more than was allocated. The remaining balance is
 *     computed from the distributions already recorded, never stored.
 */
class AssistanceController extends Controller
{
    use ResolvesAssociation;

    private const PHOTO_DISK = 'public';
    private const PHOTO_DIR  = 'distributions';
    private const MAX_PHOTOS = 6;

    /**
     * A report must be at one of these statuses before it can back a
     * distribution. Narrowed to 'approved' only (was ['verified',
     * 'approved']) because the real gate is now the beneficiary check in
     * assertEligible() below - a report can only reach the beneficiary list
     * in the first place once MAO approves it (see
     * AssistanceAllocationController::QUALIFYING_REPORT_STATUSES), so this
     * is kept as a defense-in-depth check, not the primary rule anymore.
     */
    private const ELIGIBLE_STATUSES = ['approved'];

    /**
     * Everything the office has allocated to this association.
     */
    public function index(Request $request)
    {
        $association = $this->association();

        $allocations = AssistanceAllocation::where('association_id', $association->id)
            ->with(['assistance', 'disaster', 'crop', 'allocatedBy'])
            ->withCount('distributions')
            ->withSum('distributions as distributed_so_far', 'quantity')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            // Anything still open comes first: that is aid sitting with the
            // association and not yet with a farmer.
            ->orderByRaw("FIELD(status, 'allocated', 'pending', 'distributed', 'completed', 'cancelled')")
            ->orderByDesc('allocated_at')
            ->paginate(12)
            ->withQueryString();

        return view('association.assistance.index', [
            'association' => $association,
            'allocations' => $allocations,
            'filters'     => $request->only(['status']),
        ]);
    }

    /**
     * One allocation: what it is, what is left, and who has already had some.
     */
    public function show(AssistanceAllocation $allocation)
    {
        $this->assertBelongsToAssociation($allocation->association_id);

        $allocation->load([
            'assistance', 'disaster', 'crop', 'allocatedBy',
            'distributions.farmer', 'distributions.damageReport',
            'distributions.distributedBy', 'distributions.photos',
        ]);

        return view('association.assistance.show', [
            'association' => $this->association(),
            'allocation'  => $allocation,
            'remaining'   => $this->remainingOn($allocation),
        ]);
    }

    /**
     * The form for handing part of an allocation to one member.
     */
    public function distribute(AssistanceAllocation $allocation)
    {
        $this->assertBelongsToAssociation($allocation->association_id);

        if (in_array($allocation->status, ['completed', 'cancelled'], true)) {
            return redirect()
                ->route('association.assistance.show', $allocation)
                ->withErrors(['allocation' => 'This allocation is closed, so nothing more can be given out from it.']);
        }

        return view('association.assistance.distribute', [
            'association'  => $this->association(),
            'allocation'   => $allocation->load(['assistance', 'disaster', 'crop']),
            'remaining'    => $this->remainingOn($allocation),
            'eligible'     => $this->eligibleMembers($allocation),
        ]);
    }

    /**
     * Record that assistance was handed to a member.
     *
     * Section 91.9: reviewed on screen, confirmed in the dialog, and only
     * then written. Everything below is the server checking the same things
     * again, because a hidden field is not a rule.
     */
    public function store(Request $request, AssistanceAllocation $allocation)
    {
        $this->assertBelongsToAssociation($allocation->association_id);

        if (in_array($allocation->status, ['completed', 'cancelled'], true)) {
            return back()->withErrors(['allocation' => 'This allocation is closed.']);
        }

        $data = $request->validate([
            // Must be a farmer, and the ownership check below makes sure it
            // is a farmer belonging to THIS association.
            'farmer_id' => ['required', Rule::exists('farmers', 'id')],

            // Section 64: every distribution cites the damage report that made
            // the farmer eligible, so aid can always be traced back to damage.
            'damage_report_id' => ['required', Rule::exists('damage_reports', 'id')],

            'quantity'            => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'in_kind_description' => ['nullable', 'string', 'max:255'],
            'remarks'             => ['nullable', 'string', 'max:1000'],
            'distributed_at'      => ['required', 'date', 'before_or_equal:today'],

            'photos'   => ['nullable', 'array', 'max:' . self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],
        ], [
            'farmer_id.required'        => 'Please choose the member who received the assistance.',
            'damage_report_id.required' => 'Please choose which damage report this assistance is for.',
            'quantity.required'         => 'Please enter how much was given.',
            'distributed_at.required'   => 'Please give the date it was handed over.',
            'distributed_at.before_or_equal' => 'The distribution date cannot be in the future.',
            'photos.*.max'              => 'Each photo must be 5 MB or smaller.',
        ]);

        $farmer = Farmer::findOrFail($data['farmer_id']);
        $report = DamageReport::findOrFail($data['damage_report_id']);

        $this->assertEligible($farmer, $report, $allocation);
        $this->assertQuantityFits($allocation, (float) $data['quantity']);

        $distribution = DB::transaction(function () use ($request, $allocation, $farmer, $report, $data) {

            $distribution = AssistanceDistribution::create([
                'assistance_allocation_id' => $allocation->id,
                'farmer_id'                => $farmer->id,
                'damage_report_id'         => $report->id,

                'in_kind_description' => $data['in_kind_description']
                                          ?? $allocation->in_kind_description,
                'quantity'            => $data['quantity'],

                'distributed_by' => Auth::id(),
                'distributed_at' => $data['distributed_at'],
                'remarks'        => $data['remarks'] ?? null,

                // Two separate tracks. We have handed it over; whether the
                // farmer agrees they received it is their answer to give,
                // on their own Assistance page.
                'distribution_status' => 'distributed',
                'receipt_status'      => 'pending_confirmation',
            ]);

            // The association's own proof, kept apart from anything the
            // farmer uploads later.
            foreach ($request->file('photos', []) as $photo) {
                $distribution->photos()->create([
                    'photo_type'  => 'receipt',
                    'file_path'   => $photo->store(self::PHOTO_DIR . '/' . $distribution->id, self::PHOTO_DISK),
                    'uploaded_at' => now(),
                ]);
            }

            $this->refreshAllocationTotals($allocation);
            $this->notifyFarmer($distribution, $farmer, $allocation);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Distributed ' . rtrim(rtrim(number_format((float) $data['quantity'], 2), '0'), '.')
                                  . ' of ' . ($allocation->assistance?->name ?? 'assistance')
                                  . ' to ' . $farmer->full_name . ' for ' . $report->reference,
                'target_table' => 'assistance_distributions',
                'target_id'    => $distribution->id,
                'created_at'   => now(),
            ]);

            return $distribution;
        });

        return redirect()
            ->route('association.assistance.show', $allocation)
            ->with('status', 'Distribution recorded for ' . $farmer->full_name
                . '. They will be asked to confirm they received it.');
    }

    /* ==================================================================
     | Helper methods
     ================================================================== */

    /**
     * How much of this allocation is left.
     *
     * Computed from the distributions every time, never stored. A stored
     * balance is wrong the moment anything is added, and the office would
     * have no way to tell.
     *
     * Returns null when the allocation has no quantity at all, which is how
     * cash assistance is recorded in this system.
     */
    private function remainingOn(AssistanceAllocation $allocation): ?float
    {
        if ($allocation->allocated_quantity === null) {
            return null;
        }

        $given = (float) $allocation->distributions()->sum('quantity');

        return round((float) $allocation->allocated_quantity - $given, 2);
    }

    /**
     * Is this member allowed to receive from THIS allocation?
     *
     * Four things have to hold, and all are checked here rather than trusted
     * to the dropdown, because a dropdown is only a suggestion once the form
     * has been posted. The last check is the important one: it is not
     * enough that the member has some approved report somewhere - MAO has
     * to have actually named them as a beneficiary of this specific
     * allocation when they allocated it (see the class docblock, rule 2).
     */
    private function assertEligible(Farmer $farmer, DamageReport $report, AssistanceAllocation $allocation): void
    {
        if ($farmer->association_id !== $this->association()->id) {
            throw ValidationException::withMessages([
                'farmer_id' => 'That farmer is not a member of your association.',
            ]);
        }

        if ($report->farmer_id !== $farmer->id) {
            throw ValidationException::withMessages([
                'damage_report_id' => 'That damage report belongs to a different farmer.',
            ]);
        }

        if (! in_array($report->status, self::ELIGIBLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'damage_report_id' => 'Report ' . $report->reference . ' has not been approved by MAO yet, '
                    . 'so assistance cannot be recorded against it.',
            ]);
        }

        $isNamedBeneficiary = AssistanceAllocationBeneficiary::where('assistance_allocation_id', $allocation->id)
            ->where('farmer_id', $farmer->id)
            ->where('damage_report_id', $report->id)
            ->exists();

        if (! $isNamedBeneficiary) {
            throw ValidationException::withMessages([
                'farmer_id' => 'MAO did not include ' . $farmer->full_name . ' as a beneficiary of this '
                    . 'allocation, so assistance cannot be recorded against them from it.',
            ]);
        }
    }

    /**
     * You cannot give out more than the office allocated.
     */
    private function assertQuantityFits(AssistanceAllocation $allocation, float $quantity): void
    {
        $remaining = $this->remainingOn($allocation);

        if ($remaining === null) {
            return;                       // no quantity tracked on this one
        }

        // The small allowance is for rounding, so giving the last 2.50 out of
        // exactly 2.5 is not rejected.
        if ($quantity > $remaining + 0.001) {
            throw ValidationException::withMessages([
                'quantity' => 'Only ' . number_format($remaining, 2) . ' is left on this allocation, '
                    . 'and you entered ' . number_format($quantity, 2) . '.',
            ]);
        }
    }

    /**
     * Keep the allocation's own totals in step after a distribution.
     *
     * distributed_quantity is a running total the MAO screens read, so it is
     * recalculated from the rows rather than incremented, which cannot drift.
     */
    private function refreshAllocationTotals(AssistanceAllocation $allocation): void
    {
        $given = (float) $allocation->distributions()->sum('quantity');

        $status = $allocation->status;

        if ($allocation->allocated_quantity !== null) {
            // Fully given out, allowing for rounding.
            $status = $given >= ((float) $allocation->allocated_quantity - 0.001)
                ? 'completed'
                : 'distributed';
        } elseif ($given > 0) {
            $status = 'distributed';
        }

        $allocation->update([
            'distributed_quantity'          => $given,
            'status'                        => $status,
            'distributed_to_association_at' => $allocation->distributed_to_association_at ?? now(),
            'distributed_by'                => $allocation->distributed_by ?? Auth::id(),
        ]);
    }

    /**
     * Tell the farmer, in the app (proposal section 66).
     *
     * The notifications table hangs every row off a broadcast, so we create a
     * one-person broadcast and let the existing dispatcher write the row. It
     * files under the "assistance" category, which the MAO alerts page
     * already has a tab for, so the office can see these too.
     *
     * Priority is normal on purpose: this is good news, not an emergency, and
     * section 67 says SMS is for urgent matters only.
     */
    private function notifyFarmer(AssistanceDistribution $distribution, Farmer $farmer, AssistanceAllocation $allocation): void
    {
        $what = $allocation->assistance?->name
            ?? $distribution->in_kind_description
            ?? 'Assistance';

        $broadcast = NotificationBroadcast::create([
            'title'       => 'Assistance recorded: ' . $what,
            'message'     => $this->association()->name . ' recorded giving you '
                . rtrim(rtrim(number_format((float) $distribution->quantity, 2), '0'), '.')
                . ' of ' . $what . ' on ' . $distribution->distributed_at->format('F d, Y')
                . '. Please open your Assistance page and confirm whether you received it. '
                . 'Pakikumpirma po sa inyong Assistance page kung natanggap ninyo ito.',
            'category'    => 'assistance',
            'priority'    => 'normal',
            'target_type' => 'specific_farmer',
            'target_id'   => $farmer->id,
            'status'      => 'draft',
            'created_by'  => Auth::id(),
        ]);

        $broadcast->dispatchToRecipients();
    }

    /**
     * Members who can legitimately receive something from THIS allocation
     * right now: this association's farmers MAO specifically named as
     * beneficiaries when they allocated it, with the reports that made them
     * a beneficiary loaded so the form can offer them.
     *
     * This used to be "any member with a verified or approved report",
     * completely independent of which allocation the officer opened the
     * form from - meaning MAO's own beneficiary selection was never
     * actually enforced. Scoping to $allocation's own beneficiary rows is
     * the fix (see the class docblock, rule 2).
     */
    private function eligibleMembers(AssistanceAllocation $allocation)
    {
        $beneficiaryReportIds = $allocation->beneficiaries()->pluck('damage_report_id');

        return Farmer::where('association_id', $this->association()->id)
            ->whereHas('damageReports', fn ($q) => $q->whereIn('id', $beneficiaryReportIds))
            ->with([
                'barangay',
                'damageReports' => fn ($q) => $q->whereIn('id', $beneficiaryReportIds)
                    ->with(['crops.crop', 'validation'])
                    ->latest(),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
