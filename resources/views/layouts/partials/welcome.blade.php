{{--
    THE WELCOME SCREEN (sign-in screens, once per device)
    ---------------------------------------------------------------------
    What the system says for itself the first time it is opened on a phone
    or a computer: the farmer, the name, what the system is for, and one
    button into the login form behind it.

    WHY IT IS HERE AND NOT INSIDE THE SYSTEM

    Nobody is signed in yet, which is exactly the point. It greets every
    kind of user the same way - a farmer who has not registered, a
    technician on a borrowed phone, an officer at a shared desk - because
    at this moment the system does not know which of them has arrived.

    ONCE PER DEVICE

    localStorage, not sessionStorage, and not a column on a user: this is
    about the device, so it must survive closing the browser and must not
    care who signs in afterwards. The flag is written the moment the screen
    is shown rather than when the button is pressed. Writing it on the press
    meant closing the tab, hitting back, or just putting the phone down left
    the flag unwritten and the screen returned on every visit, which is the
    same mistake the farmer welcome made and fixed.

    IT SITS UNDER THE SPLASH, AND THAT IS THE TIMING

    No timer, no callback, no coordination with the splash at all. The
    splash is z-index 9999 and this is 9998, so this is already painted
    underneath while the green is still on screen, and the moment the splash
    fades out this is simply what is there. If the splash does not play -
    second visit this session, reduced motion, a browser that threw on
    sessionStorage - this appears immediately, which is also correct.

    WHY THE STYLE IS INLINE AND IN THE BODY

    Same reason as the splash. The compiled stylesheet is render-blocking,
    so the first paint happens when it arrives; by then this is already in
    the document. Keeping it independent also means a broken build cannot
    leave somebody looking at an undismissable green rectangle.

    IT CANNOT TRAP ANYONE

    Without JavaScript it is never shown at all: the element is hidden by
    default and the script at the bottom is what reveals it. So a browser
    with scripting off, or a script that failed to run, gets the login form
    directly rather than a screen with a button that does nothing.

    THE DRAWING IS ORIGINAL

    The farmer is drawn in SVG in this file, in the system's own greens.
    Nothing is traced from stock artwork, so there is no watermark and
    nothing to answer for at a defence, and it stays sharp at any size.
    It is the same figure as the farmer's own approval welcome
    (farmer/_onboarding.blade.php), deliberately: one system, one hand.
--}}

