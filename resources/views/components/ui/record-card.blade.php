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
        'block rounded-xl border border-border bg-card p-4 shadow-sm transition active:bg-muted'
    ) }}>

    {{-- Reference and state --}}
    <div class="flex items-start justify-between gap-2">
        @if ($eyebrow)
            <span class="rounded-md bg-muted px-2.5 py-1 text-sm font-semibold tracking-wide text-foreground">
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
        <p class="mt-2.5 truncate text-lg font-bold leading-tight text-foreground">{{ $title }}</p>
    @endif
    @if ($subtitle)
        <p class="mt-0.5 truncate text-sm text-muted-foreground">{{ $subtitle }}</p>
    @endif

    {{-- The figures.

         Two to a row, except that a value too long for half a card takes a
         row of its own and runs the full width. A full association name is
         about sixty characters: in half a card that wrapped to four or five
         lines, and since the figure beside it was two words, it left a hole
         the height of the name next to it. Given the whole width the same
         name takes two lines and nothing sits empty.

         Three short figures go on one line rather than two and a stray: a
         planting record is crop, date planted and area, which is one fact
         in three parts, and splitting it left the area sitting alone under
         a half-empty row.

         A rule between rows and one between columns, so a label belongs to
         the value under it and to nothing else. min-w-0 on every cell so a
         long word wraps inside its own column instead of widening the card,
         which is the fault this component exists to fix. --}}
    @php
        /*
            Short figures are gathered into a run and the run is then broken
            into rows: an odd run leads with a row of three so nothing is
            left over on its own, an even one is all pairs. A long value
            interrupts the run and takes a row to itself.
        */
        $fieldRows  = [];
        $shortRun   = [];

        $flushRun = function () use (&$fieldRows, &$shortRun) {
            $n = count($shortRun);

            if ($n === 0) {
                return;
            }

            if ($n % 2 === 1 && $n >= 3) {
                $fieldRows[] = array_splice($shortRun, 0, 3);
            }

            while (count($shortRun) > 0) {
                $fieldRows[] = array_splice($shortRun, 0, min(2, count($shortRun)));
            }

            $shortRun = [];
        };

        foreach ($fields as $fieldLabel => $fieldValue) {
            $text = trim((string) ($fieldValue === null || $fieldValue === '' ? '-' : $fieldValue));
            $cell = [$fieldLabel, $text];

            // 22 characters is about what half of a 360px card holds on
            // one line now the card reads a size larger. Past that the
            // value is going to wrap, so it takes a row of its own.
            if (mb_strlen($text) > 22) {
                $flushRun();
                $fieldRows[] = [$cell];
            } else {
                $shortRun[] = $cell;
            }
        }

        $flushRun();
    @endphp

    @if (count($fieldRows))
        <dl class="mt-3 divide-y divide-border border-t border-border">
            @foreach ($fieldRows as $fieldRow)
                {{-- Three to a row is tight on a 360px phone, so that row
                     closes up its gaps to buy each column a few more px. --}}
                <div class="flex py-3.5 {{ count($fieldRow) === 3 ? 'gap-2' : 'gap-3' }}">
                    @foreach ($fieldRow as $i => [$cellLabel, $cellValue])
                        <div class="min-w-0 flex-1
                                    {{ $i > 0 ? 'border-l border-border ' . (count($fieldRow) === 3 ? 'pl-2' : 'pl-3') : '' }}">
                            <dt class="text-xs leading-tight text-muted-foreground">{{ $cellLabel }}</dt>
                            <dd class="mt-0.5 text-base font-semibold leading-tight text-foreground">{{ $cellValue }}</dd>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </dl>
    @endif

    @if (trim($slot ?? '') !== '')
        <div class="mt-3 text-sm">{{ $slot }}</div>
    @endif

    {{-- The action. 44px tall so it is a thumb-sized target; the whole card
         is the link anyway, this just says where it goes. --}}
    <span class="mt-3.5 flex h-12 items-center gap-2 rounded-lg bg-accent px-3.5
                 text-base font-semibold text-accent-foreground">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 12h13M13 6l6 6-6 6"/>
        </svg>
        {{ $action }}
    </span>
</a>
