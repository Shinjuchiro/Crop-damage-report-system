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
--}}

@props([
    'farmer',
    'count'   => 0,
    'total'   => null,
    'noun'    => 'record',
    'colspan' => 4,
    'href'    => null,
])

<tr class="bg-muted/60">
    <td colspan="{{ $colspan }}" class="px-3 py-2.5 sm:px-4">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">

            <div class="min-w-0">
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
                    <a href="{{ $href }}" class="text-xs font-semibold text-primary hover:underline">
                        View profile
                    </a>
                @endif
            </div>
        </div>
    </td>
</tr>
