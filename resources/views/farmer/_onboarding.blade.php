{{--
    THE APPROVAL WELCOME (farmers, once per account)

    What a farmer sees the first time they reach their dashboard after the
    Municipal Agriculture Office approves them: a congratulation, then three
    cards on what the system is for. It is the one moment the system gets to
    introduce itself to the person it was built for.

    THE SHAPE

    Artwork on a pale ground up top, a deep green card with rounded
    shoulders below it carrying the words and the button, and the municipal
    seal sitting on the seam between the two. Phone-first: this is the
    screen a farmer meets on their own phone, standing in their own yard,
    and it is laid out for a thumb before anything else. On a tablet or a
    desktop the same card simply sits in the middle with more room around
    it, rather than stretching one sentence across a monitor.

    TWO TRACKS, ONE INDEX

    The artwork and the words slide separately, above and inside the green
    card, both driven by the same index. One track carrying both would mean
    the green card sliding off and on with its own rounded corners showing
    the pale ground through the gap between slides. This way the card is a
    fixed stage and only its contents move, which is what the design is.

    WHEN IT SHOWS

    Farmer\DashboardController stamps farmers.approval_welcome_shown_at the
    moment it decides to show this, not when the farmer finishes it. That
    was a deliberate decision made for the old dialog and it still holds:
    stamping on the final tap meant closing the app, going back, or logging
    in again left the flag null and the welcome reappeared on every login.
    So this is genuinely once, even if they swipe away halfway.

    IT IS NOT THE SPLASH

    The splash plays on the sign-in screens, for everybody, every time they
    arrive signed out (layouts/partials/splash.blade.php). This plays once
    in a farmer's life, inside the system, after approval. They share the
    seal and the green, and nothing else.

    WHY THE CSS IS IN HERE

    The animations are plain keyframes in a style block rather than Tailwind
    utilities, so this screen works the moment the file is deployed, without
    waiting for a stylesheet rebuild. Everything animated is decoration:
    turn the animations off and the slides still read exactly the same.
    prefers-reduced-motion is honoured for farmers who have asked their
    phone to keep still.

    TOUCH

    Swipe left and right, or use the buttons. The buttons are the reliable
    path, the swipe is the nice one: nothing here depends on a gesture a
    farmer might not think to try.

    THE DRAWINGS ARE ORIGINAL

    Every figure here is drawn in SVG in this file, in the system's own
    greens. Nothing is traced from stock artwork, so there is no watermark,
    no licence to produce and nothing to answer for at a defence. It also
    stays sharp at any size and costs no download.
--}}

