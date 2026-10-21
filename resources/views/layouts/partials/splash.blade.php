{{--
    SPLASH SCREEN (sign-in screens only)
    ---------------------------------------------------------------------
    The deep green closes in from the top and bottom edges and meets in the
    middle. The office itself fades up behind a green wash, the seal drops
    into the centre of three rings, the name and the system's full title
    settle under it, and after a few seconds the whole thing fades away.

    It holds for about two and a half seconds before it starts to fade,
    which is long enough to read the title. That is deliberately longer than
    a loading screen needs to be: this is the first thing anyone sees of the
    system, including a panel at a defence, and the one place the full name
    is spelled out.

    WHY IT IS ONLY HERE

    This is included by layouts/auth.blade.php, which is login, register,
    forgot password, the OTP screen and the password reset. It is NOT in
    layouts/app.blade.php. The system is an ordinary multi-page Laravel
    app, so every link and every tap of the bottom bar is a full page load;
    a splash inside the app would play dozens of times in one sitting and
    read as the app being slow rather than as it starting up.

    WHEN IT PLAYS

    On arriving at the sign-in screens, and again every time you arrive
    after having been signed in - so logging out and coming back plays it,
    and so does opening the site fresh. It does not replay while you move
    between Login, Register and Forgot Password, and it does not replay
    after a mistyped password, because three seconds of animation between
    you and a second attempt would be a punishment.

    That is one flag in sessionStorage, set by the script at the bottom of
    this file and cleared by a line in layouts/app.blade.php - the layout
    every page inside the system uses. Being signed in is what arms it
    again.

    WHY THE STYLE IS INLINE AND IN THE BODY

    Everything here is self-contained: no Tailwind class, no Vite asset, and
    sizes in px rather than rem so the root font-size cannot move it. The
    compiled stylesheet is render-blocking, so the first paint happens when
    it arrives; by then this is already in the document and covers the page
    while the fonts, the images and Alpine are still coming. Keeping it
    independent also means a broken build cannot leave a farmer looking at
    an unstyled green rectangle they cannot dismiss.

    WHY IT CANNOT TRAP ANYONE

    The animation ends, by itself, in a state that is invisible and
    unclickable: opacity 0, visibility hidden, pointer-events none, written
    into the keyframes with animation-fill-mode forwards. No JavaScript is
    needed for that. The script below only tidies the element out of the
    document afterwards, and if it never runs the screen still clears.

    THE TIMELINE, in one place, since it is spread across the rules below

        0ms      the two green panels start closing in
        450ms    they meet; the screen is solid dark green
        450ms    the office photo and its green wash fade up over them
        600ms    the seal drops into the middle and the rings draw outward
        1000ms   TANZA - FITS fades in under the seal
        1250ms   the full system name fades in at the foot
        2500ms   the whole screen begins to fade
        2950ms   it is gone; the script removes it at 3200ms
--}}

