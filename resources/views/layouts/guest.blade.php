<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Crop Damage Reporting and Assistance Allocation System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans antialiased">

    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">

        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-green-800">
                <svg class="h-9 w-9 text-lime-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 21c0-6 3-10 8-12-1 6-4 9-8 12zM12 21c0-5-2.5-8-7-10 1 5 3 8 7 10z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-green-900 sm:text-2xl">Crop Damage Reporting</h1>
            <p class="text-sm text-slate-600">and Assistance Allocation System &mdash; Tanza, Cavite</p>
        </div>

        <div class="w-full {{ $wide ?? false ? 'max-w-3xl' : 'max-w-md' }}">
            @yield('content')
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">
            Municipal Agriculture Office &mdash; Tanza, Cavite
        </p>
    </div>

</body>
</html>
