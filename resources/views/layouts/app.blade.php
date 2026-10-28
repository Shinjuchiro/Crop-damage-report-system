<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Dashboard')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/tanza-seal.png') }}">

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
    {{-- Text size for the whole system: about 16% larger than the browser
         default. Everything is sized in rem, so changing it here scales the
         text and the spacing together. It is inline so it takes effect
         without rebuilding the stylesheet. --}}
    <style>html { font-size: 116%; }</style>
    {{-- SHOW THE PAGE ONLY WHEN IT IS READY

         Content used to appear piece by piece: the page first, then photos,
         then the weather card a moment later, which pushed everything below
         it down. Now the content area is held back (and a small spinner
         shown) until the page has fully loaded AND, on a dashboard, the
         weather lookup has finished. The class is added by script, so with
         scripts off nothing is ever hidden; and show() below is also forced
         after six seconds, so a slow forecast can never hold a page back.
         Inline on purpose: it has to be in force before the stylesheet. --}}
    <style>
        /* visibility, not opacity. Anything less than full opacity turns <main>
           into its own stacking context, and every modal lives inside it:
           for as long as that lasts the sidebar and the top bar are drawn
           ON TOP of the modal and its dim, and they jump behind it when
           the fade ends. Same reason the frame below no longer animates. */
        html.fits-loading main { visibility: hidden; }
        html.fits-loading body::after {
            content: ""; position: fixed; top: 50%; left: 50%; z-index: 60;
            width: 2.25rem; height: 2.25rem; margin: -1.125rem 0 0 -1.125rem;
            border: 3px solid rgba(21, 128, 61, 0.2); border-top-color: #15803d;
            border-radius: 9999px; animation: fits-spin 0.7s linear infinite;
        }
        @keyframes fits-spin { to { transform: rotate(360deg); } }
    </style>
    <script>document.documentElement.classList.add('fits-loading');</script>
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
    {{-- THE ICON RAIL (Oct 2026)

         On a laptop or a landscape tablet the menu is a narrow bar of icons
         down the left edge. Point at it (or tab into it with the keyboard)
         and it grows to show the full menu, and the page beside it is
         pushed to the right by the same amount; move away and both fold
         back together.

         Two parts. The wrapper takes part in the page layout (sticky, one
         window tall) and is the thing whose width animates, so the content
         column beside it shifts with it. The aside inside it fills the
         wrapper and clips whatever does not fit (the labels). Its contents
         are always laid out at full width, so nothing re-wraps while it
         opens. The numbers on the menu become small dots on the icons while
         it is folded. All the sizes are inline or in the style block below
         so they never depend on the compiled stylesheet. --}}
    <style>
        .sidebar-wrap, .sidebar-shell { display: none; }
        @media (min-width: 1024px) {
            .sidebar-wrap  { display: block; flex: none; position: sticky; top: 0; align-self: flex-start;
                             height: 100vh; width: 4.75rem; z-index: 50;
                             transition: width 0.2s ease; }
            /* The top bar is exactly as tall as the folded logo block, so the
               line under the logo and the line under the top bar are one
               straight line across the screen. (The 1px is the divider.) */
            .fits-topbar { height: 5rem; }
            .sidebar-wrap:hover { width: 20rem; }
            /* Separate rule: one browser that cannot read :has() must not
               throw away the hover rule above along with it. */
            .sidebar-wrap:has(:focus-visible) { width: 20rem; }
            .sidebar-shell { display: block; position: absolute; top: 0; bottom: 0; left: 0;
                             width: 100%; overflow: hidden;
                             border-radius: 0 12px 12px 0; }
            /* The office header. Folded: just the seal, centred. Open: a
               bigger seal with the name centred underneath. The name is
               collapsed to nothing (not just hidden) while folded, so it can
               never peek out of the narrow rail. It is also a link, so it
               gets a quiet tint on hover. */
            .sidebar-brand { display: flex; flex-direction: column; align-items: center; flex: none;
                             box-sizing: border-box; min-height: calc(5rem - 1px); justify-content: center;
                             padding: 0.7rem 0; overflow: hidden; color: inherit; text-decoration: none;
                             transition: background-color 0.15s ease; }
            .sidebar-brand:hover, .sidebar-brand:focus-visible { background-color: rgba(255, 255, 255, 0.08); }
            .sidebar-brand img { flex: none; width: 3.5rem; height: 3.5rem; min-width: 3.5rem;
                                 aspect-ratio: 1 / 1; object-fit: contain;
                                 transition: width 0.2s ease, height 0.2s ease, min-width 0.2s ease; }
            .sidebar-brand-title { max-height: 0; opacity: 0; margin: 0; overflow: hidden;
                                   text-align: center; white-space: nowrap; font-size: 0.95rem;
                                   font-weight: 600; line-height: 1.3;
                                   transition: max-height 0.2s ease, opacity 0.15s ease, margin 0.2s ease; }
            .sidebar-wrap:hover .sidebar-brand img { width: 5rem; height: 5rem; min-width: 5rem; }
            .sidebar-wrap:hover .sidebar-brand-title { max-height: 4rem; opacity: 1; margin-top: 0.75rem; }
            .sidebar-wrap:has(:focus-visible) .sidebar-brand img { width: 5rem; height: 5rem; min-width: 5rem; }
            .sidebar-wrap:has(:focus-visible) .sidebar-brand-title { max-height: 4rem; opacity: 1; margin-top: 0.75rem; }
            .sidebar-inner  { display: flex; flex-direction: column; width: 100%; height: 100%; }

            /* Each menu item is its own box that clips its own text. Folded,
               that leaves a small icon-sized square (so the highlighted one
               is a square, not a long bar cut off by the edge); as the
               panel grows, the boxes grow with it. The text never wraps, so
               nothing jumps while it opens, and the left padding is the
               exact amount that centres a 1.25rem icon in the narrow strip. */
            .sidebar-shell nav a,
            .sidebar-shell nav button {
                overflow: hidden; white-space: nowrap;
                padding-left: 1.25rem; padding-right: 1.25rem;
            }
            /* Larger icons, and a wider gap before the label so that the box
               (4.25rem wide when folded) never shows the start of a word. */
            .sidebar-shell nav svg.h-5 { width: 1.75rem; height: 1.75rem; }
            .sidebar-shell nav a > span.flex,
            .sidebar-shell nav button > span.flex { gap: 1rem; }

            /* Folded: submenus stay out of sight, and a menu number becomes
               a small dot on the corner of its icon. */
            .sidebar-wrap:not(:hover):not(:has(:focus-visible)) .sidebar-shell nav .pl-8 { display: none; }
            /* The small dropdown arrow on a group (Assistance Allocation) is
               only drawn once the menu is open. */
            .sidebar-wrap:not(:hover):not(:has(:focus-visible)) .sidebar-shell nav button > svg { display: none; }

            /* Folded: the labels are hidden outright, not just clipped by the
               edge of the box. Clipping alone let the first letters show
               whenever the rail was a little wider than icon plus gap. The
               icon wrappers (span.flex) and the count bubble stay visible. */
            .sidebar-wrap:not(:hover):not(:has(:focus-visible)) .sidebar-shell nav a span:not(.flex):not(.bg-destructive),
            .sidebar-wrap:not(:hover):not(:has(:focus-visible)) .sidebar-shell nav button span:not(.flex):not(.bg-destructive),
            .sidebar-wrap:not(:hover):not(:has(:focus-visible)) .sidebar-shell nav > span span { visibility: hidden; }

            /* THE COUNT BUBBLE, MOBILE LEGENDS STYLE
               A menu number is a small red bubble sitting on the top right
               corner of its icon, folded or open, instead of at the far end
               of the row. That is also what keeps it visible on the folded
               rail. It applies to every role's menu because they all draw
               their counts as span.bg-destructive. */
            .sidebar-shell nav a { position: relative; }
            .sidebar-shell nav a > span.bg-destructive {
                position: absolute; left: 2.6rem; top: 0.15rem; margin: 0;
                min-width: 1.05rem; height: 1.05rem; padding: 0 0.28rem;
                font-size: 10px; line-height: 1;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
                pointer-events: none;
            }
        }
    </style>
    {{-- Cards were nearly flat against the page. This makes their shadow
         clearly visible without a heavy look. It targets the rounded panels
         that carry the shadow-sm class (cards, stat tiles, record cards) and
         leaves buttons and inputs, which are smaller radii, as they were.
         Inline so it never waits on the compiled stylesheet. --}}
    <style>
        .rounded-xl.shadow-sm, .rounded-2xl.shadow-sm {
            box-shadow: 0 4px 14px -3px rgba(15, 23, 42, 0.22), 0 1px 4px 0 rgba(15, 23, 42, 0.16);
        }
    </style>
    <div class="sidebar-wrap">
    <aside class="sidebar-shell flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground"
           aria-label="Main menu">
      <div class="sidebar-inner">

        {{-- Logo. Folded, only a small seal shows, centred in the narrow
             rail. Open, the seal grows and the office name appears beneath
             it, both centred. The whole block links to the office's
             Facebook page. --}}
        <a href="https://www.facebook.com/agriculture.tanza" target="_blank" rel="noopener noreferrer"
           title="Open the Tanza Municipal Agriculture Office on Facebook"
           aria-label="Municipal Agriculture Office of Tanza on Facebook (opens in a new tab)"
           class="sidebar-brand">
            <img src="{{ asset('images/tanza-seal.png') }}"
                 alt="Seal of the Municipality of Tanza, Cavite">
            <p class="sidebar-brand-title">
                Farmers Information and<br>
                Technology Services Center
            </p>
        </a>

        <div class="border-t border-sidebar-border" style="flex:none;margin:0 0.5rem;"></div>

        {{-- Role navigation. flex-col + mt-auto on the trailing "Need Help?"
             block (see the nav-* partials) is what pins it to the very
             bottom of the sidebar instead of just after the last menu item. --}}
        <nav class="sidebar-scroll flex flex-1 flex-col gap-0.5 overflow-y-auto overflow-x-hidden px-3 py-3 text-sm"
             style="min-height:0;padding-left:0.25rem;padding-right:0.25rem;">
            @include('layouts.partials.nav-' . auth()->user()->role)
        </nav>

      </div>
    </aside>
    </div>
    <script>
        /* Maps and charts measure themselves once; tell them when the page
           beside the menu has finished changing width. */
        (function () {
            var w = document.querySelector('.sidebar-wrap');
            if (!w) return;
            w.addEventListener('transitionend', function (e) {
                if (e.propertyName === 'width') window.dispatchEvent(new Event('resize'));
            });
        })();
    </script>

    <div class="flex min-w-0 flex-1 flex-col">

        {{-- =================================================================
             TOP BAR
             Shorter on phones (h-14) so the content starts higher up.
        ================================================================== --}}
        <header class="fits-topbar sticky top-0 z-20 flex h-14 items-center justify-between gap-3 border-b border-border
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
                {{-- NO ENTRANCE ANIMATION ON THIS FRAME, ON PURPOSE. It used to
                     slide up (a transform) and then fade (an opacity
                     animation). Either one turns the frame into a containing
                     block (transform) or a stacking context (opacity) for
                     every modal inside it, and every modal on every page
                     lives in here. For the length of the animation each one
                     was placed against the frame, or drawn UNDER the sidebar
                     and top bar, and then jumped into place when it ended.
                     That was the "modal appears below, snaps into place,
                     background not dimmed" glitch. The loading veil above
                     already gives the page a clean appearance. --}}
                <div @class([
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
@include('layouts.partials.equal-buttons')

@stack('scripts')
<script>
    (function () {
        var root = document.documentElement, done = false;

        function show() {
            if (done) { return; }
            done = true;
            root.classList.remove('fits-loading');
        }

        function check() {
            if (document.readyState !== 'complete') { return; }
            if (document.querySelector('[x-data="tanzaWeather()"]') && ! window.__fitsWeatherReady) { return; }
            requestAnimationFrame(function () { requestAnimationFrame(show); });
        }

        window.addEventListener('load', check);
        window.addEventListener('fits-weather-ready', check);
        // Back/forward can restore the page from memory without a load event.
        window.addEventListener('pageshow', function (e) { if (e.persisted) { show(); } });
        setTimeout(show, 6000);
        check();
    })();
</script>
</body>
</html>
