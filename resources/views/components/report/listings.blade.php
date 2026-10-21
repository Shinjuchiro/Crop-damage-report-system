{{--
    THE RECORDS BEHIND A MONTHLY REPORT

        <x-report.listings :records="$data['records'] ?? []" />

    Every card on a monthly report is a count. These are the rows behind
    them, named, so "Total Damage Reports: 24" can be answered with "which
    twenty-four?" without leaving the page: who registered, who planted
    what, who reported damage and what it cost them, which technician
    inspected it, who was allocated assistance and who signed for it.

    ONE COMPONENT, BOTH REPORTS

    The office's report and an association's own report show the same
    listings from the same array - MonthlyReportBuilder::records(), scoped
    by association for the second one. A second copy of this markup would
    be a second place for them to drift apart, which is the same reason
    MonthlyReportBuilder itself is shared rather than duplicated.

    CLOSED BY DEFAULT

    A busy month is hundreds of rows, and this page is opened far more
    often for the figures at the top than for the detail underneath. Nobody
    should have to scroll past a thousand rows to reach the Assistance
    summary. Each listing says how many records it holds before it is
    opened, so the count is readable either way.

    props
      records  the 'records' array from MonthlyReportBuilder::forPeriod()
--}}

@props(['records' => []])

@foreach ($records as $listing)
    <x-ui.card :padded="false" x-data="{ open: false }">
        <button type="button" @click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left sm:px-5">
            <span>
                <span class="block text-base font-bold text-card-foreground">{{ $listing['title'] }}</span>
                <span class="block text-sm text-muted-foreground">
                    {{ number_format(count($listing['rows'])) }}
                    {{ \Illuminate\Support\Str::plural('record', count($listing['rows'])) }}
                    in this period
                </span>
            </span>

            <svg class="h-5 w-5 shrink-0 text-muted-foreground transition-transform"
                 :class="open ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </button>

        <div x-show="open" x-cloak class="border-t border-border">
            @if (count($listing['rows']) === 0)
                <p class="px-4 py-6 text-center text-sm text-muted-foreground sm:px-5">
                    No records in this period.
                </p>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            @foreach ($listing['headings'] as $heading)
                                <th class="whitespace-nowrap">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </x-slot:head>

                    @foreach ($listing['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell === '' || $cell === null ? '-' : $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>
@endforeach