<style>
    /* Once per browser session. The flag is set by the script at the
       bottom; the class is put on <html> before the body paints, so a
       second visit shows nothing rather than flashing and hiding. */
    html.splash-done #tf-splash { display: none; }

    /* THE GROUND IS OPAQUE, AND THAT MATTERS

       The two green panels start off-screen and slide in, so for the first
       450ms the splash itself draws nothing at all. With a transparent
       ground that meant the login form was visible underneath for those
       450ms and then got covered up, which read as the page loading twice.

       A solid white ground fixes it: the very first frame is a blank
       screen, and the green closes in over that instead of over the form.
       White, specifically, because the auth layout's body is white, so
       there is no seam at the moment the splash finally fades away. */
    #tf-splash {
        position: fixed;
        inset: 0;
        z-index: 9999;
        overflow: hidden;
        background: #ffffff;
        animation: tf-splash-out 450ms ease-in 2500ms forwards;
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

    /* The stage sits over the closed panels, so the green is what the eye
       sees first and the photograph arrives into it rather than being
       uncovered by it. */
    #tf-splash .tf-stage {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        animation: tf-fade-in 500ms ease-out 450ms both;
    }

    #tf-splash .tf-photo {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* The same green wash the login screen puts over this photograph, so
       the splash and the screen behind it are plainly the same place. */
    #tf-splash .tf-tint {
        position: absolute;
        inset: 0;
        background: rgba(13, 61, 27, .72);
    }

    #tf-splash .tf-centre {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    #tf-splash .tf-mark {
        position: relative;
        width: 108px;
        height: 108px;
        display: flex;
        align-items: center;
        justify-content: center;
        /* Starts above the middle and drops in, slightly past the centre
           and back, which is what makes it land rather than arrive. */
        animation: tf-drop 600ms cubic-bezier(.34,1.3,.64,1) 600ms both;
    }

    /* Three rings around the seal, drawn outward one after another. They
       are what stops the mark reading as a sticker on a photograph. */
    #tf-splash .tf-ring {
        position: absolute;
        top: 50%;
        left: 50%;
        border: 1px solid rgba(255, 255, 255, .45);
        border-radius: 9999px;
        transform: translate(-50%, -50%);
        animation: tf-ring-out 700ms ease-out both;
    }

    #tf-splash .tf-ring-1 { width: 130px; height: 130px; animation-delay: 850ms; }
    #tf-splash .tf-ring-2 { width: 164px; height: 164px; animation-delay: 950ms;  border-color: rgba(255,255,255,.3); }
    #tf-splash .tf-ring-3 { width: 200px; height: 200px; animation-delay: 1050ms; border-color: rgba(255,255,255,.18); }

    /* The seal keeps the white disc the login screen gives it, so it holds
       its own edges against both the green and the photograph. */
    #tf-splash .tf-disc {
        position: relative;
        width: 84px;
        height: 84px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: #ffffff;
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, .55);
    }

    #tf-splash .tf-disc img {
        width: 66px;
        height: 66px;
        object-fit: contain;
    }

    #tf-splash .tf-title {
        margin: 26px 0 0;
        color: #ffffff;
        font-family: 'Inter Variable', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .18em;
        text-align: center;
        animation: tf-rise 500ms ease-out 1000ms both;
    }

    /* The full name, at the foot rather than under the seal: it is the one
       line that says what the system actually is, and it should not crowd
       the mark. */
    #tf-splash .tf-caption {
        position: absolute;
        left: 24px;
        right: 24px;
        bottom: calc(40px + env(safe-area-inset-bottom));
        margin: 0;
        color: rgba(255, 255, 255, .92);
        font-family: 'Inter Variable', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .14em;
        line-height: 1.6;
        text-align: center;
        animation: tf-rise 500ms ease-out 1250ms both;
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
        from { opacity: 0; transform: translateY(-80px) scale(.9); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    @keyframes tf-ring-out {
        from { opacity: 0; transform: translate(-50%, -50%) scale(.6); }
        to   { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    }

    @keyframes tf-rise {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes tf-fade-in {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    @keyframes tf-splash-out {
        from { opacity: 1; }
        to   { opacity: 0; visibility: hidden; pointer-events: none; }
    }

    /* Someone who has asked their device to stop animating gets the whole
       screen at once, held long enough to read, and then faded. Nothing
       moves, slides or drops. */
    @media (prefers-reduced-motion: reduce) {
        #tf-splash .tf-panel-top,
        #tf-splash .tf-panel-bottom { animation: none; }

        #tf-splash .tf-stage,
        #tf-splash .tf-mark,
        #tf-splash .tf-ring,
        #tf-splash .tf-title,
        #tf-splash .tf-caption { animation: tf-fade-in 250ms linear both; }

        #tf-splash { animation: tf-splash-out 400ms linear 2200ms forwards; }
    }
</style>

<div id="tf-splash" aria-hidden="true" role="presentation">
    <div class="tf-panel tf-panel-top"></div>
    <div class="tf-panel tf-panel-bottom"></div>

    <div class="tf-stage">
        <img class="tf-photo" src="{{ asset('images/fitsc-office.jpg') }}" alt="">
        <div class="tf-tint"></div>

        <div class="tf-centre">
            <div class="tf-mark">
                <span class="tf-ring tf-ring-1"></span>
                <span class="tf-ring tf-ring-2"></span>
                <span class="tf-ring tf-ring-3"></span>

                <span class="tf-disc">
                    <img src="{{ asset('images/tanza-seal.png') }}" alt="">
                </span>
            </div>

            <p class="tf-title">TANZA - FITS</p>
        </div>

        <p class="tf-caption">A Crop Damage Reporting and Assistance Allocation System</p>
    </div>
</div>

<script>
    (function () {
        // Remove the element once it has finished, so nothing is left
        // sitting over the form. The timeout is the animation's own length
        // plus a little; animationend is not used because the fade is on
        // the container and would also fire for everything inside it.
        window.setTimeout(function () {
            var splash = document.getElementById('tf-splash');
            if (splash && splash.parentNode) {
                splash.parentNode.removeChild(splash);
            }
        }, 3200);

        // Mark it played, so moving from Login to Register and back does
        // not replay it. Every page inside the system clears this flag
        // again (see layouts/app.blade.php), which is what makes it play
        // when somebody logs out and comes back.
        //
        // sessionStorage, not localStorage: closing the browser and
        // returning tomorrow should feel like opening the system again.
        // Wrapped because private browsing can make this throw.
        try {
            sessionStorage.setItem('tf-splash-done', '1');
        } catch (e) { /* no flag, so it plays again. Harmless. */ }
    })();
</script>
