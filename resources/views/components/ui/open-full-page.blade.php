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

{{--
    A BUTTON, NOT A LINK, AND A GREEN ONE

    This was a small underlined link. Underlined blue text is a web
    convention, and the office staff who use this screen all day may well
    read it as one, but it sat under a heading among plain labels and was
    easy to miss entirely. An outline button is unmistakably something to
    press, which is what it is.

    The outline version was still too quiet: a grey border on a white card,
    sitting between grey labels, in a modal where everything else is text.
    A pale green tint was tried next and was no better - a light fill on a
    white card is barely a fill at all, especially on a phone screen in
    daylight.

    So it is solid: the system's dark green under white text, darkening
    further on hover. Same treatment as every other real button here, which
    is the point - a tint reads as a label, and this is something to press.

    LAPTOP AND UP ONLY (hidden lg:inline-flex)

    The button exists because on a wide screen the panel sits beside the
    list and the full record is somewhere else. Below 1024px there is no
    panel beside anything: the list is a column of cards, tapping one
    opens the full page, and the panel above it is that same record. So on
    a phone or a tablet this offered to open the page you were already
    looking at.

    The component owns its own display class. Passing class="block" from
    the call site does NOT override it: the override and the component's
    own class both land in one class attribute, and which wins is decided
    by the order of the two rules in the compiled stylesheet, not by the
    element.
--}}
<a href="{{ $href }}" {{ $attributes->class(
        'mt-3 hidden shrink-0 items-center justify-center gap-1.5 rounded-full '
        . 'bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm '
        . 'h-9 transition-colors hover:bg-[#0d3d1b] lg:inline-flex'
    ) }}>
    {{ $label }}
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M14 4h6v6M20 4l-8.5 8.5M18 14v4.5A1.5 1.5 0 0116.5 20h-11A1.5 1.5 0 014 18.5v-11A1.5 1.5 0 015.5 6H10"/>
    </svg>
</a>
