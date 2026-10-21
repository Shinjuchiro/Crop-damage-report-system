<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\AssistanceAllocation;
use Illuminate\Http\Request;

/**
 * Assistance, as the association sees it (proposal sections 61, 64 and 65).
 *
 * READ-ONLY SINCE OCT 2026 (client decision)
 *
 * This controller used to record hand-outs. Assistance went
 *
 *     MAO  ->  Association  ->  Farmer
 *
 * and the association's Distribute screen was the only thing in the system
 * that could write an assistance_distributions row. The client changed that:
 * the Municipal Agriculture Office hands assistance to the farmer itself and
 * records it, and the association watches.
 *
 * That is closer to the written design than what it replaced, which is worth
 * saying plainly because removing a screen usually is not. Section 61 lists
 * what an association does and every item on it is a form of monitoring;
 * section 8's Role 3 says the same. The Distribute screen was added later
 * out of necessity, because without it nothing could ever record a
 * distribution and a farmer's Assistance page stayed empty forever. The
 * office doing it closes that gap from the other end.
 *
 * What went with it: distribute(), store(), the eligibility checks, the
 * quantity arithmetic and the farmer notification. They live in
 * MAO\AssistanceAllocationController now, minus the quantities, which went
 * out of the whole module for privacy - see App\Models\Assistance.
 *
 * What stays: the two screens an officer reads. An officer can see what the
 * office allocated to their association, which of their members were
 * designated, who has been handed theirs, and who has confirmed receiving
 * it. None of it is editable here.
 */
class AssistanceController extends Controller
{
    use ResolvesAssociation;

    /**
     * Everything the office has allocated to this association.
     */
    public function index(Request $request)
    {
        $association = $this->association();

        /* "Allocations that include one of my members", not "allocations
           addressed to me" (Oct 2026).

           An allocation is a batch of farmers chosen from across the
           municipality now, so association_id is not what it is addressed
           to any more and is null on anything new (migration
           2024_01_22_000001). The question an officer is asking has not
           changed - which assistance concerns my members - so it is asked
           through the designated farmers instead.

           One allocation can appear on several associations' lists at once,
           which is correct: one batch really can concern several. */
        $allocations = AssistanceAllocation::query()
            ->whereHas('beneficiaries.farmer',
                fn ($farmer) => $farmer->where('association_id', $association->id))
            ->with(['allocatedBy'])
            /* No withSum of quantities: the Remaining column went with the
               figures (Oct 2026). Two counts replace it, and they answer
               the same question about people rather than goods - how many
               members the office designated, and how many of them it has
               handed to. Counts, not sums, so nothing here is a figure
               about any individual.

               Both are scoped to THIS association's members. A batch can
               span several associations, so an unscoped count would tell an
               officer "4 of 30 designated" when only three of those thirty
               are theirs, which is both wrong for them and more than they
               should see of anybody else's. */
            ->withCount([
                'beneficiaries' => fn ($q) => $q->whereHas('farmer',
                    fn ($f) => $f->where('association_id', $association->id)),
                'distributions' => fn ($q) => $q->whereHas('farmer',
                    fn ($f) => $f->where('association_id', $association->id)),
            ])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            // Anything still open comes first: that is assistance the office
            // has set aside for this association's members and not yet
            // handed over.
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
     * One allocation: what it is, which members were designated, and who has
     * been handed theirs.
     */
    public function show(AssistanceAllocation $allocation)
    {
        $association = $this->association();

        $allocation->load([
            'allocatedBy',
            'beneficiaries.farmer.barangay',
            'distributions.farmer', 'distributions.damageReport',
            'distributions.distributedBy', 'distributions.photos',
        ]);

        /* The ownership check, rewritten for the same reason as index().
           assertBelongsToAssociation($allocation->association_id) used to
           do this, and it cannot any more: that column is null on a new
           allocation, so every officer would have been refused. An officer
           may open an allocation their association has a member on, and no
           other. */
        abort_unless(
            $allocation->beneficiaries->contains(
                fn ($beneficiary) => $beneficiary->farmer?->association_id === $association->id
            ),
            403
        );

        /* AND THEY SEE ONLY THEIR OWN MEMBERS ON IT

           A batch can span several associations, so the rows have to be
           filtered as well as the page gated. Without this, opening a
           shared allocation would show an officer the names of farmers in
           other associations, which is exactly the leak the rest of this
           role is careful to avoid. */
        $allocation->setRelation('beneficiaries', $allocation->beneficiaries->filter(
            fn ($beneficiary) => $beneficiary->farmer?->association_id === $association->id
        )->values());

        $allocation->setRelation('distributions', $allocation->distributions->filter(
            fn ($distribution) => $distribution->farmer?->association_id === $association->id
        )->values());

        return view('association.assistance.show', [
            'association' => $association,
            'allocation'  => $allocation,
        ]);
    }
}
