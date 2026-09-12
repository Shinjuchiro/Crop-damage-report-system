<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password?" for every role. Nothing role-specific here - the email
 * and password columns both live on `users`, shared by Farmer, Technician,
 * Association and MAO alike, so one flow covers all four. Everything below
 * is Laravel's own password-broker (config/auth.php's "passwords" section,
 * the password_reset_tokens table) - this controller just wires up the two
 * screens and keeps an audit trail, matching every other flow in the system
 * (section 13: "Write an audit_logs row for every ... login" and friends).
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

        $status = Password::sendResetLink($request->only('email'));

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
}
