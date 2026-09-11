<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/**
 * Google login is a SECONDARY convenience option for FARMERS only.
 * Technician / Association / MAO accounts are created by the MAO and use
 * username + password, so they are not permitted through this path.
 */
class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['login' => 'Google sign-in failed. Please try again or use your username and password.']);
        }

        // Already linked to a Google account
        $user = User::where('google_id', $googleUser->getId())->first();

        // Otherwise try to match an existing local account by email and link it
        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        }

        // No account at all - we do NOT silently create one, because farmer
        // registration collects farm/association details we cannot get from Google.
        if (! $user) {
            return redirect()->route('register')
                ->withErrors(['login' => 'No account found for that Google address. Please complete the registration form first.']);
        }

        if ($user->role !== 'farmer') {
            return redirect()->route('login')
                ->withErrors(['login' => 'Google sign-in is only available for farmer accounts. Please use your username and password.']);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(LoginController::redirectForRole($user->role));
    }
}
