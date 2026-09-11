{{-- One row inside x-ui.dropdown. Give it an href to make it a link. --}}
@props([
    'href'    => null,
    'variant' => 'default',
])

@php
    $classes = 'flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors '
        . '[&_svg]:size-4 [&_svg]:shrink-0 '
        . ($variant === 'destructive'
            ? 'text-destructive hover:bg-destructive/10'
            : 'text-popover-foreground hover:bg-accent hover:text-accent-foreground');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>{{ $slot }}</button>
@endif
