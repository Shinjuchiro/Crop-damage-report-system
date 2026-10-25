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

    {{-- ARMS THE SPLASH FOR THE NEXT SIGN-IN

         The splash screen (layouts/partials/splash.blade.php) plays once and
         then sets a flag in sessionStorage so that moving between Login,
         Register and Forgot Password does not replay a three second
         animation on every page.

         That flag is cleared here, on every page INSIDE the system. So the
         rule becomes: it plays when you arrive at the sign-in screens, and
         again the next time you arrive after having been signed in - which
         is what logging out and coming back to the site is. What it still
         will not do is replay while you are moving around the sign-in
         screens themselves, or after a mistyped password, because nothing
         in there clears the flag.

         Inline, in the head, and wrapped: this must not depend on the
         bundle, and private browsing can make sessionStorage throw. --}}
    <script>
        try { sessionStorage.removeItem('tf-splash-done'); } catch (e) { /* nothing to arm */ }
    </script>
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
<div class="min-h-screen lg:flex">

    {{-- =====================================================================
         SIDEBAR
         Desktop only. Below 1024px the bottom bar and its More sheet are the
         navigation, and nothing opens this.

         It is hidden with a plain class rather than by an Alpine binding,
         and that is the whole point. It used to be an off canvas drawer
         held off screen by :class="sidebarOpen ? ... : '-translate-x-full'".
         Alpine is a deferred module, so on every page load the browser
         painted this aside before Alpine had run, with no transform on it
         yet: the sidebar flashed across the screen for an instant on every
         phone navigation. A class that is in the HTML from the first paint
         cannot do that.
    ====================================================================== --}}
    {{-- sidebar-shell pairs with sidebar-scroll on the <nav> below: the
         menu's scrollbar is invisible until the pointer is anywhere over
         this panel. The rules are in resources/css/app.css. --}}
    <aside class="sidebar-shell hidden w-80 shrink-0 flex-col border-r border-sidebar-border
                  bg-sidebar text-sidebar-foreground lg:sticky lg:top-0 lg:flex lg:h-screen">

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
        </div>

        <div class="mx-4 border-t border-sidebar-border lg:mx-5"></div>

        {{-- Role navigation. flex-col + mt-auto on the trailing "Need Help?"
             block (see the nav-* partials) is what pins it to the very
             bottom of the sidebar instead of just after the last menu item. --}}
        <nav class="sidebar-scroll flex flex-1 flex-col gap-0.5 overflow-y-auto overflow-x-hidden px-3 py-3 text-sm">
            @include('layouts.partials.nav-' . auth()->user()->role)
        </nav>

    </aside>

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

                {{-- NOTIFICATIONS

                     The bell opens each role's own inbox: things addressed
                     TO the signed-in user, most with their own read state
                     and a click-through to the record they're about (see
                     NotificationBroadcast::linkUrl() and the Sept 2026
                     notification-system rule). None of these four inboxes
                     sit in the sidebar too (see nav-farmer.blade.php's note
                     on this) - the bell is the only door to each.

                     MAO is the one exception worth calling out: its
                     sidebar's "Announcements" is a DIFFERENT page - where
                     the office composes and reviews what it has SENT
                     (NotificationBroadcastController) - so the bell here
                     deliberately points at a separate inbox route
                     (NotificationInboxController) instead of that one.

                     Oct 2026: the bell now opens a dropdown rather than
                     navigating. The question it answers is almost always
                     "anything new?", and the answer is almost always "no";
                     making that a page load and a trip back was the long
                     way round for a question asked twenty times a day. The
                     pages are untouched and See all leads to them.

                     The rows are rendered with the page, like the unread
                     count always was, so the panel opens with no spinner
                     and no request. Eight of them: enough that the answer
                     is usually in the panel, few enough that every page in
                     the system is not carrying a mailbox around. --}}
                @php
                    $role = auth()->user()->role;

                    [$bellRoute, $bellShowRoute, $bellReadAll] = match ($role) {
                        'farmer'      => [route('farmer.notifications.index'),      'farmer.notifications.show',      route('farmer.notifications.read-all')],
                        'association' => [route('association.notifications.index'), 'association.notifications.show', route('association.notifications.read-all')],
                        'technician'  => [route('technician.notifications.index'),  'technician.notifications.show',  route('technician.notifications.read-all')],
                        'mao'         => [route('mao.notifications.inbox'),         'mao.notifications.inbox.show',   route('mao.notifications.inbox.read-all')],
                        default       => [null, null, null],
                    };

                    $bellCount = \App\Models\Notification::where('user_id', auth()->id())
                        ->where('is_read', false)->count();

                    $bellItems = \App\Models\Notification::with('broadcast')
                        ->where('user_id', auth()->id())
                        ->orderByDesc('created_at')
                        ->limit(8)
                        ->get();
                @endphp

                <x-notification.panel :items="$bellItems"
                                      :unread="$bellCount"
                                      :see-all="$bellRoute"
                                      :read-all="$bellReadAll"
                                      :show-route="$bellShowRoute" />

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
                     when this went in, so it is not framed twice.

                     THE HEADING IS INSIDE IT (Oct 2026)

                     It used to sit above the card, on the page background,
                     so a screen that used it looked quite different from one
                     that printed its own heading inside the card - and about
                     half the system does each. The office noticed, which is
                     how these things get noticed: two screens of the same
                     product that do not look like the same product.

                     Inside the frame, every page reads the same way from the
                     top down: name, what the screen is for, then the screen.
                     Nothing a page does changes: the sections it sets are
                     the same ones, hideHeading still opts out for the pages
                     that print their own. --}}
                <div @class([
                    'animate-fade-up',
                    'rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6'
                        => ! View::hasSection('noFrame'),
                ])>

                    {{-- Heading. Hidden on phones and tablets: below 1024px
                         the bar carries the office mark and the screen is
                         running the compact layout, where a heading block is
                         wasted height. It appears with the sidebar, at
                         1024px.

                         A page opts out with @section('hideHeading', true)
                         when it carries its own name inside the frame
                         instead (the Crop Management Report's tab strip, and
                         the filter-bar pages that title their own card).
                         Opt-in only: a page that does not set it keeps
                         working exactly as before. --}}
                    @unless (View::hasSection('hideHeading'))
                        <div class="mb-5 hidden flex-col gap-3 lg:flex lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <h1 class="truncate text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                    @yield('heading', 'Welcome back, ' . auth()->user()->display_name . '!')
                                    @hasSection('heading-fil')
                                        <span class="font-medium text-muted-foreground">/ @yield('heading-fil')</span>
                                    @endif
                                </h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    @yield('subheading', 'Monitor crop damage reports and manage assistance for disaster affected farmers.')
                                </p>
                            </div>

                            <div class="shrink-0">@yield('header-actions')</div>
                        </div>
                    @endunless

                    {{-- Below 1024px the page actions still need somewhere to
                         live - right-aligned to match where they sit on the
                         desktop heading row above (justify-between puts them
                         on the right there too). --}}
                    @hasSection('header-actions')
                        <div class="mb-4 flex justify-end lg:hidden">@yield('header-actions')</div>
                    @endif

                    @if (session('status'))
                        <x-ui.alert variant="success" class="animate-fade-up mb-4">
                            {{ session('status') }}
                        </x-ui.alert>
                    @endif

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
