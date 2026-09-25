<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], [
            'login' => 'username or email',
        ]);

        $this->ensureIsNotRateLimited($request);

        // Allow logging in with EITHER username or email in the same field
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $attempt = Auth::attempt(
            [$field => $credentials['login'], 'password' => $credentials['password']],
            $request->boolean('remember')
        );

        if (! $attempt) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'login' => 'The username/email or password you entered is incorrect.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate(); // prevents session fixation

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'Logged in',
            'created_at' => now(),
        ]);

        return redirect()->intended($this->redirectForRole(Auth::user()->role));
    }

    /**
     * Five wrong passwords for one account, then a pause. Without this the
     * MAO super admin account can be guessed at forever, with no lockout and
     * nothing in the logs to notice.
     *
     * Keyed on the username being tried rather than on the IP address.
     * bootstrap/app.php trusts every proxy, which it has to because Railway
     * terminates HTTPS at its edge, and the price of that is that
     * $request->ip() is simply whatever X-Forwarded-For the caller chose to
     * send. An IP-keyed limiter would be stepped around by changing one
     * header per request. The account name cannot be rotated that way: it is
     * the thing being attacked.
     */
    private function throttleKey(Request $request): string
    {
        return 'login|' . Str::lower((string) $request->input('login'));
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $minutes = max(1, (int) ceil(RateLimiter::availableIn($this->throttleKey($request)) / 60));

        throw ValidationException::withMessages([
            'login' => "Too many login attempts for this account. Please try again in {$minutes} minute(s).",
        ]);
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            AuditLog::create([
                'user_id'    => Auth::id(),
                'action'     => 'Logged out',
                'created_at' => now(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * One login page for everyone - the system decides where to send them.
     */
    public static function redirectForRole(string $role): string
    {
        return match ($role) {
            'mao'         => route('mao.dashboard'),
            'technician'  => route('technician.dashboard'),
            'association' => route('association.dashboard'),
            'farmer'      => route('farmer.dashboard'),
            default       => route('login'),
        };
    }
}
