{{--
    "Open full page" - the escape hatch out of a detail panel.

        <x-ui.open-full-page :href="route('mao.farmers.show', $farmer)" />

    Every list + detail page shows a summary in the right-hand panel and
    keeps the full record on a page of its own. Without this link the full
    page is unreachable from the panel, so anything the panel leaves out
    (long histories, photo grids, the audit trail) may as well not exist.

    One component rather than the anchor copied into each panel, so the
    wording and the styling cannot drift from page to page, and so a panel
    added later gets it right by default.

    Only for panels that actually HAVE a full page behind them. Several do
    not: Archive, Crops, Disasters, Associations, Disputes and Distribution
    Tracking have no show route, and the panel is the whole record there.
    Do not invent a destination for those.

    label overrides the wording for a panel whose full page is not the
    obvious next step ("Open the damage report", say).
--}}
@props([
    'href',
    'label' => 'Open full page',
])

<a href="{{ $href }}" {{ $attributes->class(
        'mt-3 inline-flex items-center gap-1 text-xs font-medium text-sky-700 underline '
        . 'hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300'
    ) }}>
    {{ $label }}
    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M14 4h6v6M20 4l-8.5 8.5M18 14v4.5A1.5 1.5 0 0116.5 20h-11A1.5 1.5 0 014 18.5v-11A1.5 1.5 0 015.5 6H10"/>
    </svg>
</a>
