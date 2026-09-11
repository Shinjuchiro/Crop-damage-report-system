<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    public $timestamps = false;

    protected $fillable = ['notification_broadcast_id', 'user_id', 'is_read', 'sms_status', 'created_at'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }

    public function broadcast()
    {
        return $this->belongsTo(NotificationBroadcast::class, 'notification_broadcast_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