<style>
    /* Hidden until the script decides otherwise - see the note above. */
    #tf-welcome { display: none; }
    #tf-welcome.is-on { display: flex; }

    #tf-welcome, #tf-welcome *, #tf-welcome *::before, #tf-welcome *::after {
        box-sizing: border-box;
    }

    /* Pale green, not the blue-grey the rest of the system uses for a page
       background. This screen is one picture rather than a page of panels,
       and the figure belongs to a field: a green ground puts him in one,
       and it carries the eye down into the deep green card instead of
       changing subject at the seam. Light enough that the drawing's own
       greens still separate from it. */
    #tf-welcome {
        position: fixed;
        inset: 0;
        z-index: 9998;
        align-items: stretch;
        justify-content: center;
        background: #e7f1e1;
        overflow: hidden;
    }

    #tf-welcome.is-out {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 260ms ease, visibility 0s linear 260ms;
    }

    /* 100dvh, not 100vh: on a phone the browser's own chrome slides away as
       you scroll, and 100vh is the tall measurement, so the button would sit
       below the fold until something moved. */
    #tf-welcome .tfw-sheet {
        position: relative;
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 30rem;
        height: 100vh;
        height: 100dvh;
        background: #e7f1e1;
        overflow: hidden;
    }

    /* ---------------- the pale half ---------------- */

    /* flex-end: the figure stands ON the card's shoulder rather than
       floating in the middle of the pale half with a gap under it. */
    #tf-welcome .tfw-art {
        position: relative;
        flex: 1 1 auto;
        min-height: 8rem;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding-top: calc(0.5rem + env(safe-area-inset-top));
    }

    #tf-welcome .tfw-svg {
        width: auto;
        height: 100%;
        max-height: 19rem;
        max-width: 96%;
    }

    /* ---------------- the green card ---------------- */

    #tf-welcome .tfw-card {
        position: relative;
        flex: 0 0 auto;
        border-radius: 2rem 2rem 0 0;
        background: linear-gradient(170deg, #1b5c30 0%, #124a26 60%, #0d3d1b 100%);
        color: #ffffff;
        padding: 2.75rem 1.5rem calc(1.75rem + env(safe-area-inset-bottom));
        text-align: center;
        box-shadow: 0 -18px 40px -24px rgba(13, 61, 27, .55);
        font-family: 'Inter Variable', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
    }

    /* Half above the card's edge, half on it. */
    #tf-welcome .tfw-seal {
        position: absolute;
        top: -1.75rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 999px;
        background: #ffffff;
        box-shadow: 0 10px 22px -10px rgba(0, 0, 0, .5);
    }
    #tf-welcome .tfw-seal img { width: 2.6rem; height: 2.6rem; object-fit: contain; }

    #tf-welcome .tfw-inner { max-width: 34rem; margin: 0 auto; }

    #tf-welcome .tfw-wordmark {
        margin: 0 0 1rem;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.18em;
        color: rgba(255, 255, 255, 0.88);
    }

    #tf-welcome .tfw-headline {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: 0.01em;
        text-transform: uppercase;
    }

    #tf-welcome .tfw-body {
        margin: 1rem 0 0;
        font-size: 0.875rem;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.92);
    }

    #tf-welcome .tfw-body-fil {
        margin: 0.5rem 0 0;
        font-size: 0.8rem;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.72);
    }

    /* Pale on purpose, and only here: a pale button is wrong on a white
       page, which is why every button in the system itself is solid green.
       On this deep green card it is the light thing in a dark frame, and
       dark green type on it reads harder than white type on green could. */
    #tf-welcome .tfw-go {
        width: 100%;
        min-height: 3rem;
        margin-top: 1.5rem;
        border: 0;
        border-radius: 999px;
        background: #dcedd3;
        color: #0d3d1b;
        font-family: inherit;
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        cursor: pointer;
    }
    #tf-welcome .tfw-go-fil { font-weight: 500; opacity: 0.75; text-transform: none; }

    /* ---------------- the farmer ----------------
       Flat fills, because this is a figure rather than a pictogram and
       outlines would make it a colouring book. */
    #tf-welcome .tfw-skin        { fill: #f0c49b; }
    #tf-welcome .tfw-skin-shade  { fill: #dba77c; }
    #tf-welcome .tfw-hat         { fill: #c78f3f; }
    #tf-welcome .tfw-hat-crown   { fill: #d9a452; }
    #tf-welcome .tfw-hat-band    { fill: none; stroke: #8a5c22; stroke-width: 7; stroke-linecap: round; }
    #tf-welcome .tfw-shirt       { fill: #4f9a3f; }
    #tf-welcome .tfw-sleeve      { fill: none; stroke: #4f9a3f; stroke-width: 17; stroke-linecap: round; }
    #tf-welcome .tfw-denim       { fill: #2f6b5e; }
    /* butt, not round: a round cap adds half the stroke width past the end
       of the line, which is enough to push the strap out through the
       shoulder again. */
    #tf-welcome .tfw-strap       { fill: none; stroke: #2f6b5e; stroke-width: 9; stroke-linecap: butt; }
    #tf-welcome .tfw-btn         { fill: #e7d9a8; }
    #tf-welcome .tfw-tablet      { fill: #22343c; }
    #tf-welcome .tfw-screen      { fill: #46656f; }
    #tf-welcome .tfw-screen-line { fill: none; stroke: rgba(255,255,255,.55); stroke-width: 3; stroke-linecap: round; }

    /* The card rises into place once, under the splash, so by the time the
       green clears this is already settled rather than moving while being
       looked at. */
    #tf-welcome .tfw-card { animation: tfw-rise 520ms cubic-bezier(.22,.61,.36,1) both; }
    #tf-welcome .tfw-art  { animation: tfw-fade 520ms ease-out 120ms both; }

    @keyframes tfw-rise { from { transform: translateY(26px); opacity: 0; } to { transform: none; opacity: 1; } }
    @keyframes tfw-fade { from { opacity: 0; } to { opacity: 1; } }

    @media (prefers-reduced-motion: reduce) {
        #tf-welcome .tfw-card, #tf-welcome .tfw-art { animation: none; }
        #tf-welcome.is-out { transition: none; }
    }

    /* A short phone, or a phone held sideways. The artwork gives up its
       room first, because the words and the button are what the screen is
       for. The scroll is a last resort: a screen whose only action cannot
       be reached is worse than one you have to nudge. */
    @media (max-height: 700px) {
        #tf-welcome .tfw-svg      { max-height: 10rem; }
        #tf-welcome .tfw-headline { font-size: 1.15rem; }
        #tf-welcome .tfw-card     { padding-top: 2.5rem; }
    }

    @media (max-height: 580px) {
        #tf-welcome .tfw-art      { min-height: 0; }
        #tf-welcome .tfw-svg      { max-height: 7.5rem; }
        #tf-welcome .tfw-body-fil { display: none; }
        #tf-welcome .tfw-sheet    { overflow-y: auto; }
        #tf-welcome .tfw-card     { padding-top: 2.25rem; }
    }

    /* Width AND height. A phone held sideways is 740px wide and 380px tall:
       wide enough to look like a tablet to a width-only query, and far too
       short for the roomier layout. */
    /* On a laptop or a desktop this fills the window rather than floating a
       phone-shaped card in the middle of it. It is the first thing anyone
       sees of the system, a panel at a defence included, and a small card
       marooned in a field of green reads as something that failed to load.
       The card still runs the full width; only its words are measured. */
    @media (min-width: 640px) and (min-height: 700px) {
        #tf-welcome .tfw-sheet    { max-width: none; }
        #tf-welcome .tfw-art      { min-height: 0; padding-top: 2rem; }
        #tf-welcome .tfw-svg      { max-height: 30rem; max-width: 42rem; }
        #tf-welcome .tfw-card     { border-radius: 2.5rem 2.5rem 0 0; padding: 3.25rem 2rem 3rem; }
        #tf-welcome .tfw-seal     { top: -2.25rem; width: 4.5rem; height: 4.5rem; }
        #tf-welcome .tfw-seal img { width: 3.4rem; height: 3.4rem; }
        #tf-welcome .tfw-wordmark { font-size: 0.8rem; margin-bottom: 1.25rem; }
        #tf-welcome .tfw-headline { font-size: 2rem; }
        #tf-welcome .tfw-body     { font-size: 1rem; }
        #tf-welcome .tfw-body-fil { font-size: 0.875rem; }
        #tf-welcome .tfw-go       { width: auto; min-width: 20rem; padding: 0 2.5rem; font-size: 1rem; }
    }

    /* A big monitor: more room again for the figure, and the card keeps its
       share of the screen rather than becoming a green stripe. */
    @media (min-width: 1100px) and (min-height: 820px) {
        #tf-welcome .tfw-svg      { max-height: 36rem; max-width: 48rem; }
        #tf-welcome .tfw-headline { font-size: 2.3rem; }
        #tf-welcome .tfw-card     { padding: 3.75rem 2rem 3.5rem; }
    }
