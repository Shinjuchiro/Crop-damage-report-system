{{--
    HISTORY, for one member.

        <x-member.history :farmer="$farmer" />

    Everything that has happened to this member, in one column, newest
    first: the registration, every planting record, every damage report,
    every inspection a technician submitted, and every piece of assistance
    handed over.

    WHY A TIMELINE AND NOT A SIXTH TABLE

    The other tabs each answer "what are this member's X". This one answers
    the question those cannot: what order did it happen in. A farmer plants
    in June, reports flooding in July, is inspected in August and receives
    seed in September - four rows in four different tables, and the story
    only appears when they are put on one line.

    It is assembled from relations already loaded for the other tabs, so it
    costs no extra queries. Nothing here is a new record: every row points
    at something the member or the office already filed.

    Expects $farmer with plantingRecords, damageReports.validation.technician
    and assistanceDistributions.allocation loaded.
--}}

@props(['farmer'])

@php
    $events = collect();

    // Registration. The one event with no record of its own - the farmer
    // row IS the event - so it is built from created_at.
    if ($farmer->created_at) {
        $events->push([
            'at'    => $farmer->created_at,
            'kind'  => 'Registered',
            'tone'  => 'muted',
            'title' => 'Registration submitted',
            'note'  => $farmer->association?->name
                ? 'Applied as a member of ' . $farmer->association->name
                : 'Applied without an association',
        ]);
    }

    foreach ($farmer->plantingRecords as $record) {
        $crops = $record->crops->map(fn ($crop) => $crop->crop_specify ?: $crop->crop?->name)
            ->filter()->join(', ');

        $events->push([
            'at'    => $record->date_submitted ?? $record->created_at,
            'kind'  => 'Planting',
            'tone'  => 'planting',
            'title' => $crops ?: 'Planting activity recorded',
            'note'  => number_format($record->crops->sum('area_hectares'), 2) . ' ha planted',
        ]);
    }

    foreach ($farmer->damageReports as $report) {
        $events->push([
            'at'    => $report->created_at,
            'kind'  => 'Damage report',
            'tone'  => 'damage',
            'title' => $report->reference . ' filed',
            'note'  => number_format($report->crops->sum('damaged_area_hectares'), 2) . ' ha reported damaged'
                . ($report->damage_cause_label ? ' after ' . strtolower($report->damage_cause_label) : ''),
        ]);

        // The inspection is a separate event from the report, on its own
        // date, by a different person. Collapsing the two would hide the
        // wait between them, which is the thing worth seeing here.
        if ($report->validation?->validated_at) {
            $check = $report->validation;

            $events->push([
                'at'    => $check->validated_at,
                'kind'  => 'Inspection',
                'tone'  => 'check',
                'title' => $report->reference . ' inspected',
                'note'  => (\App\Models\Validation::severityLabel($check->severity) ?? 'Assessed') . ', '
                    . round($check->assessed_damage_percent) . '% assessed by '
                    . ($check->technician?->display_name ?? $check->technician?->username ?? 'a technician'),
            ]);
        }
    }

    foreach ($farmer->assistanceDistributions as $given) {
        $events->push([
            'at'    => $given->distributed_at ?? $given->created_at,
            'kind'  => 'Assistance',
            'tone'  => 'assist',
            'title' => 'Assistance received',
            // The quantity used to open this line. No figure is recorded any
            // more (Oct 2026, client decision); the allocation reference says
            // which batch it came from.
            'note'  => ($given->assistance_allocation_id
                    ? 'From AA-' . str_pad((string) $given->assistance_allocation_id, 3, '0', STR_PAD_LEFT)
                    : 'Recorded')
                . match ($given->receipt_status) {
                    'confirmed_received' => ', confirmed by the member',
                    'not_received'       => ', the member says it did not arrive',
                    default              => ', not yet confirmed',
                },
        ]);
    }

    // Undated rows would sort unpredictably and tell nobody anything, so
    // they are dropped rather than shown at the end of time.
    $events = $events->filter(fn ($event) => $event['at'])->sortByDesc('at')->values();

    $tones = [
        'planting' => 'bg-accent text-primary',
        'damage'   => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'check'    => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'assist'   => 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300',
        'muted'    => 'bg-secondary text-secondary-foreground',
    ];
@endphp

<x-ui.card title="History" :description="$events->count() . ' recorded ' . \Illuminate\Support\Str::plural('event', $events->count())">

    @if ($events->isEmpty())
        <x-ui.empty title="Nothing recorded yet"
                    icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2"
                    message="Once this member records a planting activity or files a report, it appears here in order." />
    @else
        <ol class="relative space-y-5 border-l border-border pl-6">
            @foreach ($events as $event)
                <li class="relative">
                    {{-- The dot sits on the line itself: -left-[1.6875rem] is
                         the padding above plus half the dot, so it is centred
                         on the border rather than beside it. --}}
                    <span class="absolute -left-[1.6875rem] top-1 h-3 w-3 rounded-full ring-4 ring-card
                                 {{ $event['tone'] === 'muted' ? 'bg-muted-foreground' : 'bg-primary' }}"
                          aria-hidden="true"></span>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tones[$event['tone']] ?? $tones['muted'] }}">
                            {{ $event['kind'] }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ $event['at']->format('M d, Y') }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm font-semibold text-card-foreground">{{ $event['title'] }}</p>
                    <p class="text-sm text-muted-foreground">{{ $event['note'] }}</p>
                </li>
            @endforeach
        </ol>
    @endif
</x-ui.card>
