<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
                $middleware->alias([
            'role'   => \App\Http\Middleware\EnsureRole::class,
            'active' => \App\Http\Middleware\EnsureActiveAccount::class,
        ]);

        // Railway (like Heroku, Render, Fly.io) terminates HTTPS at its edge
        // and forwards plain HTTP to the container, with an X-Forwarded-Proto
        // header saying the original request was HTTPS. Without trusting
        // that header, Laravel thinks every request is HTTP - which makes
        // asset(), Vite's @vite() directive, and url() all generate
        // "http://" links even on a site only ever reached over HTTPS,
        // which browsers then block as mixed content (the CSS/JS never
        // load). "*" trusts whichever proxy forwarded the request, which is
        // the right call here since the container is never reachable
        // directly - only through Railway's own edge.
        $middleware->trustProxies(at: '*');

        // This is the actual cause of the ERR_TOO_MANY_REDIRECTS reports -
        // it was never the service worker. Laravel's built-in "guest"
        // middleware (Illuminate\Auth\Middleware\RedirectIfAuthenticated),
        // which guards /login, /register and the whole forgot-password
        // group in routes/web.php, sends an already-logged-in visitor to
        // whichever named route is called "dashboard" or "home" - and this
        // app has neither (every dashboard is role-specific: mao.dashboard,
        // farmer.dashboard, etc). Its fallback for that case is "/", and
        // "/" itself sits inside that same guest-only route group, so the
        // fallback sends the browser straight back through the same
        // "are you logged in?" check, forever - a genuine infinite loop,
        // not a caching artifact. It fires for anyone who still has a
        // valid session and opens /login, /register, or a password-reset
        // link: a stale bookmark, an old email link, Messenger's in-app
        // browser carrying a cookie from an earlier visit, or (since a
        // recent fix) the installed app's own start_url. Pointing it at
        // the same role-based dashboard LoginController already sends
        // people to after signing in fixes it for good, since that target
        // is never itself a guest route.
        RedirectIfAuthenticated::redirectUsing(
            fn () => LoginController::redirectForRole(Auth::user()->role)
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
