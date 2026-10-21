<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletable;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, SoftDeletable;

    protected $table = 'users';

    /**
     * `role` is deliberately NOT in this list.
     *
     * Everything here can be written in bulk from an array, which is what
     * User::create($array) and $user->update($array) do. That is safe only
     * for as long as no such array is ever built straight out of a request.
     * `role` decides whether an account is a farmer or the MAO super admin,
     * so leaving it fillable means one future $request->all() anywhere in the
     * app turns an extra form field into a privilege escalation.
     *
     * The three places that legitimately set it (farmer registration, and
     * MAO creating a technician or an association account) assign it as a
     * plain property instead, which bypasses this list by design and reads
     * as the deliberate act it is.
     */
    protected $fillable = [
        'username', 'full_name', 'email', 'password', 'phone_number',
        'status', 'preferred_language', 'google_id', 'profile_photo_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'deleted_at' => 'datetime'];
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

    /**
     * The phone number with everything but the last four digits hidden
     * (added Oct 2026).
     *
     * WHY THIS EXISTS
     *
     * Proposal section 91.11 already asks for exactly this before an SMS is
     * sent: "Phone: ********1234". The SMS review dialog honoured it and
     * then the SMS History page printed every recipient's number in full,
     * for every text the office had ever sent, twenty rows to a page. That
     * is revision section 3.1's "personal contact information" in a summary
     * table, and section 3.4's "avoid unnecessary exposure", on the one
     * screen where contact details are least needed: the office is reading
     * a delivery log, not phoning anybody from it.
     *
     * The last four digits stay, because the one legitimate reason to look
     * at a number here is "did it go to the right phone?", and four digits
     * answer that without publishing the rest.
     *
     * WHY ON THE MODEL
     *
     * auth/verify-otp.blade.php had already worked this out inline, and a
     * second inline copy is how two screens end up masking differently. One
     * method decides, the same way getDisplayNameAttribute does.
     */
    public function getMaskedPhoneNumberAttribute(): ?string
    {
        $phone = trim((string) $this->phone_number);

        if ($phone === '') {
            return null;
        }

        /* Shorter than five digits is not a real number, so there is
           nothing safe to reveal: mask the whole thing rather than show
           most of it. */
        return strlen($phone) > 4
            ? str_repeat('•', strlen($phone) - 4) . substr($phone, -4)
            : str_repeat('•', strlen($phone));
    }

    /**
     * The uploaded profile photo's public URL, or null when the account has
     * none and the initials avatar should show instead. See
     * App\Http\Controllers\Concerns\ManagesProfilePhoto.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    /**
     * Send the branded reset-password email instead of the framework's
     * generic default. See App\Notifications\ResetPasswordNotification and
     * App\Http\Controllers\Auth\PasswordResetController.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
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
