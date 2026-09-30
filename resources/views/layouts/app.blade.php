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

        {{-- Role navigation. flex-col + mt-auto on the trailing "Need Help?"
             block (see the nav-* partials) is what pins it to the very
             bottom of the sidebar instead of just after the last menu item. --}}
        <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto overflow-x-hidden px-3 py-3 text-sm">
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
                {{-- BRAND, PHONES ONLY

                     The seal and the office's short name, the same mark the
                     login screen opens with, so the top of every screen says
                     whose system this is rather than repeating the name of
                     the page.

                     This replaced two things: the hamburger and the page
                     title. The hamburger opened the off canvas sidebar, which
                     nothing else on a phone now opens - the bottom bar and
                     its More sheet carry every link the sidebar had, so the
                     drawer was a second door to the same rooms. The sidebar
                     itself is untouched and is still the navigation from
                     1024px up, where it is always on screen.

                     On desktop this is hidden: the sidebar already carries
                     the seal and the full office name. --}}
                <span class="flex min-w-0 items-center gap-2 lg:hidden">
                    <img src="{{ asset('images/tanza-seal.png') }}"
                         alt="Seal of the Municipality of Tanza, Cavite"
                         class="h-9 w-9 shrink-0">
                    <span class="truncate text-base font-bold tracking-tight">TANZA-FITS</span>
                </span>
            </div>

            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                @include('layouts.partials.install-button')

                {{-- Notifications --}}
                @php
                    // The bell opens each role's own inbox: things addressed
                    // TO the signed-in user, most with their own read state
                    // and a click-through to the record they're about (see
                    // NotificationBroadcast::linkUrl() and the Sept 2026
                    // notification-system rule). None of these four inboxes
                    // sit in the sidebar too (see nav-farmer.blade.php's note
                    // on this) - the bell is the only door to each.
                    //
                    // MAO is the one exception worth calling out: its
                    // sidebar's "Notification and Alerts" is a DIFFERENT
                    // page - where the office composes and reviews what it
                    // has SENT (NotificationBroadcastController) - so the
                    // bell here deliberately points at a separate inbox
                    // route (NotificationInboxController) instead of that
                    // one. A farmer's new damage report, a completed
                    // inspection, and the like land in this inbox; the
                    // office's own queues for those are also visible at a
                    // glance via the sidebar badges on Membership
                    // Applications / Crop Damage Monitoring / Validation
                    // Monitoring.
                    $bellRoute = match (auth()->user()->role) {
                        'farmer'      => route('farmer.notifications.index'),
                        'association' => route('association.notifications.index'),
                        'technician'  => route('technician.notifications.index'),
                        'mao'         => route('mao.notifications.inbox'),
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

                {{-- Heading. Hidden on phones and tablets: below 1024px the
                     bar carries the office mark and the screen is running the
                     compact layout, where a heading block is wasted height.
                     It appears with the sidebar, at 1024px.

                     A page can opt out of this whole block with
                     @section('hideHeading', true) when it carries its own
                     name + primary action inside its own card instead (see
                     the filter-bar pages redesigned per the reference
                     screenshots - mao/users/index.blade.php and friends).
                     Opt-in only: every page that doesn't set this keeps
                     working exactly as before. --}}
                @unless (View::hasSection('hideHeading'))
                    <div class="mb-4 hidden flex-col gap-3 lg:flex lg:flex-row lg:items-start lg:justify-between">
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
                @endunless

                {{-- Below 1024px the page actions still need somewhere to
                     live - right-aligned to match where they sit on the
                     desktop heading row above (justify-between puts them on
                     the right there too). --}}
                @hasSection('header-actions')
                    <div class="mb-4 flex justify-end lg:hidden">@yield('header-actions')</div>
                @endif

                @if (session('status'))
                    <x-ui.alert variant="success" class="animate-fade-up mb-4">
                        {{ session('status') }}
                    </x-ui.alert>
                @endif

                {{-- THE PAGE FRAME

                     Every page sits on one card, the way the Maps and
                     Visualization screen already did: a white panel with a
                     border and a soft shadow, holding whatever the page puts
                     inside it. Before this, the map was the only screen built
                     that way and every other page dropped its blocks straight
                     onto the page background, so the system looked like two
                     different products depending on where you were.

                     It lives here rather than on each page because there are
                     eighty-odd pages and a rule that has to be remembered
                     eighty times is a rule that will be forgotten. Any page
                     that genuinely cannot be framed opts out with
                     @section('noFrame', true) - the same escape hatch
                     hideHeading uses - and none do today.

                     The map screen's own copy of this wrapper was removed
                     when this went in, so it is not framed twice. --}}
                <div @class([
                    'animate-fade-up',
                    'rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6'
                        => ! View::hasSection('noFrame'),
                ])>
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
