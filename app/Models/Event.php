<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'title', 'description', 'event_date', 'start_time', 'end_time',
        'venue', 'organizer', 'target_type', 'target_id',
        'priority', 'attachment_path', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
