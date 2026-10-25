<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceAllocation extends Model
{
    protected $table = 'assistance_allocations';

    /* NO TYPE, NO QUANTITIES (Oct 2026, client decision)

       See the note on App\Models\Assistance for why: how much a named
       farmer received is a financial detail about an identifiable person,
       and it is out of this system by the client's own request.

       `in_kind_description` held the free-typed name of an allocation not
       linked to a catalogue item. It came out of this list when the
       assistance item itself went: an allocation does not name what is
       being given at all now, so there is no name to type.

       `assistance_id`, `association_id`, `disaster_id` and `crop_id` are
       all still here even though store() no longer sets any of them. They
       are what rows created BEFORE this change recorded, those rows are
       still read, and leaving a column writable costs nothing while taking
       one out of the list is the kind of change that quietly breaks a
       restore or a seeder. Nothing writes them; the columns simply stay
       available.

       `type`, `allocated_quantity` and `distributed_quantity` are left on
       the table and OUT of this list, so nothing can write them and no
       screen reads them. */
    protected $fillable = [
        'assistance_id', 'association_id', 'disaster_id', 'crop_id',
        'allocated_by', 'allocated_at', 'distributed_to_association_at', 'distributed_by',
        'remarks', 'supporting_evidence_path', 'status',
        // Create Assistance Distribution (Oct 2026): a named batch with a
        // free-text description and an optional date and venue.
        'batch_name', 'details', 'distribution_date', 'venue',
        // PAY-<year>-<seq>, assigned by the system when the batch is finalised.
        'payroll_no',
    ];

    protected function casts(): array
    {
        return [
            'allocated_at' => 'datetime',
            'distributed_to_association_at' => 'datetime',
            'distribution_date' => 'date',
        ];
    }

    public function assistance()
    {
        return $this->belongsTo(Assistance::class);
    }

    public function association()
    {
        return $this->belongsTo(Association::class);
    }

    public function disaster()
    {
        return $this->belongsTo(Disaster::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function distributedBy()
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }

    public function distributions()
    {
        return $this->hasMany(AssistanceDistribution::class);
    }

    /**
     * The farmers MAO selected while allocating. Informational - see
     * AssistanceAllocationBeneficiary and the migration that creates its
     * table for why this never turns into a distribution by itself.
     */
    public function beneficiaries()
    {
        return $this->hasMany(AssistanceAllocationBeneficiary::class);
    }

    public function documents()
    {
        return $this->hasMany(AssistanceAllocationDocument::class);
    }

    /* getIsCashAttribute() was removed with the cash/in-kind distinction.
       Nothing in the system now asks whether an allocation is cash,
       because nothing displays or records a figure either way. Every
       screen that used to branch on it now prints the same thing for
       every allocation. */

    /**
     * What to show for this allocation. Always the one word.
     *
     * IT USED TO NAME THE ITEM (changed Oct 2026, client decision)
     *
     * It returned the catalogue item's name, or a free-typed one, so an
     * allocation read "Rice Seeds" or "Fertilizer". The client asked for
     * the item to go: an allocation says that assistance is being given and
     * nothing more, the same way it no longer says cash or in-kind and no
     * longer carries a figure.
     *
     * Nothing is destroyed by this. assistance_id and in_kind_description
     * still hold whatever older rows recorded, so if the office ever wants
     * the names back it is this method that changes, not the data.
     *
     * Kept as an accessor rather than replaced with the literal string at
     * every call site, so that remains true: one method decides, and about
     * twenty screens read it.
     */
    /**
     * The next payroll number for the year: PAY-2026-0001, 0002, ...
     *
     * Call inside the transaction that creates the batch. The row lock keeps
     * two batches finalised at the same moment from reading the same last
     * number, and the unique index on payroll_no is the backstop if that
     * ever fails anyway.
     */
    public static function nextPayrollNo(?int $year = null): string
    {
        $year   = $year ?? (int) now()->year;
        $prefix = 'PAY-' . $year . '-';

        $last = static::where('payroll_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('payroll_no')
            ->value('payroll_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function getDisplayNameAttribute(): string
    {
        // A batch made on the new form has a name. Older rows have none and
        // keep reading "Assistance".
        return $this->batch_name ?: 'Assistance';
    }

    /**
     * The crops the designated farmers' damage reports are about.
     *
     * A batch is not tied to one crop any more (it names farmers, not a
     * crop), so "Crop" on its pages is worked out from what the designated
     * reports actually say. Needs beneficiaries.damageReport.crops.crop
     * loaded.
     */
    public function getConcernedCropsAttribute()
    {
        return $this->beneficiaries
            ->flatMap(fn ($beneficiary) => $beneficiary->damageReport?->crops ?? collect())
            ->map(fn ($reportCrop) => $reportCrop->crop)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * The associations an allocation concerns: the ones the designated
     * farmers belong to.
     *
     * This replaces the association_id column as the answer to "who is this
     * for" (migration 2024_01_22_000001). An allocation is a batch of
     * farmers now, chosen from across the municipality, so it can concern
     * several associations at once, which a single column could not say.
     *
     * association_id itself is kept and is still readable: on a row created
     * before this change it records the one association the allocation was
     * addressed to, and that is a true fact about it. On a new row it is
     * null.
     *
     * Needs beneficiaries.farmer.association loaded; a caller that does not
     * load them pays a query per beneficiary.
     */
    public function getConcernedAssociationsAttribute()
    {
        return $this->beneficiaries
            ->map(fn ($beneficiary) => $beneficiary->farmer?->association)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
