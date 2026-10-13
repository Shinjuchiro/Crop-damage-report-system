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
    @stack('head')
</head>
<body class="min-h-screen bg-white font-sans antialiased">

{{-- The green closes in, the seal drops into the middle, and it clears.
     Sign-in screens only - see the note in the partial for why it is not
     in the app layout. --}}
@include('layouts.partials.splash')

@yield('content')

@include('layouts.partials.confirm-dialog')

@stack('scripts')
</body>
</html>
