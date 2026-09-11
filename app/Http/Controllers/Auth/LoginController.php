<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Allow logging in with EITHER username or email in the same field
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $attempt = Auth::attempt(
            [$field => $credentials['login'], 'password' => $credentials['password']],
            $request->boolean('remember')
        );

        if (! $attempt) {
            throw ValidationException::withMessages([
                'login' => 'The username/email or password you entered is incorrect.',
            ]);
        }

        $request->session()->regenerate(); // prevents session fixation

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'Logged in',
            'created_at' => now(),
        ]);

        return redirect()->intended($this->redirectForRole(Auth::user()->role));
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
