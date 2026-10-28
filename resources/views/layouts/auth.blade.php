<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Crop Damage Reporting and Assistance Allocation System')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/tanza-seal.png') }}">

    {{-- Runs before the body paints. If the splash has already played this
         session, the document is marked and the CSS in the splash partial
         never shows it, rather than it appearing and being hidden a moment
         later. Kept inline and tiny on purpose: anything loaded over the
         network here would be too late to matter. --}}
    <script>
        try {
            if (sessionStorage.getItem('tf-splash-done')) {
                document.documentElement.className += ' splash-done';
            }
        } catch (e) { /* private browsing: let it play */ }
    </script>

    @include('layouts.partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Hides anything marked x-cloak from the very first paint.

         This rule also lives in resources/css/app.css, but that is not enough
         on its own: while `npm run dev` is running (public/hot exists) Vite
         injects the stylesheet from JavaScript AFTER the page has painted,
         and until it lands every closed modal on the page is drawn
         unstyled, in flow at the bottom, with no dim behind it. It then
         snaps away or into place when the CSS arrives. That was the "modal
         appears below, then jumps, and the background is not dimmed for a
         second". Inline here, it is in force before anything else loads. --}}
    <style>[x-cloak] { display: none !important; }</style>
    @stack('head')
</head>
<body class="min-h-screen bg-white font-sans antialiased">

{{-- The green closes in, the seal drops into the middle, and it clears.
     Sign-in screens only - see the note in the partial for why it is not
     in the app layout. --}}
@include('layouts.partials.splash')

{{-- And underneath it, once per device, what the system says for itself
     before anyone has signed in. It is deliberately included AFTER the
     splash: the splash is z-index 9999 and this is 9998, so this is already
     painted while the green is still up and is simply what is there when
     the green clears. No timer, and nothing to keep in step. --}}
@include('layouts.partials.welcome')

@yield('content')

@include('layouts.partials.confirm-dialog')

@stack('scripts')
</body>
</html>
