<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An unfinished Create Assistance Distribution form, kept so the officer can
 * leave and come back. Never counted anywhere and never notifies anybody:
 * it becomes an AssistanceAllocation only when it is finalized.
 */
class AssistanceAllocationDraft extends Model
{
    protected $table = 'assistance_allocation_drafts';

    protected $fillable = [
        'user_id', 'batch_name', 'details', 'distribution_date', 'venue', 'farmer_ids',
    ];

    protected function casts(): array
    {
        return [
            'distribution_date' => 'date',
            'farmer_ids'        => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
