<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceAllocation extends Model
{
    protected $table = 'assistance_allocations';

    protected $fillable = [
        'assistance_id', 'type', 'association_id', 'disaster_id', 'crop_id',
        'in_kind_description', 'allocated_quantity', 'distributed_quantity',
        'allocated_by', 'allocated_at', 'distributed_to_association_at', 'distributed_by',
        'remarks', 'supporting_evidence_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'allocated_at' => 'datetime',
            'distributed_to_association_at' => 'datetime',
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

    /**
     * Whether this allocation is cash. `type` is what this is actually
     * decided from now (an allocation no longer has to link to a catalogue
     * row at all - see the Sept 2026 "no more create a new item" change to
     * the Allocate Assistance modal). Falling back to the linked catalogue
     * item's own type only covers a row from before `type` existed that
     * somehow never got backfilled.
     */
    public function getIsCashAttribute(): bool
    {
        return ($this->type ?? $this->assistance?->type) === 'cash';
    }

    /**
     * What to actually show for this allocation: the catalogue item's name
     * when it is linked to one, otherwise the free-typed description
     * (In-Kind "Other"), otherwise a generic label for its type. Never
     * null, so every place that used to chain
     * `$allocation->assistance?->name ?? $allocation->in_kind_description ?? 'Assistance'`
     * can just read this instead.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->assistance?->name
            ?? $this->in_kind_description
            ?? ($this->is_cash ? 'Cash Assistance' : 'Assistance');
    }
}
