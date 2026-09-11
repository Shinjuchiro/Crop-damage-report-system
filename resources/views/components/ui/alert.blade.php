{{--
    <x-ui.alert variant="success" title="Saved">Damage report submitted.</x-ui.alert>
    <x-ui.alert variant="destructive">Something went wrong.</x-ui.alert>
--}}
@props([
    'variant' => 'info',
    'title'   => null,
])

@php
    $variants = [
        'info'        => ['bg-sky-50 text-sky-900 border-sky-200 dark:bg-sky-950/50 dark:text-sky-200 dark:border-sky-900', 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 16v-5m0-3h.01'],
        'success'     => ['bg-green-50 text-green-900 border-green-200 dark:bg-green-950/50 dark:text-green-200 dark:border-green-900', 'M20 6L9 17l-5-5'],
        'warning'     => ['bg-amber-50 text-amber-900 border-amber-200 dark:bg-amber-950/50 dark:text-amber-200 dark:border-amber-900', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z'],
        'destructive' => ['bg-rose-50 text-rose-900 border-rose-200 dark:bg-rose-950/50 dark:text-rose-200 dark:border-rose-900', 'M12 8v5m0 3h.01M12 22a10 10 0 100-20 10 10 0 000 20z'],
    ];

    [$classes, $icon] = $variants[$variant] ?? $variants['info'];
@endphp

<div role="status"
     {{ $attributes->class('flex items-start gap-3 rounded-xl border px-4 py-3 text-sm ' . $classes) }}>

    <svg class="mt-0.5 h-4.5 w-4.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <path d="{{ $icon }}"/>
    </svg>

    <div class="min-w-0 leading-relaxed">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
