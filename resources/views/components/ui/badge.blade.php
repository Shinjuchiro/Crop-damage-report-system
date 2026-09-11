{{--
    Status pills. <x-ui.badge variant="success">Verified</x-ui.badge>

    Report and account statuses should go through x-ui.status instead, which
    picks the variant for you so the same status never looks different on two
    pages.
--}}
@props([
    'variant' => 'default',
    'dot'     => false,
])

@php
    $variants = [
        'default'  => 'bg-secondary text-secondary-foreground',
        'primary'  => 'bg-accent text-accent-foreground',
        'success'  => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'warning'  => 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
        'danger'   => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
        'info'     => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'purple'   => 'bg-purple-100 text-purple-900 dark:bg-purple-950 dark:text-purple-300',
        'outline'  => 'border border-border text-muted-foreground',
    ];
@endphp

<span {{ $attributes->class(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap '
        . ($variants[$variant] ?? $variants['default'])
    ) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
