{{--
    A dashboard figure. Replaces the older x-stat-card and works in dark mode.

    <x-ui.stat label="Verified Reports" :value="$verified" hint="This month"
               :href="route('mao.damage-reports.index', ['status' => 'verified'])" />

    Pass an SVG path in :icon to get the icon tile beside the figure:

    <x-ui.stat label="Pending Validation" :value="21" tone="warning"
               icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2" />

    Every value passed in must come from a query. No figure on any dashboard
    in this system is written by hand.

    TWO SIZES, ONE CARD (Sept 2026)

    Below 640px the card is the compact one: a 32px round icon with the label
    beside it, and the number on its own line under them. Four of these fit
    two across on a phone, so a farmer sees all four figures at once instead
    of scrolling past four full-width tiles.

    From 640px up the card is the original: a 44px rounded square on the
    left, label, number and hint stacked in a column beside it. A laptop has
    the room for it, and the office reads these all day.

    The two layouts are written out separately and one of them is hidden,
    rather than being one layout that changes shape. They do not just differ
    in size: the compact one puts the number below the icon, the wide one
    puts it beside the icon, and CSS cannot move an element from one column
    into another. Whichever is hidden is display:none, so it is not read out
    by a screen reader and the figure is announced once.

    Both halves take their label, value, suffix, hint and tone from the same
    props, so a figure can never differ between phone and laptop. If you
    change one half, change the other.
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

    // The icon tile borrows the same tone as the number so the card reads
    // as one thing rather than a number with an unrelated picture beside it.
    // 'warning' is deliberately muted (pale amber-50 behind a dark amber-800
    // icon) rather than a saturated amber - a bright amber-on-white tile
    // next to the other three quiet stat cards was too vivid to sit with
    // comfortably on a dashboard someone looks at all day.
    //
    // 'default' used to be grey on grey, which on a dashboard of five cards
    // meant three colourless ones between the amber and the green. It is a
    // soft blue now: a plain count is neither good news nor bad, so it gets
    // a colour that says neither, and no icon on any page is left plain.
    $iconTones = [
        'default' => 'bg-sky-100 text-sky-700',
        'primary' => 'bg-accent text-primary',
        'warning' => 'bg-amber-50 text-amber-800',
        'danger'  => 'bg-rose-100 text-rose-700',
    ];

    $tag = $href ? 'a' : 'div';

    $iconTone  = $iconTones[$tone] ?? $iconTones['default'];
    $valueTone = $tones[$tone] ?? $tones['default'];

    // p-3 on a phone, the original p-5 from sm up.
    $classes = 'block rounded-xl border border-border bg-card p-3 shadow-sm sm:p-5'
        . ($href ? ' card-hover hover:border-primary/40' : '');
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class($classes) }}>

    {{-- ---------------------------------------------------------------
         PHONE (under 640px): compact
    ---------------------------------------------------------------- --}}
    <div class="sm:hidden">

        {{-- Icon and label share the top line, so the number below gets the
             full width of the card instead of being squeezed beside a big
             square. min-w-0 so a long label wraps inside the card rather
             than widening it, which is what pushed these pages sideways. --}}
        <div class="flex items-center gap-2">
            @if ($icon)
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full
                             {{ $iconTone }}" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="{{ $icon }}"/>
                    </svg>
                </span>
            @endif

            <p class="min-w-0 text-xs font-medium leading-tight text-muted-foreground">{{ $label }}</p>
        </div>

        {{-- The number and the hint start where the label starts, not at the
             card's edge. The icon is 32px and the gap 8px, so they are
             indented by 40px to sit under the first letter of the label
             rather than under the icon. Without it every figure hung out to
             the left of its own label, which is what made a row of these
             look crooked. No icon, no indent. --}}
        <div class="{{ $icon ? 'pl-10' : '' }}">
            <p class="mt-2 flex items-baseline gap-1">
                <span class="text-xl font-bold tracking-tight {{ $valueTone }}">{{ $value }}</span>
                @if ($suffix)
                    <span class="text-xs font-medium text-muted-foreground">{{ $suffix }}</span>
                @endif
            </p>

            @if ($hint)
                <p class="mt-0.5 text-[11px] leading-tight text-muted-foreground">{{ $hint }}</p>
            @endif
        </div>
    </div>

    {{-- ---------------------------------------------------------------
         TABLET AND UP (640px and over): the original card
    ---------------------------------------------------------------- --}}
    <div class="hidden sm:flex sm:items-start sm:gap-3">

        @if ($icon)
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                         {{ $iconTone }}" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="{{ $icon }}"/>
                </svg>
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-muted-foreground">{{ $label }}</p>

            <p class="mt-1 flex items-baseline gap-1.5">
                <span class="text-3xl font-bold tracking-tight {{ $valueTone }}">{{ $value }}</span>
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
