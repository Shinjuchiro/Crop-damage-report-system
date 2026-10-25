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

    protected $fillable = ['assistance_allocation_id', 'farmer_id', 'damage_report_id', 'payroll_line', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * The farmer's own payroll number: the batch's PAY-2026-0003 plus their
     * line on it, so PAY-2026-0003-007. Needs `allocation` loaded; null when
     * either part is missing.
     */
    public function getPayrollRefAttribute(): ?string
    {
        $batch = $this->allocation?->payroll_no;

        return ($batch && $this->payroll_line)
            ? $batch . '-' . str_pad((string) $this->payroll_line, 3, '0', STR_PAD_LEFT)
            : null;
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
