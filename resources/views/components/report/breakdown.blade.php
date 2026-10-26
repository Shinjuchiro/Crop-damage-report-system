{{--
    ONE BREAKDOWN BLOCK OF A MONTHLY REPORT (Oct 2026)

    <x-report.breakdown title="By Barangay"
                        :headers="['Barangay', 'Reports']"
                        :rows="$data['damage']['by_barangay']->map(fn ($r) => [$r->name, $r->total])"
                        empty="No data" />

    rows     a list of rows, each a list of already-formatted cells; the first
             cell is the label, the rest are numbers (right-aligned)
    headers  the column headings, one per cell
    note     small explanatory line under the title
    long     how many rows a two-column list may have before it stops being a
             table and becomes a multi-column list (default 8)

    Short lists are a normal table. A long two-column list (forty barangays,
    say) is laid out as several columns of label-and-number rows across the
    full width of its grid, so it is a quarter of the height instead of a
    long strip with empty space beside it. Lists with more than two columns
    stay tables: their figures need the column headings.

    Place inside <x-report.breakdown-grid>; a long list takes a row of its own,
    so put it last in the group.
--}}
@props([
    'title',
    'headers' => [],
    'rows'    => [],
    'empty'   => 'No data',
    'note'    => null,
    'long'    => 8,
])

@php
    $rows   = collect($rows)->values();
    $isLong = count($headers) <= 2 && $rows->count() > (int) $long;
@endphp

<div class="min-w-0" style="flex:1 1 {{ $isLong ? '100%' : '19rem' }}">
    <p class="mb-2 text-sm font-semibold text-foreground">
        {{ $title }}
        @if ($isLong && isset($headers[1]))
            <span class="font-normal text-muted-foreground">&middot; {{ $headers[1] }}</span>
        @endif
    </p>

    @if ($note)
        <p class="mb-2 text-xs text-muted-foreground">{{ $note }}</p>
    @endif

    @if ($rows->isEmpty())
        <div class="rounded-lg border border-border px-4 py-4 text-center text-sm text-muted-foreground">
            {{ $empty }}
        </div>
    @elseif ($isLong)
        <div style="column-width:16rem;column-gap:2rem">
            @foreach ($rows as $row)
                <div class="flex items-baseline justify-between gap-3 border-b border-border py-2 text-sm"
                     style="break-inside:avoid">
                    <span class="min-w-0 text-foreground">{{ $row[0] }}</span>
                    <span class="font-medium tabular-nums text-foreground">{{ $row[1] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    @else
        <x-ui.table>
            <x-slot:head>
                <tr>
                    @foreach ($headers as $i => $header)
                        <th class="{{ $i > 0 ? 'text-right' : '' }}">{{ $header }}</th>
                    @endforeach
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        <td class="{{ $i > 0 ? 'text-right tabular-nums' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</div>
