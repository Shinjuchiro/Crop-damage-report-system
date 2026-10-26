{{--
    THE FARMER'S NAME, ONCE, ABOVE THEIR RECORDS

        <x-record.group-head :farmer="$record->farmer" :count="3"
                             noun="damage report" :colspan="6"
                             :href="route('mao.farmers.show', $record->farmer)" />

    A full-width row that opens a farmer's block inside a record table. The
    rows under it are that farmer's, so none of them repeats the name
    (revision section 8): on a damage report or a validation the first
    column carries the reference instead, which is enough to name the row
    on its own.

    WHY A ROW AND NOT A SEPARATE TABLE PER FARMER

    Because the columns stay aligned. A table per farmer would restart the
    column widths for every member, so the same figure would sit in a
    different place down the page and the eye would have to find it again
    on every block.

    colspan has to match the table it is dropped into, so it is a prop
    rather than something clever: a wrong number here shows up immediately
    as a ragged row, which is better than a silent misalignment.

    props
      farmer   the Farmer whose block this opens
      count    how many of their records are in this block
      total    all they have, when the block shows only their latest few
      noun     singular noun for the count, pluralised here
      colspan  number of columns in the host table
      href     their profile, or null for no link
      toggle   true to make the row open and close that farmer's records
               (Oct 2026). The host must be inside an Alpine scope holding
               `open`, the id of the farmer whose block is open, and give
               each of that farmer's rows x-show="open === <groupId>".
      groupId  that farmer's id, the value `open` is compared with

    OPEN AND CLOSE

    On the three Crop Management Report tabs a farmer is one row and their
    records stay folded away until the row is opened, so a page holds a
    handful of farmers and does not need scrolling. One block is open at a
    time: opening another closes the first.
--}}

@props([
    'farmer',
    'count'   => 0,
    'total'   => null,
    'noun'    => 'record',
    'colspan' => 4,
    'href'    => null,
    'toggle'  => false,
    'groupId' => 0,
])

<tr class="bg-muted/60 {{ $toggle ? 'cursor-pointer select-none hover:bg-muted' : '' }}"
    @if ($toggle)
        @click="open = open === {{ (int) $groupId }} ? null : {{ (int) $groupId }}"
        @keydown.enter.prevent="open = open === {{ (int) $groupId }} ? null : {{ (int) $groupId }}"
        tabindex="0" role="button"
        :aria-expanded="(open === {{ (int) $groupId }}).toString()"
    @endif>
    <td colspan="{{ $colspan }}" class="px-3 py-2.5 sm:px-4">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">

            <div class="flex min-w-0 items-center gap-2">
                @if ($toggle)
                    <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                         :class="open === {{ (int) $groupId }} ? 'rotate-90' : ''"
                         fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                         stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9 6l6 6-6 6"/>
                    </svg>
                @endif
                <span class="text-sm font-bold text-card-foreground">
                    {{ $farmer?->full_name ?? 'Unknown member' }}
                </span>

                @if ($farmer?->association?->name)
                    <span class="text-xs text-muted-foreground">
                        &middot; {{ $farmer->association->name }}
                    </span>
                @endif

                @if ($farmer?->barangay?->name)
                    <span class="text-xs text-muted-foreground">
                        &middot; {{ $farmer->barangay->name }}
                    </span>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                    @if ($total && $total > $count)Latest {{ $count }} of {{ $total }} {{ \Illuminate\Support\Str::plural($noun, $total) }}@else{{ $count }} {{ \Illuminate\Support\Str::plural($noun, $count) }}@endif
                </span>

                @if ($href)
                    <a href="{{ $href }}" @if ($toggle) @click.stop @endif
                       class="text-xs font-semibold text-primary hover:underline">
                        View profile
                    </a>
                @endif
            </div>
        </div>
    </td>
</tr>
