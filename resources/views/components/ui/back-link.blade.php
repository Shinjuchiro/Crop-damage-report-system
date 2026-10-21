{{--
    A quiet "up one level" link.

        <x-ui.back-link :href="route('mao.crop-management.index')"
                        label="Crop Management Report" />

    Used at the top of the pages that now sit under Crop Management Report.
    Those pages are reached from a hub card rather than from the sidebar, so
    without this the only way back up is the browser's own Back button, and
    a page with no visible parent does not read as being inside anything.

    Deliberately understated: muted text, no border, no button shape. It is
    orientation, not an action, and it should not compete with whatever the
    page's real buttons are.
--}}

@props([
    'href',
    'label' => 'Back',
])

<a href="{{ $href }}"
   {{ $attributes->class(
        'mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground '
        . 'transition hover:text-foreground'
   ) }}>
    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M15 18l-6-6 6-6"/>
    </svg>
    {{ $label }}
</a>
