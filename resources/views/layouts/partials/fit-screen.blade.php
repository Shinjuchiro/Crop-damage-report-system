{{--
    MAKE THIS SCREEN FIT THE DEVICE

    Included by the sign-in screens (Login, Register). Nowhere else: the
    pages inside the system are lists and forms that are meant to scroll.

        @include('layouts.partials.fit-screen')

    WHAT IT DOES

    A sign-in screen is one screen. There is nothing below the fold worth
    scrolling to, so a scrollbar on it reads as the page not fitting the
    device rather than as there being more to see, and that is exactly what
    happened: the same login that sat perfectly on one laptop scrolled on
    another with a browser notification bar, and on a phone held sideways.

    So the screen is told to be exactly as tall as the viewport, and
    anything that would not fit inside it is shrunk until it does. Three
    classes do the work, all defined in resources/css/app.css:

        .fit-shell    the screen, exactly viewport height
        .fit-zoom     content that scales down as one piece
        .fit-card     a single centred card that shrinks in place

    and this file is what measures the screen and decides by how much.

    Mark what should shrink with data-fit-zoom or data-fit-card alongside
    the matching class. A page can carry both: Login does, because its
    phone layout and its desktop layout are separate markup and only one
    of them is on screen at a time. The one that is hidden measures zero
    and is skipped, so neither has to know about the other.

    Register marks nothing at all. Its steps are genuinely long, so there
    the shell holds the screen to the device and the form scrolls inside
    it, against the green scrollbar in app.css, rather than the whole page
    moving.

    NOTHING HERE IS LOAD BEARING

    The fitting only happens while <html> carries .fit-on, and .fit-on is
    added by script. If the script never runs, or runs and finds content
    too tall to shrink into the space even at the floor below, the class
    comes off and the page goes back to being an ordinary tall page that
    scrolls. A farmer can always reach the login button; the worst case is
    the scrollbar we were trying to avoid.
--}}

@push('head')
    {{-- Added before the body paints, so the page is never briefly a
         scrolling one. The timer is the failsafe: if the script at the
         bottom of the page never gets to run, this comes back off a
         moment later rather than leaving the screen clipped. --}}
    <script>
        document.documentElement.classList.add('fit-on');
        setTimeout(function () {
            if (! window.__fitScreenRan) {
                document.documentElement.classList.remove('fit-on');
            }
        }, 3000);
    </script>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /* How far anything is allowed to shrink. Below about seven tenths the
       smaller labels stop being readable outdoors, which is the whole
       point of the 15px root size elsewhere, so past this the page is
       allowed to scroll instead. */
    var MIN  = 0.7;
    var root = document.documentElement;

    function all(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    function fit() {
        var viewport = window.innerHeight;
        if (! viewport) return;

        var overflowed = false;
        var scaled     = [];        /* so it can all be undone in one go */

        /* ---------------------------------------------------------------
           Content that scales as one piece.

           Shrinking changes the width the content lays itself out at (see
           .fit-zoom), and a wider column wraps less text, which changes
           its height, which changes the factor needed. So this solves by
           walking towards the answer rather than calculating it once.
           Three or four passes is plenty; the loop stops as soon as two
           passes agree to within half a percent.
        --------------------------------------------------------------- */
        all('[data-fit-zoom]').forEach(function (el) {
            /* offsetHeight is the laid-out height and ignores the scaling,
               which is what makes this measurable at all. Zero means this
               half of the page is the display:none one. */
            if (! el.offsetHeight) return;

            var factor = 1;

            for (var pass = 0; pass < 5; pass++) {
                el.style.setProperty('--fit', factor);

                var height = el.offsetHeight;
                if (! height) return;

                var next = Math.min(1, Math.max(MIN, viewport / height));
                if (Math.abs(next - factor) < 0.004) { factor = next; break; }
                factor = next;
            }

            el.style.setProperty('--fit', factor);
            scaled.push(['--fit', el]);

            /* Hit the floor and still too tall. */
            if (el.offsetHeight * factor > viewport + 1) overflowed = true;
        });

        /* ---------------------------------------------------------------
           A centred card shrinks in place instead. The photo behind it
           stays full bleed, so there is nothing to widen first.
        --------------------------------------------------------------- */
        all('[data-fit-card]').forEach(function (el) {
            var height = el.offsetHeight;
            if (! height) return;

            /* The seal hangs over the top edge of the card and the screen
               keeps a little air around the whole thing, so the card needs
               more room than it measures. */
            var needed    = height + 56;
            var available = viewport - 24;
            var factor    = Math.min(1, Math.max(MIN, available / needed));

            el.style.setProperty('--fit-card', factor);
            scaled.push(['--fit-card', el]);

            if (needed * factor > available + 1) overflowed = true;
        });

        /* Too tall even at the floor. Shrunk text AND a scrollbar would be
           the worst of both, so the shrinking is undone completely and the
           screen goes back to the ordinary tall page it was before: either
           it fits, or it looks exactly as it always has. */
        if (overflowed) {
            scaled.forEach(function (pair) {
                pair[1].style.setProperty(pair[0], 1);
            });
        }

        root.classList.toggle('fit-on', ! overflowed);
        window.__fitScreenRan = true;
    }

    var pending;

    function schedule() {
        clearTimeout(pending);
        pending = setTimeout(fit, 120);
    }

    function onResize() {
        /* A phone's on-screen keyboard shrinks the viewport. Re-fitting
           then would shrink the whole form the moment a farmer taps a
           field, and grow it back when they tap away, which is far worse
           than the scrollbar this is here to avoid. */
        var focused = document.activeElement;
        if (focused && /^(INPUT|TEXTAREA|SELECT)$/.test(focused.tagName)) return;

        schedule();
    }

    fit();

    /* Images and the webfont both land after this first pass and both can
       change the height, so measure again once they have. */
    window.addEventListener('load', schedule);
    window.addEventListener('resize', onResize);
    window.addEventListener('orientationchange', schedule);

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(schedule);
    }
})();
</script>
@endpush
