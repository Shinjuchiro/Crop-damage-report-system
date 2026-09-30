{{--
    TODAY'S WEATHER OVER TANZA

        <x-ui.weather />

    The card from the reference design: the temperature and the place on the
    left, the condition and today's high and low on the right.

    It asks the service directly rather than being handed data by a
    controller. That is unusual for this codebase and deliberate here: it
    appears on four dashboards belonging to four different controllers, and
    threading the same call through all four would be four chances for one
    of them to forget. The lookup is cached for half an hour, so a page load
    costs nothing.

    App\Services\Weather returns null when the forecast cannot be fetched,
    and this renders nothing at all in that case. No error, no empty card,
    no "unavailable" placeholder taking up a screen: the dashboard's job is
    damage reports, and the weather is a courtesy.
--}}
@php
    $weather = app(\App\Services\Weather::class)->today();
@endphp

@if ($weather)
    <div {{ $attributes->class('mb-5 flex items-center gap-4 rounded-2xl border border-border bg-card px-4 py-4 shadow-sm sm:px-5') }}>

        {{-- The drawing. Stroke only, so it holds its shape at 40px, and the
             sun keeps its warm colour while the cloud stays grey: this is the
             one place in the system where a colour is describing the real
             world rather than a status. --}}
        <span class="shrink-0" aria-hidden="true">
            <svg class="h-11 w-11" fill="none" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 48 48">
                @if ($weather['icon'] === 'sun')
                    <circle cx="24" cy="24" r="9" class="fill-amber-300 stroke-amber-400" stroke-width="2"/>
                    <g class="stroke-amber-400" stroke-width="2.6">
                        <path d="M24 5v5M24 38v5M5 24h5M38 24h5M10.8 10.8l3.6 3.6M33.6 33.6l3.6 3.6M37.2 10.8l-3.6 3.6M14.4 33.6l-3.6 3.6"/>
                    </g>
                @elseif ($weather['icon'] === 'partly')
                    <circle cx="18" cy="17" r="7" class="fill-amber-300 stroke-amber-400" stroke-width="2"/>
                    <g class="stroke-amber-400" stroke-width="2.4">
                        <path d="M18 3v4M4 17h4M8.4 7.4l2.8 2.8M27.6 7.4l-2.8 2.8"/>
                    </g>
                    <path d="M16 38a7 7 0 010-14 10 10 0 0119-3 7.5 7.5 0 01-1 17z"
                          class="fill-sky-200 stroke-sky-400" stroke-width="2"/>
                @elseif ($weather['icon'] === 'storm')
                    <path d="M14 33a8 8 0 010-16 11 11 0 0121-3 8 8 0 01-1 19z"
                          class="fill-slate-300 stroke-slate-400" stroke-width="2"/>
                    <path d="M25 33l-5 8h6l-4 7" class="stroke-amber-400" stroke-width="2.6"/>
                @elseif ($weather['icon'] === 'rain')
                    <path d="M14 31a8 8 0 010-16 11 11 0 0121-3 8 8 0 01-1 19z"
                          class="fill-sky-200 stroke-sky-400" stroke-width="2"/>
                    <g class="stroke-sky-500" stroke-width="2.6">
                        <path d="M17 36l-2 6M25 36l-2 6M33 36l-2 6"/>
                    </g>
                @else
                    <path d="M14 34a8 8 0 010-16 11 11 0 0121-3 8 8 0 01-1 19z"
                          class="fill-slate-200 stroke-slate-400" stroke-width="2"/>
                @endif
            </svg>
        </span>

        <div class="min-w-0 flex-1">
            <p class="flex items-baseline gap-1">
                <span class="text-2xl font-bold tracking-tight text-foreground">{{ $weather['temperature'] }}</span>
                <span class="text-lg font-semibold text-foreground">&deg;C</span>
            </p>
            <p class="truncate text-sm text-muted-foreground">Tanza, Cavite</p>
        </div>

        <div class="shrink-0 text-right">
            <p class="text-sm font-medium text-foreground">{{ $weather['description'] }}</p>
            <p class="text-xs text-muted-foreground">
                H: {{ $weather['high'] }}&deg; &nbsp; L: {{ $weather['low'] }}&deg;
            </p>
        </div>
    </div>
@endif
