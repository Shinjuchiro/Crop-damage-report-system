{{--
    A dashboard figure. Replaces the older x-stat-card and works in dark mode.

    <x-ui.stat label="Verified Reports" :value="$verified" hint="This month"
               :href="route('mao.damage-reports.index', ['status' => 'verified'])" />

    Pass an SVG path in :icon to get the tile with the icon square on the
    left, the way the technician dashboard mockup shows it:

    <x-ui.stat label="Pending Validation" :value="21" tone="warning"
               icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2" />

    Every value passed in must come from a query. No figure on any dashboard
    in this system is written by hand.
--}}
@props([
    'label'  => '',
    'value'  => '0',
    'suffix' => null,
    'hint'   => null,
    'href'   => null,
    'tone'   => 'default',
    'icon'   => null,
])

@php
    $tones = [
        'default' => 'text-foreground',
        'primary' => 'text-primary',
        'warning' => 'text-amber-800',
        'danger'  => 'text-destructive',
    ];

    // The icon square borrows the same tone as the number so the tile reads
    // as one thing rather than a number with an unrelated picture beside it.
    // 'warning' is deliberately muted (pale amber-50 behind a dark amber-800
    // icon) rather than a saturated amber - a bright amber-on-white tile
    // next to the other three quiet stat cards was too vivid to sit with
    // comfortably on a dashboard someone looks at all day.
    $iconTones = [
        'default' => 'bg-muted text-muted-foreground',
        'primary' => 'bg-accent text-primary',
        'warning' => 'bg-amber-50 text-amber-800',
        'danger'  => 'bg-rose-100 text-rose-700',
    ];

    $tag = $href ? 'a' : 'div';

    $classes = 'block rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5'
        . ($href ? ' card-hover hover:border-primary/40' : '');
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class($classes) }}>

    <div class="flex items-start gap-3">

        @if ($icon)
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                         {{ $iconTones[$tone] ?? $iconTones['default'] }}" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="{{ $icon }}"/>
                </svg>
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-muted-foreground">{{ $label }}</p>

            <p class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-bold tracking-tight sm:text-3xl {{ $tones[$tone] ?? $tones['default'] }}">{{ $value }}</span>
                @if ($suffix)
                    <span class="text-sm font-medium text-muted-foreground">{{ $suffix }}</span>
                @endif
            </p>

            @if ($hint)
                <p class="mt-1 text-xs text-muted-foreground">{{ $hint }}</p>
            @endif
        </div>
    </div>
</{{ $tag }}>
