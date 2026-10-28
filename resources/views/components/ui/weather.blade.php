{{--
    TODAY'S WEATHER OVER TANZA

        <x-ui.weather />

    The card from the reference design: the temperature and the place on the
    left, the condition and today's high and low on the right. It sits on all
    four dashboards, directly under the greeting.

    ONE PLACE, ON PURPOSE

    The coordinates are the municipality's, not the farmer's. This is a
    municipal system covering one town; a farm in Tres Cruces and one in
    Daang Amaya share their weather. Asking each phone for its GPS position
    would be a permission prompt, a privacy question and a different answer
    per farmer, for a forecast that would read the same.

    WHERE THE NUMBER COMES FROM

    OpenWeatherMap, which is the service the search engines are already
    showing: search "weather in Tanza" and the card that comes back says
    "Data from OpenWeatherMap" at the bottom. Reading the same service is
    how this card agrees with what anyone would get by checking for
    themselves, rather than being a degree or two off and inviting the
    question.

    THE KEY NEVER REACHES THE BROWSER

    This card does not call OpenWeatherMap. It calls this application's own
    /weather route, which calls OpenWeatherMap with the key from .env (see
    App\Services\Weather). Calling the service straight from here would be
    fewer moving parts and would put the API key in the page source for
    anyone to read.

    IT UPDATES WITHOUT A PAGE LOAD

    The reading is fetched when the dashboard opens, again every ten minutes
    while it is left open, and again whenever the tab is brought back to the
    front - so a dashboard left open since this morning is not still showing
    this morning's weather. The server caches for ten minutes, so polling
    costs a database-free route hit and nothing more.

    The page also renders before the request is made, so a slow or
    unreachable forecast can no longer hold up the dashboard's own render,
    which it could when this was fetched server-side.

    FAILURE IS QUIET, NOT INVISIBLE

    The route answers 204 No Content when there is nothing to show - no key
    set, no network, the service down - and the card shows a small "Weather
    unavailable right now" placeholder instead of disappearing (Oct 25: the
    old hide-completely behaviour made it look removed). A good earlier
    reading is kept on screen if one poll fails.
--}}

<div x-data="tanzaWeather()" {{ $attributes->class('mb-5') }}>

{{-- Shown whenever the lookup has finished and returned nothing (no key, no
     network, service down). The card used to vanish in that case, which read
     as "the weather card was removed". A quiet placeholder keeps it visible
     on every dashboard and says why there is no number. --}}
<div x-show="settled && ! reading" x-cloak
     class="flex items-center gap-4 rounded-2xl border border-dashed border-border bg-card px-4 py-4 shadow-sm sm:px-5">
    <span class="shrink-0 text-muted-foreground" aria-hidden="true">
        <svg class="h-11 w-11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 48 48">
            <path d="M14 34a8 8 0 010-16 11 11 0 0121-3 8 8 0 01-1 19z"/>
        </svg>
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-foreground">Weather unavailable right now</p>
        <p class="text-xs text-muted-foreground">Tanza, Cavite. It will refresh automatically.</p>
    </div>
</div>

{{-- On a narrow phone the condition and high/low block no longer fits beside
     the temperature and place, and used to be drawn over them. Below ~430px
     the row is allowed to wrap and that block drops under the text, lined up
     with it. Inline so it never waits on the compiled stylesheet. --}}
<style>
    .wx-card  { flex-wrap: wrap; }
    .wx-main  { flex: 1 1 9rem; min-width: 9rem; }
    .wx-right { flex: 0 0 auto; margin-left: auto; }
    @media (max-width: 430px) {
        .wx-right { flex: 1 1 100%; margin-left: 0; text-align: left !important; padding-left: 3.75rem; }
    }
</style>

