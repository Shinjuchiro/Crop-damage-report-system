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
        // $timestamps is false below (this table only has created_at, not
        // updated_at, and writes it manually), which also turns off
        // Eloquent's automatic "treat created_at as a date" behaviour that
        // normally comes bundled with timestamps. Without this line,
        // created_at stays a plain string from the database, and every
        // notification bell (Farmer/Technician/Association/MAO all read
        // this same model) throws "Call to a member function format() on
        // string" the moment it tries to display a notification's date.
        return [
            'is_read'    => 'boolean',
            'created_at' => 'datetime',
        ];
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
