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
    /*
        Sept 2026: the fills were deepened from the 100 shades to the 200s,
        with the text a shade darker to keep the contrast. The 100s were so
        pale that on a phone, in daylight, a green pill and an amber one were
        hard to tell apart at a glance, which is the one job these have. The
        shape is unchanged: still a round pill with the small dot, so a
        status is never carried by colour alone.

        info and purple are kept here for anything that asks for them
        directly, but no status uses them any more: x-ui.status now works in
        green, amber, red and grey only.
    */
    $variants = [
        'default'  => 'bg-secondary text-secondary-foreground',
        'primary'  => 'bg-accent text-accent-foreground',
        // The grey of the four-colour set. Weighted to match the three
        // below, which 'default' (a very pale slate) is not.
        'neutral'  => 'bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-slate-100',
        'success'  => 'bg-green-200 text-green-900 dark:bg-green-900 dark:text-green-100',
        'warning'  => 'bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-100',
        'danger'   => 'bg-rose-200 text-rose-900 dark:bg-rose-900 dark:text-rose-100',
        'info'     => 'bg-sky-200 text-sky-900 dark:bg-sky-900 dark:text-sky-100',
        'purple'   => 'bg-purple-200 text-purple-900 dark:bg-purple-900 dark:text-purple-100',
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
