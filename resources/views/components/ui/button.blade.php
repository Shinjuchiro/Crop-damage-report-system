{{--
    <x-ui.button>Save</x-ui.button>
    <x-ui.button variant="outline" size="sm">Cancel</x-ui.button>
    <x-ui.button variant="destructive" type="submit">Archive</x-ui.button>
    <x-ui.button href="{{ route('mao.dashboard') }}" variant="ghost">Back</x-ui.button>
    <x-ui.button href="{{ $viewUrl }}" variant="view" size="sm">View</x-ui.button>

    "view" is the standalone style used for every View / View Details
    action across the app: a solid blue, so the view action reads
    consistently everywhere a list links to a record's details and is
    still clearly lighter in weight than the green primary button.

    Giving it an href renders a link that looks identical to the button.
    Everything else you pass through (type, name, value, @click, x-show,
    data-confirm attributes) lands on the element untouched.
--}}
@props([
    'variant' => 'default',
    'size'    => 'default',
    'href'    => null,
    'icon'    => false,
])

@php
    $variants = [
        'default'     => 'bg-primary text-primary-foreground shadow-sm hover:brightness-110',
        'secondary'   => 'bg-secondary text-secondary-foreground hover:bg-accent hover:text-accent-foreground',
        'outline'     => 'border border-input bg-card text-foreground hover:bg-accent hover:text-accent-foreground',
        'ghost'       => 'text-foreground hover:bg-accent hover:text-accent-foreground',
        'destructive' => 'bg-destructive text-destructive-foreground shadow-sm hover:brightness-110',
        'link'        => 'text-primary underline-offset-4 hover:underline',
        /* The View / View Details action. Solid and dark, not a tint.

           It used to be blue-500 at half opacity with near-black text.
           Over a white row that is about #94b8f7, and the text on it was
           whatever won between text-blue-950 and a dark: override, so on
           a device in dark mode it came out near-white on pale blue and
           was barely readable. A solid blue-700 under white text is 8.6:1,
           well past the 4.5:1 that normal text needs, and it reads the
           same on every device because nothing is left translucent. */
        'view'        => 'bg-blue-700 text-white shadow-sm hover:brightness-110',
    ];

    $sizes = [
        'sm'      => 'h-8 gap-1.5 px-3 text-xs',
        'default' => 'h-10 gap-2 px-4 text-sm',
        // Farmers and technicians work on phones, outdoors, sometimes with
        // gloves, so this stays a bigger tap target than "default" - just a
        // slimmer one than before (was h-12/px-6/text-base, which read as
        // oversized and heavy next to the rest of the interface).
        'lg'      => 'h-11 gap-2 px-5 text-sm',
        'icon'    => 'h-10 w-10',
    ];

    /* Pills, not rectangles. Every button in the reference design is fully
       rounded, and it is the single most recognisable thing about that
       style. rounded-full on a tall button is a capsule and on the square
       icon button it is a circle, which is what we want in both places. */
    $base = 'inline-flex shrink-0 items-center justify-center rounded-full font-medium '
        . 'transition-colors disabled:pointer-events-none disabled:opacity-50 '
        . '[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg]:size-4';

    $classes = $base . ' '
        . ($variants[$variant] ?? $variants['default']) . ' '
        . ($sizes[$icon ? 'icon' : $size] ?? $sizes['default']);

    /* The View colour is also written inline. bg-blue-700 only exists in the
       stylesheet once Vite has been rebuilt after it was introduced; on a
       machine with an older public/build the class is simply missing and
       the button came out as white text on a white row, so every View
       button looked empty. Inline, it cannot go missing. */
    $inline = $variant === 'view' ? 'background-color:#1d4ed8;color:#ffffff;' : null;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(array_filter(['style' => $inline]))->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(array_filter(['type' => 'button', 'style' => $inline]))->class($classes) }}>{{ $slot }}</button>
@endif
