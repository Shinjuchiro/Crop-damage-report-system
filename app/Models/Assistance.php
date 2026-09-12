<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;

class Assistance extends Model
{
    use SoftDeletable;

    protected $table = 'assistances'; // explicit - "assistance" does not pluralize predictably

    protected $fillable = [
        'name', 'type', 'description', 'disaster_id', 'crop_id',
        'available_quantity_or_amount', 'status',
    ];

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }

    public function disaster()
    {
        return $this->belongsTo(Disaster::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function allocations()
    {
        return $this->hasMany(AssistanceAllocation::class);
    }

    // Remaining pool = available minus everything already allocated (computed, not stored)
    public function getRemainingQuantityAttribute(): ?float
    {
        if ($this->available_quantity_or_amount === null) {
            return null; // not tracked for cash-type assistance
        }
        return (float) $this->available_quantity_or_amount
            - (float) $this->allocations()->sum('allocated_quantity');
    }
}
