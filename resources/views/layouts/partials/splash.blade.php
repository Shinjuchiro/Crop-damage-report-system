{{--
    SPLASH SCREEN (sign-in screens only)
    ---------------------------------------------------------------------
    The deep green closes in from the top and bottom edges, meets in the
    middle, and the seal drops into the centre. When the page is ready the
    whole thing fades away.

    WHY IT IS ONLY HERE

    This is included by layouts/auth.blade.php, which is login, register,
    forgot password, the OTP screen and the password reset. It is NOT in
    layouts/app.blade.php. The system is an ordinary multi-page Laravel
    app, so every link and every tap of the bottom bar is a full page load;
    a splash inside the app would play dozens of times in one sitting and
    read as the app being slow rather than as it starting up.

    WHY THE STYLE IS INLINE AND IN THE BODY

    Everything here is self-contained: no Tailwind class, no Vite asset.
    The compiled stylesheet is render-blocking, so the first paint happens
    when it arrives; by then this is already in the document and covers the
    page while the fonts, the images and Alpine are still coming. Keeping
    it independent also means a broken build cannot leave a farmer looking
    at an unstyled green rectangle they cannot dismiss.

    WHY IT CANNOT TRAP ANYONE

    The animation ends, by itself, in a state that is invisible and
    unclickable: opacity 0, visibility hidden, pointer-events none, written
    into the keyframes with animation-fill-mode forwards. No JavaScript is
    needed for that. The script below only tidies the element out of the
    document afterwards, and if it never runs the screen still clears.
--}}

<style>
    /* Once per browser session. The flag is set by the script at the
       bottom; the class is put on <html> before the body paints, so a
       second visit shows nothing rather than flashing and hiding. */
    html.splash-done #tf-splash { display: none; }

    #tf-splash {
        position: fixed;
        inset: 0;
        z-index: 9999;
        overflow: hidden;
        animation: tf-splash-out 400ms ease-in 1250ms forwards;
    }

    /* 50.5%, not 50%: a viewport with an odd pixel height leaves a
       hairline of the page showing through the seam between the two
       halves, and on a white login screen that reads as a flicker. */
    #tf-splash .tf-panel {
        position: absolute;
        left: 0;
        right: 0;
        height: 50.5%;
        background: #0d3d1b;
    }

    #tf-splash .tf-panel-top {
        top: 0;
        animation: tf-close-down 450ms cubic-bezier(.22,.61,.36,1) both;
    }

    #tf-splash .tf-panel-bottom {
        bottom: 0;
        animation: tf-close-up 450ms cubic-bezier(.22,.61,.36,1) both;
    }

    /* The seal, in the white disc the login screen already puts it in, so
       it holds its edges against the dark green. */
    #tf-splash .tf-mark {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 7.5rem;
        height: 7.5rem;
        margin: -3.75rem 0 0 -3.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: #ffffff;
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, .55);
        /* Starts above the middle and drops in, slightly past the centre
           and back, which is what makes it land rather than arrive. */
        animation: tf-drop 550ms cubic-bezier(.34,1.3,.64,1) 350ms both;
    }

    #tf-splash .tf-mark img {
        width: 5.75rem;
        height: 5.75rem;
        object-fit: contain;
    }

    @keyframes tf-close-down {
        from { transform: translateY(-100%); }
        to   { transform: translateY(0); }
    }

    @keyframes tf-close-up {
        from { transform: translateY(100%); }
        to   { transform: translateY(0); }
    }

    @keyframes tf-drop {
        from { opacity: 0; transform: translateY(-5rem) scale(.9); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    @keyframes tf-splash-out {
        from { opacity: 1; }
        to   { opacity: 0; visibility: hidden; pointer-events: none; }
    }

    /* Someone who has asked their phone to stop animating gets the green
       and the seal with no movement, held briefly and then faded. */
    @media (prefers-reduced-motion: reduce) {
        #tf-splash .tf-panel-top,
        #tf-splash .tf-panel-bottom { animation: none; }
        #tf-splash .tf-mark { animation: tf-fade-in 200ms linear both; }
        #tf-splash { animation: tf-splash-out 300ms linear 700ms forwards; }

        @keyframes tf-fade-in {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
    }
</style>

<div id="tf-splash" aria-hidden="true" role="presentation">
    <div class="tf-panel tf-panel-top"></div>
    <div class="tf-panel tf-panel-bottom"></div>

    <div class="tf-mark">
        <img src="{{ asset('images/tanza-seal.png') }}" alt="">
    </div>
</div>

<script>
    (function () {
        // Remove the element once it has finished, so nothing is left
        // sitting over the form. The timeout is the animation's own length
        // plus a little; animationend is not used because the fade is on
        // the container and would also fire for the panels inside it.
        window.setTimeout(function () {
            var splash = document.getElementById('tf-splash');
            if (splash && splash.parentNode) {
                splash.parentNode.removeChild(splash);
            }
        }, 2000);

        // Play once per browser session, so moving from Login to Register
        // and back does not replay it. sessionStorage, not localStorage:
        // coming back to the app tomorrow should feel like opening it
        // again. Wrapped because private browsing can make this throw.
        try {
            sessionStorage.setItem('tf-splash-done', '1');
        } catch (e) { /* no flag, so it plays again. Harmless. */ }
    })();
</script>
