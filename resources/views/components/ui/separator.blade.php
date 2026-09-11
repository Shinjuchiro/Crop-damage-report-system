{{-- <x-ui.separator /> or <x-ui.separator label="Farm information" /> --}}
@props(['label' => null])

@if ($label)
    <div {{ $attributes->class('flex items-center gap-3') }} role="separator">
        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ $label }}</span>
        <span class="h-px flex-1 bg-border"></span>
    </div>
@else
    <hr {{ $attributes->class('border-0 border-t border-border') }}>
@endif
