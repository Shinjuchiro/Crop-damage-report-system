{{--
    <x-ui.button>Save</x-ui.button>
    <x-ui.button variant="outline" size="sm">Cancel</x-ui.button>
    <x-ui.button variant="destructive" type="submit">Archive</x-ui.button>
    <x-ui.button href="{{ route('mao.dashboard') }}" variant="ghost">Back</x-ui.button>

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
    ];

    $sizes = [
        'sm'      => 'h-8 gap-1.5 px-3 text-xs',
        'default' => 'h-10 gap-2 px-4 text-sm',
        // Farmers and technicians work on phones, outdoors, sometimes with
        // gloves. Anything they tap should use size="lg".
        'lg'      => 'h-12 gap-2 px-6 text-base',
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
