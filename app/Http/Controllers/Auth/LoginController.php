<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * REMEMBER ME, AND THE TWO DIFFERENT THINGS IT DOES
     *
     * Ticking the box does two separate jobs, because asking somebody to
     * tick two boxes to mean one thing is asking them to think about our
     * implementation.
     *
     * 1. It keeps them SIGNED IN across browser restarts, which is Laravel's
     *    own remember-me: the second argument to Auth::attempt() below.
     *    That one deliberately ends when they sign out - Auth::logout()
     *    regenerates the user's remember token - because on a shared office
     *    computer, signing out has to actually sign you out.
     *
     * 2. It remembers their USERNAME on this device, which is the cookie
     *    below, and that one DOES survive signing out. This is the part a
     *    farmer actually notices: come back to the login page and only the
     *    password is left to type.
     *
     * Never the password. Only the name they type in the first field.
     *
     * The cookie is encrypted by Laravel's own EncryptCookies middleware
     * and is marked HTTP-only, so no script on the page can read it. It is
     * still a name sitting on a shared machine, which is exactly why it is
     * opt-in: unticking the box on any later sign-in forgets it again.
     */
    private const REMEMBERED_LOGIN = 'tf_remembered_login';

    /** A year. A device nobody has signed in on for a year should not still
     *  be offering somebody's name. */
    private const REMEMBERED_LOGIN_MINUTES = 60 * 24 * 365;

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

        // Point 2 in the note above. Queued rather than set directly so it
        // rides out on the redirect below; forgotten rather than left alone
        // when the box is unticked, so unticking is a real way to opt out
        // on a machine somebody has stopped trusting.
        if ($request->boolean('remember')) {
            Cookie::queue(
                self::REMEMBERED_LOGIN,
                $credentials['login'],
                self::REMEMBERED_LOGIN_MINUTES,
                null, null, null,
                true   // HTTP-only: the page's own scripts cannot read it
            );
        } else {
            Cookie::queue(Cookie::forget(self::REMEMBERED_LOGIN));
        }

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

        // The remembered username cookie is deliberately left alone here.
        // Signing out ends the session and Laravel's own remember token,
        // but the next person to open this page is almost always the same
        // farmer on the same phone, and having their username already in
        // the field is the whole reason they ticked the box. Unticking it
        // on a later sign-in is what forgets it.
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
