<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'target_table', 'target_id', 'created_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
