<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Crop Damage Reporting and Assistance Allocation System')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/tanza-seal.png') }}">
    @include('layouts.partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-white font-sans antialiased">

@yield('content')

@include('layouts.partials.confirm-dialog')

@stack('scripts')
</body>
</html>
