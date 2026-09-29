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
    // meant three colourless ones between the amber and the green. It is
    // the office's own pale green now (--accent behind --primary), not a
    // borrowed blue: the theme is a green one, and a plain count should
    // look like it belongs to it rather than to a different system.
    //
    // 'primary' goes the other way, a solid green circle with a white
    // glyph, so the figures that matter (verified, released) still stand
    // out from the ordinary counts beside them.
    $iconTones = [
        'default' => 'bg-accent text-primary',
        'primary' => 'bg-primary text-primary-foreground',
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

        {{-- Bento tile: one column, one left edge.

             The icon has a line of its own above the label, and the label,
             the figure and the hint all start where the icon starts. The
             two layouts before this both left something out of line: icon
             and label on one row put the figure under the icon but away
             from its own label, and indenting the figure to meet the label
             left it out of line with the icon. Stacked, there is only one
             edge to be out of line with. --}}
        @if ($icon)
            <span class="mb-2 flex h-9 w-9 items-center justify-center rounded-full
                         {{ $iconTone }}" aria-hidden="true">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="{{ $icon }}"/>
                </svg>
            </span>
        @endif

        {{-- min-w-0 so a long label wraps inside the card rather than
             widening it, which is what pushed these pages sideways. --}}
        <p class="min-w-0 text-xs font-medium leading-tight text-muted-foreground">{{ $label }}</p>

        <p class="mt-1 flex items-baseline gap-1">
            <span class="text-2xl font-bold tracking-tight {{ $valueTone }}">{{ $value }}</span>
            @if ($suffix)
                <span class="text-xs font-medium text-muted-foreground">{{ $suffix }}</span>
            @endif
        </p>

        @if ($hint)
            <p class="mt-0.5 text-[11px] leading-tight text-muted-foreground">{{ $hint }}</p>
        @endif
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
