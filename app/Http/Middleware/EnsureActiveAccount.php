<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Farmers register with status = 'pending' and must be approved by the MAO.
 * Until then they can log in, but only see the "pending approval" holding screen.
 * (This is Option A from the design: account created immediately, limited access.)
 */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->status === 'pending') {
            return redirect()->route('account.pending');
        }

        if (in_array($user->status, ['inactive', 'rejected'], true)) {
            return redirect()->route('account.blocked');
        }

        return $next($request);
    }
}
