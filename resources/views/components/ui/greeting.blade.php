{{--
    THE GREETING BANNER

        <x-ui.greeting :name="$farmer->first_name" role="Farmer"
                       :href="route('farmer.profile')" />

    The pale green band at the top of a dashboard in the reference design:
    a leaf in a circle, the time of day and the person's name, their role
    underneath, and a chevron if it goes somewhere.

    It replaces the page heading on dashboards, which is why every dashboard
    that uses it also sets @section('hideHeading', true). Two greetings, one
    in the bar and one in a banner, would just be the same sentence twice.

    The time of day is worked out on the server from the app's timezone, not
    in the browser: a farmer opening this at six in the morning should be
    told "Magandang umaga" whatever their phone clock says.
--}}
@props([
    'name'     => '',
    'role'     => '',
    'href'     => null,
    'subtitle' => null,
])

@php
    $hour = (int) now()->format('G');

    // English greeting with the Filipino one under it, the way the rest of
    // the app labels things.
    [$greetEn, $greetFil] = match (true) {
        $hour < 12 => ['Good morning',   'Magandang umaga'],
        $hour < 18 => ['Good afternoon', 'Magandang hapon'],
        default    => ['Good evening',   'Magandang gabi'],
    };

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
   {{ $attributes->class('mb-5 flex items-center gap-3 rounded-2xl bg-accent px-4 py-4 sm:px-5') }}>

    {{-- The leaf. Two strokes rather than a filled blob so it keeps its shape
         at 24px on a phone. --}}
    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-card/70"
          aria-hidden="true">
        <svg class="h-7 w-7 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M12 21v-7"/>
            <path d="M12 14c0-4 3-7 8-7 0 4-3 7-8 7z"/>
            <path d="M12 16c0-3.2-2.4-5.6-6.5-5.6C5.5 13.6 7.9 16 12 16z"/>
        </svg>
    </span>

    <span class="min-w-0 flex-1">
        <span class="block truncate text-lg font-bold leading-tight text-accent-foreground">
            {{ $greetEn }}, {{ $name }}!
        </span>
        <span class="block truncate text-sm text-accent-foreground/70">
            {{ $subtitle ?? $role }}
        </span>
        <span class="block truncate text-xs text-accent-foreground/60">
            {{ $greetFil }} po
        </span>
    </span>

    @if ($href)
        <svg class="h-5 w-5 shrink-0 text-accent-foreground/60" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
             aria-hidden="true">
            <path d="M9 6l6 6-6 6"/>
        </svg>
    @endif
</{{ $tag }}>