<div x-data="farmerOnboarding()"
     x-show="open"
     x-cloak
     class="ob-root"
     role="dialog"
     aria-modal="true"
     aria-label="Welcome to the crop damage reporting system"
     @touchstart.passive="onTouchStart($event)"
     @touchmove.passive="onTouchMove($event)"
     @touchend="onTouchEnd()">

    <div class="ob-sheet">

        {{-- ================= ARTWORK, ON THE PALE GROUND ================= --}}
        <div class="ob-art-area">

            {{-- Skip. Top right, out of the way of the thumb. --}}
            <button type="button" class="ob-skip" @click="finish()">
                Skip <span class="ob-skip-fil">/ Laktawan</span>
            </button>

            <div class="ob-art-viewport">
                <div class="ob-track" :style="`transform: translateX(-${index * 100}%)`">

                    {{-- 1. The farmer.

                         Flat shapes, no outline, no face. A blank face is
                         deliberate: this figure stands for every farmer in
                         Tanza, and the moment it has eyes and a mouth it
                         starts being a particular one. --}}
                    <div class="ob-art" aria-hidden="true">
                        {{-- The viewBox is cropped tight to the drawing. Left loose at
                                 0 0 240 210 the figure sat in a box a third
                                 wider than itself, so every size rule was
                                 really sizing empty space. --}}
                            <svg viewBox="38 14 182 198" class="ob-svg ob-svg-wide">

                            {{-- ground shadow, so the figure sits rather than floats --}}
                            <ellipse cx="120" cy="198" rx="74" ry="9" fill="rgba(13,61,27,.08)"/>

                            {{-- Order matters more than anything else in this
                                 drawing. Torso, then the sleeves on top of
                                 it, then the hands, then the tablet. Drawn
                                 the other way round the arms disappear
                                 behind the shirt and the hands read as two
                                 loose dots. --}}

                            {{-- neck, head, hat. The brim is drawn after the
                                 head so it falls across the forehead the way
                                 a real one does. --}}
                            <path d="M110 92h20v26h-20z" class="ob-skin-shade"/>
                            <ellipse cx="120" cy="76" rx="27" ry="31" class="ob-skin"/>
                            <ellipse cx="120" cy="60" rx="76" ry="17" class="ob-hat"/>
                            <path d="M88 60c0-26 12-40 32-40s32 14 32 40z" class="ob-hat-crown"/>
                            <path d="M89 55h62" class="ob-hat-band"/>

                            {{-- torso --}}
                            <path d="M74 210c0-44 5-66 16-76 7-7 18-12 30-12s23 5 30 12c11 10 16 32 16 76z"
                                  class="ob-shirt"/>

                            {{-- sleeves, over the torso --}}
                            <path d="M92 134c-12 11-18 28-20 47" class="ob-sleeve"/>
                            <path d="M150 134c13 5 22 13 26 23" class="ob-sleeve"/>

                            {{-- the overalls bib and its straps --}}
                            <path d="M99 138h42v72H99z" class="ob-denim"/>

                            {{-- The straps run up and IN towards the neck,
                                 which is what they do on a real pair seen
                                 from the front, and it keeps them inside the
                                 shirt's shoulder line. Angled up and out
                                 they poked past the shoulder and read as two
                                 horns. --}}
                            <path d="M104 139L112 128M136 139L128 128" class="ob-strap"/>
                            <circle cx="106" cy="150" r="3.4" class="ob-button"/>
                            <circle cx="134" cy="150" r="3.4" class="ob-button"/>

                            {{-- the tablet, and the hand holding it --}}
                            <g transform="rotate(-10 180 125)">
                                <rect x="156" y="84" width="48" height="66" rx="7" class="ob-tablet"/>
                                <rect x="162" y="91" width="36" height="50" rx="3" class="ob-screen"/>
                                <path d="M170 107h20M170 117h20M170 127h13" class="ob-screen-line"/>
                            </g>
                            <circle cx="176" cy="156" r="11" class="ob-skin"/>
                            <circle cx="70" cy="180" r="11" class="ob-skin"/>
                        </svg>
                    </div>

                    {{-- 2. Planting --}}
                    <div class="ob-art" aria-hidden="true">
                        <svg viewBox="0 0 160 160" class="ob-svg">
                            <path d="M30 128h100" class="ob-ground"/>
                            <path d="M80 128V74" class="ob-stem"/>
                            <path d="M80 92c0-16 12-26 28-26 0 16-12 26-28 26z" class="ob-leaf ob-leaf-r"/>
                            <path d="M80 104c0-16-12-26-28-26 0 16 12 26 28 26z" class="ob-leaf ob-leaf-l"/>
                            <circle cx="80" cy="66" r="7" class="ob-bud"/>
                        </svg>
                    </div>

                    {{-- 3. Damage --}}
                    <div class="ob-art" aria-hidden="true">
                        <svg viewBox="0 0 160 160" class="ob-svg">
                            <path d="M52 74a20 20 0 0139-6 16 16 0 011 32H56a17 17 0 01-4-26z" class="ob-cloud"/>
                            <path d="M62 116l-6 14" class="ob-rain ob-rain-1"/>
                            <path d="M82 116l-6 14" class="ob-rain ob-rain-2"/>
                            <path d="M102 116l-6 14" class="ob-rain ob-rain-3"/>
                            <path d="M34 140h92" class="ob-ground"/>
                        </svg>
                    </div>

                    {{-- 4. Assistance --}}
                    <div class="ob-art" aria-hidden="true">
                        <svg viewBox="0 0 160 160" class="ob-svg">
                            <path d="M46 132c0-18 15-32 34-32s34 14 34 32z" class="ob-sack"/>
                            <path d="M62 100l10-16h16l10 16" class="ob-sack-top"/>
                            <circle cx="80" cy="56" r="14" class="ob-coin ob-coin-1"/>
                            <circle cx="56" cy="66" r="10" class="ob-coin ob-coin-2"/>
                            <circle cx="104" cy="66" r="10" class="ob-coin ob-coin-3"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= THE GREEN CARD ================= --}}
        <div class="ob-card">

            {{-- The seal on the seam, half on the artwork and half on the
                 card: the one mark that says which office this is. --}}
            <span class="ob-seal">
                <img src="{{ asset('images/tanza-seal.png') }}" alt="">
            </span>

            {{-- The card runs the full width of the screen on a desktop;
                 its words do not. A sentence measured across a 1600px
                 monitor is a sentence nobody finishes. --}}
            <div class="ob-inner">

            <p class="ob-wordmark">TANZA - FITS</p>

            <div class="ob-copy-viewport">
                <div class="ob-track" :style="`transform: translateX(-${index * 100}%)`">

                    {{-- 1 --}}
                    <section class="ob-slide">
                        <p class="ob-eyebrow">
                            Congratulations, {{ $farmer->first_name }}. Your registration is approved.
                        </p>

                        <h2 class="ob-headline">
                            Every report matters.<br>Every farmer counts.
                        </h2>

                        <p class="ob-body">
                            Connecting farmers to a simpler way of reporting, mapping, validating
                            and coordinating assistance.
                        </p>
                        <p class="ob-body-fil">
                            Naaprubahan na po ng Municipal Agriculture Office ang inyong rehistrasyon.
                        </p>
                    </section>

                    {{-- 2 --}}
                    <section class="ob-slide">
                        <h2 class="ob-title">Record what you plant</h2>
                        <p class="ob-title-fil">Itala ang inyong pagtatanim</p>

                        <p class="ob-body">
                            Enter the crop, the date you planted it and the area in hectares. Keeping
                            this up to date is also what keeps your account active.
                        </p>
                        <p class="ob-body-fil">
                            Ilagay po ang pananim, petsa ng pagtatanim at laki ng lupa. Ito rin po ang
                            nagpapanatiling aktibo ng inyong account.
                        </p>
                    </section>

                    {{-- 3 --}}
                    <section class="ob-slide">
                        <h2 class="ob-title">Report crop damage</h2>
                        <p class="ob-title-fil">Iulat ang pinsala sa pananim</p>

                        <p class="ob-body">
                            After a typhoon, flood or drought, file a report with photos and the
                            location of your farm. A technician then visits and confirms the damage.
                        </p>
                        <p class="ob-body-fil">
                            Kapag may bagyo, baha o tagtuyot, magsumite po ng ulat na may larawan at
                            lokasyon. May technician na bibisita upang tingnan ang pinsala.
                        </p>
                    </section>

                    {{-- 4 --}}
                    <section class="ob-slide">
                        <h2 class="ob-title">Follow your assistance</h2>
                        <p class="ob-title-fil">Subaybayan ang tulong</p>

                        <p class="ob-body">
                            Once your report is verified, any cash or in-kind assistance allocated to
                            you appears under Assistance, where you confirm that you received it.
                        </p>
                        <p class="ob-body-fil">
                            Kapag na-verify na ang ulat, makikita po sa Assistance ang tulong na
                            nakalaan sa inyo, at doon ninyo kumpirmahin na natanggap ito.
                        </p>
                    </section>
                </div>
            </div>

            {{-- ================= CONTROLS ================= --}}
            <div class="ob-foot">

                {{-- Dots. Tappable, so a farmer can go back to a slide. --}}
                <div class="ob-dots">
                    <template x-for="i in count" :key="i">
                        <button type="button"
                                class="ob-dot"
                                :class="index === i - 1 ? 'is-on' : ''"
                                :aria-label="`Go to slide ${i}`"
                                @click="index = i - 1"></button>
                    </template>
                </div>

                {{-- Pale on purpose, and only here: a pale button is wrong on
                     a white page, which is why every other button in the
                     system is solid green. On this deep green card it is the
                     light thing in a dark frame, and dark green type on it
                     reads harder than white type on green ever could. --}}
                <button type="button" class="ob-next" @click="next()">
                    <span x-show="index === 0">
                        Get Started <span class="ob-next-fil">/ Magsimula</span>
                    </span>
                    <span x-show="index > 0 && index < count - 1" x-cloak>
                        Next <span class="ob-next-fil">/ Susunod</span>
                    </span>
                    <span x-show="index === count - 1" x-cloak>
                        Go to my dashboard <span class="ob-next-fil">/ Tapos na</span>
                    </span>
                </button>

                {{-- Always in the layout, hidden on the first slide rather
                     than removed. Taken out of the flow it changed the
                     card's height, so the whole card jumped up a line the
                     moment you moved off slide one. --}}
                <button type="button" class="ob-back"
                        :class="index > 0 ? '' : 'is-hidden'"
                        :tabindex="index > 0 ? 0 : -1"
                        @click="prev()">
                    Back / Bumalik
                </button>
            </div>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function farmerOnboarding() {
        return {
            open: true,
            index: 0,
            count: 4,
            startX: null,
            startY: null,
            deltaX: 0,

            next() {
                if (this.index < this.count - 1) {
                    this.index++;
                } else {
                    this.finish();
                }
            },

            prev() {
                if (this.index > 0) this.index--;
            },

            // The flag is already stamped server-side on this page load, so
            // closing is purely a matter of getting out of the way.
            finish() {
                this.open = false;
                document.documentElement.style.overflow = '';
            },

            onTouchStart(e) {
                this.startX = e.changedTouches[0].clientX;
                this.startY = e.changedTouches[0].clientY;
                this.deltaX = 0;
            },

            onTouchMove(e) {
                if (this.startX === null) return;

                // A mostly-vertical drag is somebody trying to scroll, not
                // to change slide, so it is thrown away rather than counted.
                const x = e.changedTouches[0].clientX - this.startX;
                const y = e.changedTouches[0].clientY - this.startY;

                this.deltaX = Math.abs(y) > Math.abs(x) ? 0 : x;
            },

            // 48px of travel before it counts, so a tap or a vertical scroll
            // never flips the slide by accident.
            onTouchEnd() {
                if (this.startX === null) return;
                if (this.deltaX <= -48) this.next();
                if (this.deltaX >= 48) this.prev();
                this.startX = null;
                this.startY = null;
                this.deltaX = 0;
            },

            init() {
                document.documentElement.style.overflow = 'hidden';
            },
        };
    }
