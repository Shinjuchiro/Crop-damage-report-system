<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceDistribution extends Model
{
    protected $table = 'assistance_distributions';

    protected $fillable = [
        'assistance_allocation_id', 'farmer_id', 'damage_report_id',
        'in_kind_description', 'quantity', 'distributed_by', 'distributed_at',
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
