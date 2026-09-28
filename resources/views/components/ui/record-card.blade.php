{{--
    ONE TABLE ROW, AS A CARD (phone widths)

        <x-ui.record-card :href="$viewUrl($report->id)"
                          eyebrow="DR-0004"
                          title="Juan Dela Cruz"
                          subtitle="Tres Cruces &middot; Tanza Farmers Association"
                          :status="$report->status"
                          :fields="[
                              'Disaster'     => 'Typhoon',
                              'Reported'     => 'Sep 18, 2026',
                              'Damaged area' => '2.5 ha',
                              'Inspection'   => 'Validated',
                          ]" />

    WHY THIS EXISTS

    Every MAO list page puts its table inside overflow-x-auto. On a laptop
    that is fine. On a phone it means the table keeps its full width and the
    card scrolls sideways inside itself, so the office sees the first two or
    three columns and has to drag to reach the rest. Measured on the live
    site at 375px: the Farmers Directory showed 3 of 7 columns, and
    Distribution Tracking hid 684px of a 1001px table. The Action column,
    which holds the only button on the row, was off-screen on every one of
    them.

    The Farmer, Technician and Association screens never had this problem,
    because they render a card list below sm and the table from sm up. This
    component is that same idea, written once: fifteen MAO pages needed it,
    and fifteen hand-written copies would drift apart the way the technician
    lists already did once.

    PROPS

        href      where the whole card goes. The card is one big link, so
                  the tap target is the card, not a small button in it.
        eyebrow   the reference line above the title (DR-0004, FARM-001).
        title     the main line. Usually the person the row is about.
        subtitle  muted line under the title.
        status    passed straight to x-ui.status, so a status looks the
                  same here as in the table and everywhere else.
        badge     free-form pill next to the status, for a page whose
                  second state is not in the x-ui.status vocabulary.
        fields    label => value. Rendered two per row, labels small and
                  muted above the value, so nothing is identified by
                  position alone the way a bare table row is.
        action    the wording on the bottom bar. Defaults to View Details.

    Anything in the default slot is rendered after the fields, for a page
    that needs something the grid cannot express.
--}}
@props([
    'href',
    'eyebrow'  => null,
    'title'    => null,
    'subtitle' => null,
    'status'   => null,
    'badge'    => null,
    'fields'   => [],
    'action'   => 'View Details',
])

<a href="{{ $href }}" {{ $attributes->class(
        'block rounded-xl border border-border bg-card p-3.5 shadow-sm transition active:bg-muted'
    ) }}>

    {{-- Reference and state --}}
    <div class="flex items-start justify-between gap-2">
        @if ($eyebrow)
            <span class="rounded-md bg-muted px-2 py-0.5 text-xs font-semibold tracking-wide text-foreground">
                {{ $eyebrow }}
            </span>
        @else
            <span></span>
        @endif

        <span class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
            @if ($badge)
                {{ $badge }}
            @endif
            @if ($status)
                <x-ui.status :value="$status" />
            @endif
        </span>
    </div>

    {{-- Who or what the row is about --}}
    @if ($title)
        <p class="mt-2 truncate text-base font-bold leading-tight text-foreground">{{ $title }}</p>
    @endif
    @if ($subtitle)
        <p class="truncate text-xs text-muted-foreground">{{ $subtitle }}</p>
    @endif

    {{-- The figures. min-w-0 on every cell so a long association name wraps
         inside its own column instead of widening the card, which is the
         whole fault this component exists to fix. --}}
    @if (count($fields))
        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-border pt-3">
            @foreach ($fields as $label => $value)
                <div class="min-w-0">
                    <dt class="text-[11px] leading-tight text-muted-foreground">{{ $label }}</dt>
                    <dd class="text-sm font-semibold leading-tight text-foreground">
                        {{ $value === null || $value === '' ? '-' : $value }}
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if (trim($slot ?? '') !== '')
        <div class="mt-3 text-sm">{{ $slot }}</div>
    @endif

    {{-- The action. 44px tall so it is a thumb-sized target; the whole card
         is the link anyway, this just says where it goes. --}}
    <span class="mt-3 flex h-11 items-center gap-2 rounded-lg bg-accent px-3
                 text-sm font-semibold text-accent-foreground">
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 12h13M13 6l6 6-6 6"/>
        </svg>
        {{ $action }}
    </span>
</a>
