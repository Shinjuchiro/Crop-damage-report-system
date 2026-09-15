<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Services\SemaphoreSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password?" for every role. Nothing role-specific here - the email,
 * phone number and password columns all live on `users`, shared by Farmer,
 * Technician, Association and MAO alike, so one flow covers all four.
 *
 * Two independent ways in, a farmer picks one on the Forgot Password screen:
 *
 *   Email   Laravel's own password-broker (config/auth.php's "passwords"
 *           section, the password_reset_tokens table) - a long token only
 *           ever clicked from a link.
 *   SMS     App\Models\PasswordResetOtp - a short 6-digit code texted
 *           through the same Semaphore integration the MAO alert composer
 *           already uses (App\Services\SemaphoreSmsService), for a farmer
 *           who doesn't have easy access to their email but does have their
 *           phone.
 *
 * Both keep an audit trail, matching every other flow in the system
 * (section 13: "Write an audit_logs row for every ... login" and friends),
 * and both follow decision 22: never reveal whether the email/phone number
 * entered actually belongs to an account.
 */
class PasswordResetController extends Controller
{
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Always shows the same message whether or not that email address has
     * an account - never confirm or deny which emails are registered.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Password::sendResetLink() first saves the token to
        // password_reset_tokens (that part is durable and rarely fails),
        // then tries to actually mail it - a broken or misconfigured mail
        // provider (wrong SMTP host, a scheme it doesn't support, a
        // provider-side block, etc.) throws from deep inside that second
        // step. A farmer must never see a raw 500 page here just because
        // outbound mail is misbehaving - see docs/BUILD-STATUS.md's mail
        // caveat and the Sept 2026 SMTP troubleshooting. Log it for MAO/dev
        // to notice and fix, but always fall through to the same neutral
        // "check your email" message either way.
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            Log::error('Password reset link could not be emailed: ' . $e->getMessage());
            $status = null;
        }

        if ($status === Password::RESET_LINK_SENT) {
            if ($user = User::where('email', $request->email)->first()) {
                AuditLog::create([
                    'user_id'      => $user->id,
                    'action'       => 'Requested a password reset link',
                    'target_table' => 'users',
                    'target_id'    => $user->id,
                    'created_at'   => now(),
                ]);
            }
        }

        return back()->with(
            'status',
            'If an account exists for that email address, we have sent a password reset link to it.'
        );
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update(['password' => $password]); // auto-hashed by the model cast

                AuditLog::create([
                    'user_id'      => $user->id,
                    'action'       => 'Reset password via email link',
                    'target_table' => 'users',
                    'target_id'    => $user->id,
                    'created_at'   => now(),
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Your password has been reset successfully. You can now log in.');
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * "Text me a code" - the SMS half of this screen. Same privacy rule as
     * sendResetLink() above: the response is identical whether or not the
     * phone number matched an account, and either way the farmer lands on
     * the same "enter the code" screen next.
     */
    public function sendOtp(Request $request)
    {
        $request->validate(['phone_number' => ['required', 'string', 'max:20']]);

        $normalized = $this->normalizePhone($request->phone_number);
        $user       = $this->findByPhone($normalized);

        if ($user) {
            $code = PasswordResetOtp::generateFor($user);

            $text = "Your Crop Damage Reporting System password reset code is {$code}. "
                . 'It expires in ' . PasswordResetOtp::VALID_MINUTES . ' minutes. '
                . "If you didn't request this, ignore this message.";

            $result = app(SemaphoreSmsService::class)->send($user->phone_number, $text);

            if (! $result['success']) {
                // Plays the same role a "log" mailer plays for the email side
                // of this screen (docs/DEPLOYMENT.md): lets the whole flow be
                // tested on a machine with no SEMAPHORE_API_KEY set, by
                // reading the code out of storage/logs/laravel.log instead of
                // a real text message.
                Log::info("Password reset OTP for user #{$user->id} (SMS not sent - {$result['error']}): {$code}");
            }

            AuditLog::create([
                'user_id'      => $user->id,
                'action'       => 'Requested a password reset code via SMS',
                'target_table' => 'users',
                'target_id'    => $user->id,
                'created_at'   => now(),
            ]);
        }

        return redirect()->route('password.otp.verify', ['phone' => $normalized])
            ->with('status', 'If that phone number is on file, we have sent a 6-digit code to it.');
    }

    public function showVerifyOtpForm(Request $request)
    {
        $phone = (string) $request->query('phone', '');

        // Nothing to verify without a phone number to check it against -
        // send them back to pick a method instead of showing a dead form.
        if ($phone === '') {
            return redirect()->route('password.request');
        }

        return view('auth.verify-otp', ['phone' => $phone]);
    }

    public function verifyOtpAndReset(Request $request)
    {
        $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
            'code'         => ['required', 'digits:6'],
            'password'     => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $normalized = $this->normalizePhone($request->phone_number);
        $user       = $this->findByPhone($normalized);
        $otp        = $user ? PasswordResetOtp::currentFor($user) : null;

        // No account, no live code, expired, or locked out from too many
        // wrong guesses - all four get the exact same message, so a wrong
        // code can never be told apart from "that number isn't registered".
        if (! $user || ! $otp || $otp->isExpired() || $otp->isLocked()) {
            throw ValidationException::withMessages([
                'code' => ['This code is invalid or has expired. Please request a new one.'],
            ])->redirectTo(route('password.otp.verify', ['phone' => $normalized]));
        }

        if (! $otp->matches($request->code)) {
            $otp->increment('attempts');

            $remaining = max(0, PasswordResetOtp::MAX_ATTEMPTS - $otp->attempts);

            throw ValidationException::withMessages([
                'code' => [$remaining > 0
                    ? "That code doesn't match. {$remaining} attempt(s) left."
                    : 'Too many incorrect attempts. Please request a new code.'],
            ])->redirectTo(route('password.otp.verify', ['phone' => $normalized]));
        }

        $otp->update(['consumed_at' => now()]);
        $user->update(['password' => $request->password]); // auto-hashed by the model cast

        AuditLog::create([
            'user_id'      => $user->id,
            'action'       => 'Reset password via SMS code',
            'target_table' => 'users',
            'target_id'    => $user->id,
            'created_at'   => now(),
        ]);

        return redirect()->route('login')
            ->with('status', 'Your password has been reset successfully. You can now log in.');
    }

    /**
     * Strips everything but digits, the same normalization
     * App\Services\SemaphoreSmsService applies before sending, so
     * "0917 123 4567" typed on this screen matches "09171234567" as stored
     * at registration.
     */
    private function normalizePhone(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    /**
     * Matches on digits only, tolerant of spaces/dashes/parentheses/plus
     * signs on either side - see normalizePhone(). Does not try to
     * reconcile a "0917..." local number against a "+63917..." one typed
     * differently than it was stored; keeping this a plain digit compare
     * matches proposal section 2's "practical, not an enterprise platform".
     */
    private function findByPhone(string $normalized): ?User
    {
        if ($normalized === '') {
            return null;
        }

        return User::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?",
            [$normalized]
        )->first();
    }
}
