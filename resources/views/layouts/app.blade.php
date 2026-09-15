<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Dashboard')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/tanza-seal.png') }}">

    @include('layouts.partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
<div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">

    {{-- =====================================================================
         SIDEBAR
         Off canvas on phones and tablets, fixed from lg up. On a phone the
         bottom bar is the main navigation and this is the "More" drawer.
    ====================================================================== --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-72 shrink-0 transform flex-col border-r
                  border-sidebar-border bg-sidebar text-sidebar-foreground transition-transform duration-200
                  lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">

        {{-- Logo. Tighter than before: the old block wasted most of a phone
             screen height before the first menu item appeared. --}}
        <div class="flex items-center gap-3 px-4 py-4 lg:flex-col lg:gap-2 lg:px-6 lg:py-5">
            <img src="{{ asset('images/tanza-seal.png') }}"
                 alt="Seal of the Municipality of Tanza, Cavite"
                 class="h-11 w-11 shrink-0 lg:h-16 lg:w-16">
            <p class="text-sm font-semibold leading-tight lg:text-center">
                Farmers Information and<br class="hidden lg:inline">
                Technology Services Center
            </p>

            {{-- Close button, phones only --}}
            <button @click="sidebarOpen = false" aria-label="Close menu"
                    class="ml-auto rounded-md p-1.5 text-sidebar-foreground/80 hover:bg-sidebar-accent lg:hidden">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <div class="mx-4 border-t border-sidebar-border lg:mx-5"></div>

        {{-- Role navigation --}}
        <nav class="flex-1 space-y-0.5 overflow-y-auto overflow-x-hidden px-3 py-3 text-sm">
            @include('layouts.partials.nav-' . auth()->user()->role)
        </nav>

    </aside>

    {{-- Backdrop for the drawer --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         x-transition.opacity.duration.150ms
         class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col">

        {{-- =================================================================
             TOP BAR
             Shorter on phones (h-14) so the content starts higher up.
        ================================================================== --}}
        <header class="sticky top-0 z-20 flex h-14 items-center justify-between gap-3 border-b border-border
                       bg-card px-3 sm:px-5 lg:h-16 lg:px-8">

            <div class="flex min-w-0 items-center gap-2">
                <button @click="sidebarOpen = true" aria-label="Open navigation"
                        class="-ml-1 rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent
                               hover:text-accent-foreground lg:hidden">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- On a phone the page title sits in the bar, the way a phone
                     app does it, instead of taking another block of height
                     below. On desktop the heading below the bar does the job. --}}
                <p class="truncate text-base font-bold lg:hidden">@yield('title', 'Dashboard')</p>
            </div>

            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                @include('layouts.partials.install-button')

                {{-- Notifications --}}
                @php
                    // The bell opens each role's own inbox/alert page (MAO's
                    // is the alert-composer, since MAO has no personal inbox
                    // of incoming events). Every role also has this same page
                    // in its sidebar (see layouts/partials/nav-*.blade.php) -
                    // the bell is just the quick shortcut to it. A farmer's
                    // new damage report is a separate signal and does not go
                    // through here: see the badge on MAO's "Crop Damage
                    // Monitoring" sidebar item instead.
                    $bellRoute = match (auth()->user()->role) {
                        'farmer'      => route('farmer.notifications.index'),
                        'association' => route('association.notifications.index'),
                        'technician'  => route('technician.notifications.index'),
                        'mao'         => route('mao.notifications.index'),
                        default       => null,
                    };

                    $bellCount = \App\Models\Notification::where('user_id', auth()->id())
                        ->where('is_read', false)->count();
                @endphp

                <{{ $bellRoute ? 'a' : 'span' }} @if ($bellRoute) href="{{ $bellRoute }}" @endif
                    class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground
                           transition-colors hover:bg-accent hover:text-accent-foreground"
                    aria-label="Notifications">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1"/>
                    </svg>
                    @if ($bellCount > 0)
                        <span class="absolute right-1 top-1 min-w-4 rounded-full bg-destructive px-1
                                     text-[10px] font-bold leading-4 text-destructive-foreground">
                            {{ $bellCount > 9 ? '9+' : $bellCount }}
                        </span>
                    @endif
                </{{ $bellRoute ? 'a' : 'span' }}>

                {{-- Account menu: avatar, name, role and logout in one place --}}
                @include('layouts.partials.user-menu')

            </div>
        </header>

        {{-- =================================================================
             PAGE BODY
             pb-28 on phones leaves room for the bottom bar. Without it the
             last row of every list sits underneath it.
        ================================================================== --}}
        <main class="flex-1 px-3 pb-28 pt-4 sm:px-5 lg:px-8 lg:pt-6 lg:pb-6">
            <div class="w-full">

                {{-- Heading. Hidden on phones because the title is already in
                     the top bar, which is one less block of wasted height. --}}
                <div class="mb-4 hidden flex-col gap-3 sm:flex sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h1 class="truncate text-xl font-bold tracking-tight lg:text-2xl">
                            @yield('heading', 'Welcome back, ' . auth()->user()->display_name . '!')
                            @hasSection('heading-fil')
                                <span class="font-medium text-muted-foreground">/ @yield('heading-fil')</span>
                            @endif
                        </h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            @yield('subheading', 'Monitor crop damage reports and manage assistance for disaster affected farmers.')
                        </p>
                    </div>

                    <div class="shrink-0">@yield('header-actions')</div>
                </div>

                {{-- On phones the page actions still need somewhere to live -
                     right-aligned to match where they sit on the desktop
                     heading row above (justify-between puts them on the
                     right there too). --}}
                @hasSection('header-actions')
                    <div class="mb-4 flex justify-end sm:hidden">@yield('header-actions')</div>
                @endif

                @if (session('status'))
                    <x-ui.alert variant="success" class="animate-fade-up mb-4">
                        {{ session('status') }}
                    </x-ui.alert>
                @endif

                <div class="animate-fade-up">
                    @yield('content')
                </div>
            </div>
        </main>

        {{-- Footer, desktop only. On a phone the bottom bar is already there. --}}
        <footer class="hidden border-t border-foreground/20 px-8 py-3 text-xs text-muted-foreground lg:block">
            <div class="flex items-center justify-between gap-4">
                <span>&copy; {{ date('Y') }} Farmers Information and Technology Services Center.
                      All rights reserved.</span>
                <span class="flex items-center gap-6">
                    <span>Tanza, Cavite, Philippines</span>
                    <span>v1.0.0</span>
                </span>
            </div>
        </footer>
    </div>

    {{-- Bottom navigation, phones only --}}
    @include('layouts.partials.mobile-nav')
</div>

{{-- One confirmation dialog for the whole system. Any form or link carrying a
     data-confirm attribute is routed here before anything reaches the server. --}}
@include('layouts.partials.confirm-dialog')

@stack('scripts')
</body>
</html>
