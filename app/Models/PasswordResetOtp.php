<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A one-time 6-digit code texted to a farmer (or any user) who chose "Text
 * me a code" on the Forgot Password screen, instead of an emailed reset
 * link. See App\Http\Controllers\Auth\PasswordResetController.
 *
 * Deliberately its own small model rather than reusing Laravel's built-in
 * password broker (Password::sendResetLink() / Password::reset(), used by
 * the email side of this same screen) - the broker is built entirely
 * around a long token clicked from a link, not a short code a person types
 * in by hand, so it has no idea of "wrong code" attempts or locking a code
 * out after too many guesses.
 */
class PasswordResetOtp extends Model
{
    /** How long a texted code stays valid before it must be re-requested. */
    public const VALID_MINUTES = 10;

    /** Wrong guesses allowed before the code is locked and must be re-sent. */
    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['user_id', 'otp_hash', 'expires_at', 'consumed_at', 'attempts'];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a fresh code for this user, replacing any still-pending one.
     *
     * Returns the raw 6-digit code - the only moment it exists outside a
     * hash - so the caller can text it. It is never written to the
     * database or a log in this form.
     */
    public static function generateFor(User $user): string
    {
        // Only one live code per user at a time: an old unconsumed code
        // becomes worthless the moment a new one is requested, so a farmer
        // who taps "Resend code" can't be confused by which text is current.
        static::where('user_id', $user->id)->whereNull('consumed_at')->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        static::create([
            'user_id'    => $user->id,
            'otp_hash'   => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(self::VALID_MINUTES),
            'attempts'   => 0,
        ]);

        return $code;
    }

    /**
     * The current live (unconsumed, unexpired, unlocked) code for a user,
     * if any - what the verify screen checks a typed-in code against.
     */
    public static function currentFor(User $user): ?self
    {
        return static::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest()
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isLocked(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->otp_hash);
    }
}
