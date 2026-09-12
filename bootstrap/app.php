<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
