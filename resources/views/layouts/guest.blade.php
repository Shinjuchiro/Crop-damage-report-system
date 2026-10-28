<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Crop Damage Reporting and Assistance Allocation System')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/tanza-seal.png') }}">
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
    {{-- Text size for the whole system: about 16% larger than the browser
         default. Everything is sized in rem, so changing it here scales the
         text and the spacing together. It is inline so it takes effect
         without rebuilding the stylesheet. --}}
    <style>html { font-size: 116%; }</style>
</head>
<body class="min-h-screen font-sans antialiased" style="background:#0d3d1b;">

{{--
    THE SIGNED-OUT SCREENS THAT ARE NOT THE LOGIN FORM

    "Your application needs correction", "Account pending" and "Account
    blocked" are what a farmer lands on after signing in, so they carry the
    login page's look: the FITSC office photo under a green tint, the Tanza
    seal and the office name, and a white card on top.

    The photo layer is pinned to the window and written inline, the same
    way the login's is, so it can never stop short of the bottom of a long
    page and leave a plain strip, and so it does not depend on a stylesheet
    that may be older than the page.
--}}
<div aria-hidden="true" style="position:fixed;top:0;right:0;bottom:0;left:0;z-index:0;">
    <img src="{{ asset('images/fitsc-office.jpg') }}" alt=""
         style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">
    <div style="position:absolute;top:0;right:0;bottom:0;left:0;background:rgba(13,61,27,0.70);"></div>
</div>

<div class="flex min-h-screen flex-col items-center justify-center px-4 py-10"
     style="position:relative;z-index:1;">

    {{-- The seal and the office name, the same pair the login card carries. --}}
    <div class="mb-5 text-center text-white">
        <span class="mx-auto mb-3 flex items-center justify-center rounded-full bg-white p-2 shadow-lg"
              style="width:4.5rem;height:4.5rem;box-shadow:0 0 0 4px rgba(255,255,255,0.3),0 10px 25px rgba(0,0,0,0.3);">
            <img src="{{ asset('images/tanza-seal.png') }}"
                 alt="Seal of the Municipality of Tanza, Cavite" style="width:100%;height:100%;">
        </span>
        <p class="text-base font-bold leading-tight sm:text-lg">
            Farmers Information and<br>Technology Services Center
        </p>
        <p class="mt-1 text-sm font-medium" style="color:#7ddc8f;">Municipality of Tanza</p>
    </div>

    <div class="w-full {{ $wide ?? false ? 'max-w-3xl' : 'max-w-md' }}">
        @yield('content')
    </div>

    <p class="mt-8 text-center text-xs" style="color:rgba(255,255,255,0.75);">
        &copy; {{ date('Y') }} Farmers Information and Technology Services Center. All rights reserved.
    </p>
</div>

</body>
</html>
