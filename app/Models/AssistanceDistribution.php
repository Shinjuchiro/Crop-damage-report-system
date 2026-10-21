<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceDistribution extends Model
{
    protected $table = 'assistance_distributions';

    /* `in_kind_description` and `quantity` came out of this list in Oct
       2026, with every other figure in the module: how much a named farmer
       received is a financial detail about an identifiable person, and the
       client asked for it out of the system (see App\Models\Assistance).

       The columns are still on the table and still hold whatever was
       recorded before the change. They are simply not writable and nothing
       displays them. */
    protected $fillable = [
        'assistance_allocation_id', 'farmer_id', 'damage_report_id',
        'distributed_by', 'distributed_at',
        'remarks', 'distribution_evidence_path',
        'distribution_status', 'receipt_status', 'receipt_note', 'receipt_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'distributed_at' => 'datetime',
            'receipt_confirmed_at' => 'datetime',
        ];
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

    public function distributedBy()
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }

    public function photos()
    {
        return $this->hasMany(AssistanceDistributionPhoto::class);
    }

    public function receiptPhotos()
    {
        return $this->hasMany(AssistanceDistributionPhoto::class)->where('photo_type', 'receipt');
    }

    public function nonReceiptPhotos()
    {
        return $this->hasMany(AssistanceDistributionPhoto::class)->where('photo_type', 'non_receipt');
    }
}
