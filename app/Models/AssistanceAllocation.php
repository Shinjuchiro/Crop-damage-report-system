<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceAllocation extends Model
{
    protected $table = 'assistance_allocations';

    protected $fillable = [
        'assistance_id', 'association_id', 'disaster_id', 'crop_id',
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
}
