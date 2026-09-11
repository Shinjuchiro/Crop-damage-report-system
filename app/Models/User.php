<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username', 'full_name', 'email', 'password', 'phone_number', 'role',
        'status', 'preferred_language', 'google_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    /**
     * Best available display name: the stored name, then the farmer profile,
     * then the username as a last resort.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name
            ?: ($this->farmer?->full_name ?: $this->username);
    }

    // Role-specific profile (only one of these will be non-null depending on role)
    public function farmer()
    {
        return $this->hasOne(Farmer::class);
    }

    public function associationOfficer()
    {
        return $this->hasOne(AssociationOfficer::class);
    }

    // Technician-side relationships
    public function assignedReports()
    {
        return $this->hasMany(DamageReport::class, 'assigned_technician_id');
    }

    public function validations()
    {
        return $this->hasMany(Validation::class, 'technician_id');
    }

    // MAO-side relationships
    public function approvedReports()
    {
        return $this->hasMany(DamageReport::class, 'approved_by');
    }

    public function allocationsMade()
    {
        return $this->hasMany(AssistanceAllocation::class, 'allocated_by');
    }

    public function notificationsReceived()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
