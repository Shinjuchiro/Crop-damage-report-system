{{--
    FIRST-RUN ONBOARDING (farmers, once per account)

    Four full-screen cards a farmer swipes through the first time they
    reach their dashboard after the office approves them. It replaces the
    plain "Registration Approved" dialog that used to sit here: same
    trigger, same once-per-account flag, more of an introduction.

    WHEN IT SHOWS

    Farmer\DashboardController stamps farmers.approval_welcome_shown_at the
    moment it decides to show this, not when the farmer finishes it. That
    was a deliberate decision made for the old dialog and it still holds:
    stamping on the final tap meant closing the app, going back, or logging
    in again left the flag null and the welcome reappeared on every login.
    So this is genuinely once, even if they swipe away halfway.

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
--}}

<div x-data="farmerOnboarding()"
     x-show="open"
     x-cloak
     class="ob-root"
     role="dialog"
     aria-modal="true"
     aria-label="Welcome to the crop damage reporting system">

    <div class="ob-sheet">

        {{-- Skip. Top right, out of the way of the thumb. --}}
        <button type="button" class="ob-skip" @click="finish()">
            Skip <span class="ob-skip-fil">/ Laktawan</span>
        </button>

        {{-- ================= THE SLIDES ================= --}}
        <div class="ob-viewport"
             @touchstart.passive="onTouchStart($event)"
             @touchmove.passive="onTouchMove($event)"
             @touchend="onTouchEnd()">

            <div class="ob-track" :style="`transform: translateX(-${index * 100}%)`">

                {{-- 1. Approved --}}
                <section class="ob-slide">
                    <div class="ob-art" aria-hidden="true">
                        <svg viewBox="0 0 160 160" class="ob-svg">
                            <circle cx="80" cy="80" r="58" class="ob-disc"/>
                            <circle cx="80" cy="80" r="58" class="ob-ring"/>
                            <path d="M56 82l16 16 34-36" class="ob-check"/>
                        </svg>
                    </div>

                    <h2 class="ob-title">Welcome, {{ $farmer->first_name }}</h2>
                    <p class="ob-title-fil">Maligayang pagdating po</p>

                    <p class="ob-body">
                        The Municipal Agriculture Office has approved your registration, so your
                        account is active. Here is what you can do with it.
                    </p>
                    <p class="ob-body-fil">
                        Naaprubahan na po ang inyong rehistrasyon. Narito po ang mga magagawa ninyo.
                    </p>
                </section>

                {{-- 2. Planting --}}
                <section class="ob-slide">
                    <div class="ob-art" aria-hidden="true">
                        <svg viewBox="0 0 160 160" class="ob-svg">
                            <path d="M30 128h100" class="ob-ground"/>
                            <path d="M80 128V74" class="ob-stem"/>
                            <path d="M80 92c0-16 12-26 28-26 0 16-12 26-28 26z" class="ob-leaf ob-leaf-r"/>
                            <path d="M80 104c0-16-12-26-28-26 0 16 12 26 28 26z" class="ob-leaf ob-leaf-l"/>
                            <circle cx="80" cy="66" r="7" class="ob-bud"/>
                        </svg>
                    </div>

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

                {{-- 3. Damage --}}
                <section class="ob-slide">
                    <div class="ob-art" aria-hidden="true">
                        <svg viewBox="0 0 160 160" class="ob-svg">
                            <path d="M52 74a20 20 0 0139-6 16 16 0 011 32H56a17 17 0 01-4-26z" class="ob-cloud"/>
                            <path d="M62 116l-6 14" class="ob-rain ob-rain-1"/>
                            <path d="M82 116l-6 14" class="ob-rain ob-rain-2"/>
                            <path d="M102 116l-6 14" class="ob-rain ob-rain-3"/>
                            <path d="M34 140h92" class="ob-ground"/>
                        </svg>
                    </div>

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

                {{-- 4. Assistance --}}
                <section class="ob-slide">
                    <div class="ob-art" aria-hidden="true">
                        <svg viewBox="0 0 160 160" class="ob-svg">
                            <path d="M46 132c0-18 15-32 34-32s34 14 34 32z" class="ob-sack"/>
                            <path d="M62 100l10-16h16l10 16" class="ob-sack-top"/>
                            <circle cx="80" cy="56" r="14" class="ob-coin ob-coin-1"/>
                            <circle cx="56" cy="66" r="10" class="ob-coin ob-coin-2"/>
                            <circle cx="104" cy="66" r="10" class="ob-coin ob-coin-3"/>
                        </svg>
                    </div>

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

            <button type="button" class="ob-next" @click="next()">
                <span x-show="index < count - 1">Next <span class="ob-next-fil">/ Susunod</span></span>
                <span x-show="index === count - 1" x-cloak>Get Started <span class="ob-next-fil">/ Magsimula</span></span>
            </button>

            <button type="button" class="ob-back" x-show="index > 0" x-cloak @click="prev()">
                Back / Bumalik
            </button>
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
                this.deltaX = 0;
            },

            onTouchMove(e) {
                if (this.startX === null) return;
                this.deltaX = e.changedTouches[0].clientX - this.startX;
            },

            // 48px of travel before it counts, so a tap or a vertical scroll
            // never flips the slide by accident.
            onTouchEnd() {
                if (this.startX === null) return;
                if (this.deltaX <= -48) this.next();
                if (this.deltaX >= 48) this.prev();
                this.startX = null;
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
    .ob-root {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        align-items: stretch;
        justify-content: center;
        background: linear-gradient(160deg, #0d3d1b 0%, #166534 55%, #2f9e41 100%);
        padding: env(safe-area-inset-top) 0 env(safe-area-inset-bottom);
    }

    .ob-sheet {
        position: relative;
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 30rem;
        padding: 1.25rem 1.25rem 1.5rem;
        color: #ffffff;
    }

    .ob-skip {
        align-self: flex-end;
        border: 0;
        background: transparent;
        color: rgba(255, 255, 255, 0.75);
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.5rem 0.25rem;
        cursor: pointer;
    }
    .ob-skip-fil { font-weight: 400; opacity: 0.8; }

    /* The slides live in a track that slides sideways. overflow hidden on
       the viewport is what turns four slides into one at a time. */
    .ob-viewport { flex: 1; overflow: hidden; display: flex; align-items: center; }

    .ob-track {
        display: flex;
        width: 100%;
        transition: transform 380ms cubic-bezier(0.22, 0.61, 0.36, 1);
    }

    .ob-slide {
        flex: 0 0 100%;
        min-width: 100%;
        text-align: center;
        padding: 0 0.25rem;
    }

    .ob-art { display: flex; justify-content: center; margin-bottom: 1.5rem; }
    .ob-svg { width: 11rem; height: 11rem; overflow: visible; }

    .ob-title {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        line-height: 1.2;
    }
    .ob-title-fil {
        margin-top: 0.2rem;
        font-size: 0.95rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.72);
    }
    .ob-body {
        margin-top: 1rem;
        font-size: 0.95rem;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.92);
    }
    .ob-body-fil {
        margin-top: 0.6rem;
        font-size: 0.875rem;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.72);
    }

    .ob-foot { display: flex; flex-direction: column; align-items: center; gap: 1rem; }

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
        height: 3rem;
        border: 0;
        border-radius: 0.75rem;
        background: #ffffff;
        color: #166534;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
    }
    .ob-next-fil { font-weight: 500; opacity: 0.7; }

    .ob-back {
        border: 0;
        background: transparent;
        color: rgba(255, 255, 255, 0.75);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        padding: 0.25rem;
    }

    /* ---------------- the drawings ----------------
       Stroke-only artwork so one fill rule cannot make a shape disappear
       against the green, and so the animations are cheap on an old phone:
       transforms and stroke-dashoffset only, nothing that forces layout. */
    .ob-disc { fill: rgba(255, 255, 255, 0.12); stroke: none; }
    .ob-ring, .ob-check, .ob-ground, .ob-stem, .ob-leaf, .ob-cloud, .ob-rain,
    .ob-sack, .ob-sack-top {
        fill: none;
        stroke: #ffffff;
        stroke-width: 5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .ob-bud, .ob-coin { fill: #ffffff; stroke: none; }
    .ob-leaf { fill: rgba(255, 255, 255, 0.18); }
    .ob-cloud { fill: rgba(255, 255, 255, 0.14); }
    .ob-sack { fill: rgba(255, 255, 255, 0.16); }

    .ob-ring { stroke-dasharray: 365; animation: ob-draw 900ms ease forwards; }
    .ob-check { stroke-dasharray: 80; animation: ob-draw 600ms 500ms ease backwards; }

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

    @keyframes ob-draw   { from { stroke-dashoffset: 365; } to { stroke-dashoffset: 0; } }
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
        .ob-ring, .ob-check, .ob-stem, .ob-leaf, .ob-bud,
        .ob-cloud, .ob-rain, .ob-coin { animation: none; }
        .ob-rain { opacity: 1; }
    }

    /* Tablet and up: the card gets room to breathe rather than filling a
       1600px monitor with one sentence. */
    @media (min-width: 640px) {
        .ob-sheet { padding: 2rem 2rem 2.5rem; }
        .ob-svg { width: 13rem; height: 13rem; }
        .ob-title { font-size: 1.75rem; }
    }
</style>
@endpush
