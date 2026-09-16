{{--
    <x-ui.button>Save</x-ui.button>
    <x-ui.button variant="outline" size="sm">Cancel</x-ui.button>
    <x-ui.button variant="destructive" type="submit">Archive</x-ui.button>
    <x-ui.button href="{{ route('mao.dashboard') }}" variant="ghost">Back</x-ui.button>
    <x-ui.button href="{{ $viewUrl }}" variant="view" size="sm">View</x-ui.button>

    "view" is the standalone "flashcard" style used for every View / View
    Details action across the app (Sept 2026): a soft blue tinted
    background at ~50% opacity rather than a plain outline button, so the
    view action reads consistently everywhere a list links to a record's
    details.

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
        // The "flashcard" View / View Details style: a translucent blue
        // fill rather than a solid button, so it reads as a lighter-weight
        // action than Edit/Archive/Delete next to it.
        'view'        => 'bg-blue-500/50 text-blue-950 hover:bg-blue-500/60 '
                        . 'dark:bg-blue-500/40 dark:text-blue-50 dark:hover:bg-blue-500/50',
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

    $base = 'inline-flex shrink-0 items-center justify-center rounded-md font-medium '
        . 'transition-colors disabled:pointer-events-none disabled:opacity-50 '
        . '[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg]:size-4';

    $classes = $base . ' '
        . ($variants[$variant] ?? $variants['default']) . ' '
        . ($sizes[$icon ? 'icon' : $size] ?? $sizes['default']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>{{ $slot }}</button>
@endif