</script>
@endpush

@push('head')
<style>
    /* 100dvh, not 100vh: on a phone the browser's own chrome slides away as
       you scroll, and 100vh is the tall measurement, so the button would sit
       below the fold until something moved. */
    /* Declared here rather than relied on from the stylesheet. The whole
       point of this block is that the screen works the moment the file is
       deployed; borrowing one rule from a build that has not run yet would
       undo that, and without it the padding on a slide is added OUTSIDE its
       width and the last letter of a line is cut off. */
    .ob-root, .ob-root *, .ob-root *::before, .ob-root *::after {
        box-sizing: border-box;
    }

    .ob-root {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        align-items: stretch;
        justify-content: center;
        /* Pale green, not the blue-grey the rest of the system uses for a
           page background. This screen is one picture rather than a page of
           panels, and the figure belongs to a field: a green ground puts him
           in one, and carries the eye down into the deep green card instead
           of changing subject at the seam. It matches the welcome screen on
           the sign-in pages, which is the same drawing on the same ground. */
        background: #e7f1e1;
        overflow: hidden;
    }

    .ob-sheet {
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

    /* flex-end, not center: the figure is meant to stand ON the card's
       shoulder, the way it does in the design, rather than hovering in the
       middle of the pale half with a gap under it. On a tall phone that gap
       was most of the screen. */
    .ob-art-area {
        position: relative;
        flex: 1 1 auto;
        min-height: 9rem;
        display: flex;
        align-items: flex-end;
        padding-top: calc(0.5rem + env(safe-area-inset-top));
    }

    /* A pill, not bare text. Sitting over the artwork it had the hat
       behind it on a wide screen, and grey on an illustration is not
       something you can rely on being readable. */
    .ob-skip {
        position: absolute;
        top: calc(0.75rem + env(safe-area-inset-top));
        right: 0.85rem;
        z-index: 2;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.8);
        color: #3d4f55;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.4rem 0.85rem;
        cursor: pointer;
    }
    .ob-skip-fil { font-weight: 400; opacity: 0.75; }

    /* The artwork takes whatever height the pale half has left after the
       card, rather than a fixed size that leaves a tall phone two thirds
       empty and crowds a short one. The max-heights stop it running away
       on a desktop. */
    .ob-art-viewport { width: 100%; height: 100%; overflow: hidden; }
    .ob-art-viewport .ob-track { height: 100%; }

    /* The slides live in a track that slides sideways. overflow hidden on
       the viewport is what turns four slides into one at a time. */
    .ob-track {
        display: flex;
        width: 100%;
        transition: transform 380ms cubic-bezier(0.22, 0.61, 0.36, 1);
    }

    .ob-art {
        flex: 0 0 100%;
        min-width: 100%;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: flex-end;
    }

    .ob-svg {
        width: auto;
        height: 100%;
        max-height: 11rem;
        max-width: 80%;
        overflow: visible;
    }

    /* The farmer is a wider drawing than the three pictograms, and it is
       meant to run right down to the card's edge. */
    .ob-svg-wide { max-height: 19rem; max-width: 96%; }

    /* ---------------- the green card ---------------- */

    .ob-card {
        position: relative;
        flex: 0 0 auto;
        border-radius: 2rem 2rem 0 0;
        background: linear-gradient(170deg, #1b5c30 0%, #124a26 60%, #0d3d1b 100%);
        color: #ffffff;
        padding: 2.75rem 1.5rem calc(1.5rem + env(safe-area-inset-bottom));
        text-align: center;
        box-shadow: 0 -18px 40px -24px rgba(13, 61, 27, .55);
    }

    /* Half above the card's edge, half on it. */
    .ob-seal {
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
    .ob-seal img { width: 2.6rem; height: 2.6rem; object-fit: contain; }

    .ob-inner { max-width: 34rem; margin: 0 auto; }

    .ob-wordmark {
        margin: 0 0 1rem;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.18em;
        color: rgba(255, 255, 255, 0.88);
    }

    .ob-copy-viewport { overflow: hidden; }

    .ob-slide {
        flex: 0 0 100%;
        min-width: 100%;
        padding: 0 0.25rem;
    }

    .ob-eyebrow {
        margin: 0 0 0.6rem;
        font-size: 0.8rem;
        font-weight: 600;
        line-height: 1.45;
        color: #a7e2a0;
    }

    .ob-headline {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: 0.01em;
        text-transform: uppercase;
    }

    .ob-title {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        line-height: 1.2;
    }
    .ob-title-fil {
        margin: 0.2rem 0 0;
        font-size: 0.9rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.72);
    }
    .ob-body {
        margin: 0.85rem 0 0;
        font-size: 0.875rem;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.92);
    }
    .ob-body-fil {
        margin: 0.5rem 0 0;
        font-size: 0.8rem;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.72);
    }

    .ob-foot {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.9rem;
        margin-top: 1.25rem;
    }

    .ob-dots { display: flex; gap: 0.5rem; }
    .ob-dot {
        width: 0.5rem;
        height: 0.5rem;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.35);
        padding: 0;
        cursor: pointer;
        transition: width 220ms ease, background 220ms ease;
    }
    .ob-dot.is-on { width: 1.5rem; background: #ffffff; }

    .ob-next {
        width: 100%;
        min-height: 3rem;
        border: 0;
        border-radius: 999px;
        background: #dcedd3;
        color: #0d3d1b;
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        cursor: pointer;
    }
    .ob-next-fil { font-weight: 500; opacity: 0.75; text-transform: none; }

    .ob-back.is-hidden { visibility: hidden; pointer-events: none; }

    .ob-back {
        border: 0;
        background: transparent;
        color: rgba(255, 255, 255, 0.75);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        padding: 0.25rem;
    }

    /* ---------------- the farmer ----------------
       Flat fills, because this one is a figure rather than a pictogram and
       outlines would make it a colouring book. */
    .ob-skin       { fill: #f0c49b; }
    .ob-skin-shade { fill: #dba77c; }
    .ob-hat        { fill: #c78f3f; }
    .ob-hat-crown  { fill: #d9a452; }
    .ob-hat-band   { fill: none; stroke: #8a5c22; stroke-width: 7; stroke-linecap: round; }
    .ob-shirt      { fill: #4f9a3f; }
    .ob-sleeve     { fill: none; stroke: #4f9a3f; stroke-width: 17; stroke-linecap: round; }
    .ob-denim      { fill: #2f6b5e; }
    /* butt, not round: a round cap adds half the stroke width past the end
       of the line, which is exactly enough to push the strap out through
       the shoulder again. */
    .ob-strap      { fill: none; stroke: #2f6b5e; stroke-width: 9; stroke-linecap: butt; }
    .ob-button     { fill: #e7d9a8; }
    .ob-tablet     { fill: #22343c; }
    .ob-screen     { fill: #46656f; }
    .ob-screen-line{ fill: none; stroke: rgba(255,255,255,.55); stroke-width: 3; stroke-linecap: round; }

    /* ---------------- the three pictograms ----------------
       Stroke-only artwork, now in the system's green because it sits on the
       pale ground rather than on the card. The animations are transforms
       and stroke-dashoffset only, nothing that forces layout on an old
       phone. */
    .ob-ground, .ob-stem, .ob-leaf, .ob-cloud, .ob-rain, .ob-sack, .ob-sack-top {
        fill: none;
        stroke: #166534;
        stroke-width: 5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .ob-bud, .ob-coin { fill: #166534; stroke: none; }
    .ob-leaf  { fill: rgba(22, 101, 52, 0.16); }
    .ob-cloud { fill: rgba(22, 101, 52, 0.12); }
    .ob-sack  { fill: rgba(22, 101, 52, 0.14); }

    .ob-stem { transform-origin: 80px 128px; animation: ob-grow 1.6s ease-in-out infinite alternate; }
    .ob-leaf-r { transform-origin: 80px 92px; animation: ob-sway 2.4s ease-in-out infinite alternate; }
    .ob-leaf-l { transform-origin: 80px 104px; animation: ob-sway 2.4s 300ms ease-in-out infinite alternate-reverse; }
    .ob-bud { animation: ob-pulse 2s ease-in-out infinite; }

    .ob-cloud { animation: ob-drift 3.2s ease-in-out infinite alternate; }
    .ob-rain { opacity: 0; animation: ob-fall 1.4s linear infinite; }
    .ob-rain-2 { animation-delay: 0.35s; }
    .ob-rain-3 { animation-delay: 0.7s; }

    .ob-coin-1 { animation: ob-drop 2.2s ease-in-out infinite; }
    .ob-coin-2 { animation: ob-drop 2.2s 0.3s ease-in-out infinite; }
    .ob-coin-3 { animation: ob-drop 2.2s 0.6s ease-in-out infinite; }

    @keyframes ob-grow   { from { transform: scaleY(0.9); } to { transform: scaleY(1); } }
    @keyframes ob-sway   { from { transform: rotate(-5deg); } to { transform: rotate(5deg); } }
    @keyframes ob-pulse  { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.18); } }
    @keyframes ob-drift  { from { transform: translateX(-5px); } to { transform: translateX(5px); } }
    @keyframes ob-fall   { 0% { opacity: 0; transform: translateY(-8px); }
                           30% { opacity: 1; }
                           100% { opacity: 0; transform: translateY(14px); } }
    @keyframes ob-drop   { 0%, 100% { transform: translateY(0); }
                           50% { transform: translateY(-10px); } }

    @media (prefers-reduced-motion: reduce) {
        .ob-track { transition: none; }
        .ob-stem, .ob-leaf, .ob-bud, .ob-cloud, .ob-rain, .ob-coin { animation: none; }
        .ob-rain { opacity: 1; }
    }

    /* A short phone, or a phone held sideways. The artwork is the first
       thing to give up its room, because the words and the button are what
       the screen is for. */
    @media (max-height: 700px) {
        .ob-svg       { max-height: 7rem; }
        .ob-svg-wide  { max-height: 10rem; }
        .ob-headline  { font-size: 1.15rem; }
        .ob-title     { font-size: 1.2rem; }
        .ob-card      { padding-top: 2.5rem; }
    }

    @media (max-height: 580px) {
        .ob-art-area  { min-height: 0; }
        .ob-svg       { max-height: 5.5rem; }
        .ob-svg-wide  { max-height: 7.5rem; }
        .ob-body-fil  { display: none; }

        /* The last resort, for a phone held sideways with the keyboard up.
           Everything above shrinks what it can; if the button would still
           fall off the bottom, the sheet scrolls to it rather than hiding
           it. A screen whose only action cannot be reached is worse than a
           screen you have to nudge. */
        .ob-sheet { overflow-y: auto; }
        .ob-card  { padding-top: 2.25rem; }
    }

    /* A tall phone has room to spare above the card, and an empty pale
       half reads as something failing to load. The figure takes the room. */
    @media (min-height: 780px) {
        .ob-svg-wide { max-height: 22rem; }
        .ob-svg      { max-height: 13rem; }
    }

    /* Tablet and up: the card gets room to breathe rather than filling a
       1600px monitor with one sentence. */
    /* Width AND height. A phone held sideways is 740px wide and 380px tall:
       wide enough to look like a tablet to a width-only query, and far too
       short for the roomier layout, which pushed the button off the bottom
       of the screen. */
    @media (min-width: 640px) and (min-height: 700px) {
        /* On a laptop or a desktop this fills the window. It was a
           phone-shaped card floating in the middle of a field of green,
           which wasted the screen and read as something that had failed to
           load. The card still runs the full width; only its words are
           measured, by .ob-inner. */
        .ob-sheet     { max-width: none; }
        .ob-art-area  { min-height: 0; padding-top: 2rem; }
        .ob-svg       { max-height: 20rem; }
        .ob-svg-wide  { max-height: 30rem; max-width: 42rem; }
        .ob-card      { border-radius: 2.5rem 2.5rem 0 0; padding: 3.25rem 2rem 2.5rem; }
        .ob-seal      { top: -2.25rem; width: 4.5rem; height: 4.5rem; }
        .ob-seal img  { width: 3.4rem; height: 3.4rem; }
        .ob-wordmark  { font-size: 0.8rem; margin-bottom: 1.25rem; }
        .ob-headline  { font-size: 2rem; }
        .ob-title     { font-size: 1.9rem; }
        .ob-body      { font-size: 1rem; }
        .ob-body-fil  { font-size: 0.875rem; }
        .ob-next      { width: auto; min-width: 20rem; padding: 0 2.5rem; font-size: 1rem; }
        .ob-skip      { right: 1.5rem; }
    }

    /* A big monitor: more room again for the artwork. */
    @media (min-width: 1100px) and (min-height: 820px) {
        .ob-svg-wide  { max-height: 34rem; max-width: 46rem; }
        .ob-svg       { max-height: 22rem; }
        .ob-headline  { font-size: 2.2rem; }
        .ob-title     { font-size: 2.1rem; }
    }
</style>
@endpush