<div x-show="reading" x-cloak
     class="wx-card flex items-center gap-4 rounded-2xl border border-border bg-card px-4 py-4 shadow-sm sm:px-5">

    {{-- The drawing. Stroke and fill, so it holds its shape at 44px, and the
         sun keeps its warm colour while the cloud stays grey: this is the
         one place in the system where a colour is describing the real world
         rather than a status. --}}
    <span class="shrink-0" aria-hidden="true">
        <svg class="h-11 w-11" fill="none" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 48 48">

            {{-- x-show on each group, not <template x-if>. Inside an
                 <svg> the HTML parser is in foreign-content mode, where a
                 <template> tag is read as an SVG element of that name
                 rather than as a real template, and the contents never
                 render. x-show sets display:none, which SVG honours. --}}
            <g x-show="reading && reading.icon === 'sun'" x-cloak>
                <circle cx="24" cy="24" r="9" class="fill-amber-300 stroke-amber-400" stroke-width="2"/>
                <g class="stroke-amber-400" stroke-width="2.6">
                    <path d="M24 5v5M24 38v5M5 24h5M38 24h5M10.8 10.8l3.6 3.6M33.6 33.6l3.6 3.6M37.2 10.8l-3.6 3.6M14.4 33.6l-3.6 3.6"/>
                </g>
            </g>

            <g x-show="reading && reading.icon === 'partly'" x-cloak>
                <circle cx="18" cy="17" r="7" class="fill-amber-300 stroke-amber-400" stroke-width="2"/>
                <g class="stroke-amber-400" stroke-width="2.4">
                    <path d="M18 3v4M4 17h4M8.4 7.4l2.8 2.8M27.6 7.4l-2.8 2.8"/>
                </g>
                <path d="M16 38a7 7 0 010-14 10 10 0 0119-3 7.5 7.5 0 01-1 17z"
                      class="fill-sky-200 stroke-sky-400" stroke-width="2"/>
            </g>

            <g x-show="reading && reading.icon === 'storm'" x-cloak>
                <path d="M14 33a8 8 0 010-16 11 11 0 0121-3 8 8 0 01-1 19z"
                      class="fill-slate-300 stroke-slate-400" stroke-width="2"/>
                <path d="M25 33l-5 8h6l-4 7" class="stroke-amber-400" stroke-width="2.6"/>
            </g>

            <g x-show="reading && reading.icon === 'rain'" x-cloak>
                <path d="M14 31a8 8 0 010-16 11 11 0 0121-3 8 8 0 01-1 19z"
                      class="fill-sky-200 stroke-sky-400" stroke-width="2"/>
                <g class="stroke-sky-500" stroke-width="2.6">
                    <path d="M17 36l-2 6M25 36l-2 6M33 36l-2 6"/>
                </g>
            </g>

            <g x-show="reading && reading.icon === 'cloud'" x-cloak>
                <path d="M14 34a8 8 0 010-16 11 11 0 0121-3 8 8 0 01-1 19z"
                      class="fill-slate-200 stroke-slate-400" stroke-width="2"/>
            </g>

        </svg>
    </span>

    <div class="wx-main min-w-0 flex-1">
        <p class="flex items-baseline gap-1">
            <span class="text-2xl font-bold tracking-tight text-foreground" x-text="reading?.temperature"></span>
            <span class="text-lg font-semibold text-foreground">&deg;C</span>
        </p>
        <p class="truncate text-sm text-muted-foreground">Tanza, Cavite</p>
        <p class="truncate text-[11px] text-muted-foreground/80">
            Source: OpenWeatherMap<span x-show="reading?.updated"> &middot; updated <span x-text="reading?.updated"></span></span>
        </p>
    </div>

    <div class="wx-right shrink-0 text-right">
        <p class="text-sm font-medium text-foreground" x-text="reading?.description"></p>
        <p class="text-xs text-muted-foreground">
            H: <span x-text="reading?.high"></span>&deg; &nbsp;
            L: <span x-text="reading?.low"></span>&deg;
        </p>
    </div>
</div>

</div>

{{-- @once: the card appears on one dashboard at a time, but the guard means
     a page that ever shows two of them still defines this once. --}}
@once
    @push('scripts')
    <script>
        function tanzaWeather() {
            return {
                reading: null,
                settled: false,

                /* This application's own endpoint, not OpenWeatherMap's.
                   The key stays on the server; see the note above. */
                endpoint: @json(route('weather')),

                init() {
                    this.load();

                    /* OpenWeatherMap updates on roughly a ten minute cycle
                       and the server caches for the same, so asking more
                       often would be the same number. */
                    setInterval(() => this.load(), 10 * 60 * 1000);

                    /* Coming back to a tab left open since this morning
                       should not show this morning's weather. */
                    document.addEventListener('visibilitychange', () => {
                        if (! document.hidden) this.load();
                    });
                },

                /* The lookup is over, whatever it found. The page holds its
                   content back until this has happened (see the loading veil
                   in layouts/app.blade.php), so the weather card is already
                   in place when the page appears instead of popping in a
                   moment later and pushing everything down. */
                finish() {
                    this.settled = true;
                    window.__fitsWeatherReady = true;
                    window.dispatchEvent(new Event('fits-weather-ready'));
                },

                async load() {
                    try {
                        const response = await fetch(this.endpoint, {
                            cache: 'no-store',
                            headers: { 'Accept': 'application/json' },
                        });

                        /* 204 is the honest answer when there is nothing to
                           show. Anything else unexpected is treated the
                           same way: leave whatever is on screen alone
                           rather than blanking a good reading because one
                           poll failed. */
                        if (response.status !== 200) { this.finish(); return; }

                        const data = await response.json();

                        if (data && typeof data.temperature === 'number') {
                            this.reading = data;
                        }
                        this.finish();
                    } catch (e) {
                        this.finish();
                        /* Offline, or the app is restarting. The card keeps
                           its last reading, or stays hidden if it never had
                           one. Nothing else on the page cares. */
                    }
                },
            };
        }
    </script>
    @endpush
@endonce