</style>

<div id="tf-welcome" role="dialog" aria-modal="true" aria-label="Welcome to TANZA - FITS">
    <div class="tfw-sheet">

        <div class="tfw-art">
            {{-- The viewBox is cropped tight to the drawing. Left loose the
                 figure sits in a box a third wider than itself, so every
                 size rule is really sizing empty space.

                 Order matters more than anything else here: torso, then the
                 sleeves on top of it, then the hands, then the tablet. The
                 other way round the arms disappear behind the shirt and the
                 hands read as two loose dots. --}}
            <svg class="tfw-svg" viewBox="38 14 182 198" aria-hidden="true">

                {{-- ground shadow, so the figure sits rather than floats --}}
                <ellipse cx="120" cy="198" rx="74" ry="9" fill="rgba(13,61,27,.08)"/>

                {{-- neck, head, hat. The brim is drawn after the head so it
                     falls across the forehead the way a real one does. --}}
                <path d="M110 92h20v26h-20z" class="tfw-skin-shade"/>
                <ellipse cx="120" cy="76" rx="27" ry="31" class="tfw-skin"/>
                <ellipse cx="120" cy="60" rx="76" ry="17" class="tfw-hat"/>
                <path d="M88 60c0-26 12-40 32-40s32 14 32 40z" class="tfw-hat-crown"/>
                <path d="M89 55h62" class="tfw-hat-band"/>

                {{-- torso --}}
                <path d="M74 210c0-44 5-66 16-76 7-7 18-12 30-12s23 5 30 12c11 10 16 32 16 76z"
                      class="tfw-shirt"/>

                {{-- sleeves, over the torso --}}
                <path d="M92 134c-12 11-18 28-20 47" class="tfw-sleeve"/>
                <path d="M150 134c13 5 22 13 26 23" class="tfw-sleeve"/>

                {{-- the overalls bib and its straps. The straps run up and
                     IN towards the neck, which is what they do on a real
                     pair seen from the front, and it keeps them inside the
                     shirt's shoulder line. Angled up and out they poke past
                     the shoulder and read as two horns. --}}
                <path d="M99 138h42v72H99z" class="tfw-denim"/>
                <path d="M104 139L112 128M136 139L128 128" class="tfw-strap"/>
                <circle cx="106" cy="150" r="3.4" class="tfw-btn"/>
                <circle cx="134" cy="150" r="3.4" class="tfw-btn"/>

                {{-- the tablet, and the hands --}}
                <g transform="rotate(-10 180 125)">
                    <rect x="156" y="84" width="48" height="66" rx="7" class="tfw-tablet"/>
                    <rect x="162" y="91" width="36" height="50" rx="3" class="tfw-screen"/>
                    <path d="M170 107h20M170 117h20M170 127h13" class="tfw-screen-line"/>
                </g>
                <circle cx="176" cy="156" r="11" class="tfw-skin"/>
                <circle cx="70" cy="180" r="11" class="tfw-skin"/>
            </svg>
        </div>

        <div class="tfw-card">
            <span class="tfw-seal">
                <img src="{{ asset('images/tanza-seal.png') }}" alt="">
            </span>

            {{-- The card runs the full width of the screen; its words do
                 not. A sentence measured across a 1600px monitor is a
                 sentence nobody finishes. --}}
            <div class="tfw-inner">

            <p class="tfw-wordmark">TANZA - FITS</p>

            <h1 class="tfw-headline">
                Every report matters.<br>Every farmer counts.
            </h1>

            <p class="tfw-body">
                Connecting farmers, technicians and the Municipal Agriculture Office to a
                simpler way of reporting, mapping, validating and coordinating assistance.
            </p>
            <p class="tfw-body-fil">
                Mas simpleng paraan ng pag-uulat ng pinsala at pagtulong sa mga magsasaka ng Tanza.
            </p>

            <button type="button" class="tfw-go" id="tf-welcome-go">
                Get Started <span class="tfw-go-fil">/ Magsimula</span>
            </button>

            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var KEY  = 'tf-welcome-done';
        var root = document.getElementById('tf-welcome');

        if (! root) return;

        // Has this device already been greeted? A browser that throws on
        // localStorage (private mode, blocked storage) is treated as a
        // device that has not, which shows the screen rather than losing it.
        var seen = false;

        try {
            seen = localStorage.getItem(KEY) === '1';
        } catch (e) { /* no storage, so show it */ }

        if (seen) {
            // Nothing is ever revealed, and the element is taken out of the
            // document so it cannot sit invisibly over the login form.
            if (root.parentNode) root.parentNode.removeChild(root);
            return;
        }

        root.className += ' is-on';

        // Stamped on show, not on the button: closing the tab, hitting back
        // or simply putting the phone down must not bring it back forever.
        try {
            localStorage.setItem(KEY, '1');
        } catch (e) { /* it will greet this device once more. Harmless. */ }

        // Nothing underneath should scroll while this is over it.
        var previousOverflow = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';

        function dismiss() {
            root.className += ' is-out';
            document.documentElement.style.overflow = previousOverflow;

            // Removed after the fade rather than on the click, so the fade
            // is actually seen. The class alone already makes it
            // unclickable, so a failed timer cannot trap anybody.
            window.setTimeout(function () {
                if (root.parentNode) root.parentNode.removeChild(root);
            }, 320);
        }

        document.getElementById('tf-welcome-go').addEventListener('click', dismiss);

        // Escape too. It is a dialog over a form, and anybody who knows that
        // key expects it to work.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') dismiss();
        });
    })();
</script>
