<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One farmer MAO selected while allocating a pool to an association.
 *
 * This is MAO's own record of intent, kept separate from
 * AssistanceDistribution (the association's later, real hand-out) on
 * purpose - see the migration that creates this table for why.
 */
class AssistanceAllocationBeneficiary extends Model
{
    protected $table = 'assistance_allocation_beneficiaries';
    public $timestamps = false;

    protected $fillable = ['assistance_allocation_id', 'farmer_id', 'damage_report_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function allocation()
    {
        return $this->belongsTo(AssistanceAllocation::class, 'assistance_allocation_id');
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }
}
