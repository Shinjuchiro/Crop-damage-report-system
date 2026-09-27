{{--
    PLANTED AGAINST DAMAGED

    Built by App\Services\PlantingComparison. Shown on the Validation
    briefing, before the technician starts an inspection, and again on the
    Inspection History detail page as part of the record.

    Read this as a briefing, not a verdict. Proposal section 62 is
    deliberate that the cross-check is a signal only: a crop with no
    planting record is still fully available to inspect, and nothing here
    approves or rejects anything. The wording throughout is "worth checking",
    never "invalid".

    Expects:
        $comparison   the array from PlantingComparison::build()
--}}

@php
    $ha = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . ' ha';
@endphp

<div class="space-y-3">

    {{-- The one line summary, so a technician who reads nothing else still
         knows whether anything needs measuring. --}}
    @if ($comparison['warning_count'] > 0)
        <div class="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3
                    text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 9v4.5M12 17h.01M12 3.5L2.8 19.2A1 1 0 003.7 20.7h16.6a1 1 0 00.9-1.5L12 3.5z"/>
            </svg>
            <p>
                <span class="font-semibold">{{ $comparison['warning_count'] }}</span>
                {{ $comparison['warning_count'] === 1 ? 'thing is' : 'things are' }} worth checking on site.
                They are marked below. None of them stops you inspecting.
            </p>
        </div>
    @else
        <div class="flex items-start gap-2.5 rounded-lg border border-green-200 bg-green-50 px-3.5 py-3
                    text-sm text-green-900 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 6L9 17l-5-5"/>
            </svg>
            <p>What the farmer reported matches what they recorded planting.</p>
        </div>
    @endif

    {{-- One block per crop. Planted on the left, damaged on the right,
         with the figure that matters (the area) given the most weight on
         each side so the eye lands on the comparison first. --}}
    @foreach ($comparison['rows'] as $row)
        <div class="overflow-hidden rounded-lg border {{ $row['warnings'] ? 'border-amber-300 dark:border-amber-800' : 'border-border' }}">

            <div class="flex items-center justify-between gap-3 border-b border-border bg-muted/50 px-3.5 py-2">
                <p class="truncate text-sm font-semibold text-foreground">{{ $row['label'] }}</p>

                @if ($row['damage_percent'] !== null)
                    <span class="shrink-0 text-xs text-muted-foreground">
                        farmer says <span class="font-semibold text-foreground">{{ round($row['damage_percent']) }}%</span> damaged
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-2 divide-x divide-border">

                {{-- PLANTED --}}
                <div class="min-w-0 px-3.5 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Recorded planted</p>

                    @if ($row['matched'])
                        <p class="mt-1 text-lg font-bold leading-none text-foreground">{{ $ha($row['planted_area']) }}</p>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            Planted {{ $row['planted_date']?->format('M d, Y') ?? 'date not set' }}
                        </p>
                        @if ($row['recorded_on'])
                            <p class="text-xs text-muted-foreground">
                                Filed {{ $row['recorded_on']->format('M d, Y') }}
                            </p>
                        @endif
                    @else
                        <p class="mt-1 text-lg font-bold leading-none text-muted-foreground">Nothing on file</p>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            No planting record for this crop before the report date.
                        </p>
                    @endif
                </div>

                {{-- DAMAGED --}}
                <div class="min-w-0 px-3.5 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Reported damaged</p>

                    <p class="mt-1 text-lg font-bold leading-none text-foreground">
                        {{ $ha($row['damaged_area']) ?? '-' }}
                    </p>
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        Planted {{ $row['damaged_date']?->format('M d, Y') ?? 'date not given' }}
                    </p>
                    <p class="text-xs text-muted-foreground">as written on the report</p>
                </div>
            </div>

            @foreach ($row['warnings'] as $warning)
                <p class="flex items-start gap-2 border-t border-border px-3.5 py-2.5 text-xs
                          {{ $warning['level'] === 'alert'
                                ? 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                                : 'text-muted-foreground' }}">
                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        @if ($warning['level'] === 'alert')
                            <path d="M12 9v4.5M12 17h.01M12 3.5L2.8 19.2A1 1 0 003.7 20.7h16.6a1 1 0 00.9-1.5L12 3.5z"/>
                        @else
                            <path d="M12 22a10 10 0 100-20 10 10 0 000 20zM12 11v5.5M12 7.5h.01"/>
                        @endif
                    </svg>
                    {{ $warning['text'] }}
                </p>
            @endforeach
        </div>
    @endforeach

    {{-- Totals, but only when there is more than one crop to add up --}}
    @if (count($comparison['rows']) > 1)
        <div class="grid grid-cols-2 divide-x divide-border rounded-lg border border-border bg-muted/40">
            <div class="px-3.5 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Total recorded planted</p>
                <p class="mt-0.5 text-base font-bold text-foreground">{{ $ha($comparison['planted_total']) }}</p>
            </div>
            <div class="px-3.5 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Total reported damaged</p>
                <p class="mt-0.5 text-base font-bold text-foreground">{{ $ha($comparison['damaged_total']) }}</p>
            </div>
        </div>
    @endif
</div>
