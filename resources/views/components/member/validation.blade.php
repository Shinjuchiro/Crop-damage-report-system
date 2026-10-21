{{--
    VALIDATION & ASSESSMENT, for one member.

        <x-member.validation :farmer="$farmer" />

    Shared by the office's farmer profile and the association's member
    profile, because it is the same question from both sides: what did the
    technician find on this member's reports.

    Expects $farmer (a Farmer) with damageReports.validation.technician and
    damageReports.crops.crop loaded. Read only everywhere - an inspection is
    the technician's record and nobody edits it after submission (section 52).

    BOTH NUMBERS, ALWAYS

    The farmer's own estimate and the technician's assessed figure sit side
    by side and neither is ever overwritten (section 45). That is the point
    of this tab: not "how much damage", but "how far apart were the two
    people who looked at it", which is what tells the office whether a report
    needs a second look.

    A report with no inspection yet is still listed, with what it is waiting
    for, rather than being hidden - "nothing here" would otherwise read as
    "nothing to inspect".
--}}

@props(['farmer'])

@php
    $reports = $farmer->damageReports->sortByDesc('created_at');
    $done    = $reports->filter(fn ($report) => $report->validation?->validated_at);
@endphp

<x-ui.card title="Validation & Assessment"
           :description="$done->count() . ' of ' . $reports->count() . ' reports inspected'"
           :padded="false">

    @if ($reports->isEmpty())
        <x-ui.empty title="Nothing to inspect"
                    icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                    message="This member has not reported any crop damage, so there is nothing for a technician to verify." />
    @else
        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th>Report</th>
                    <th>Crops</th>
                    <th>Technician</th>
                    <th>Farmer / Assessed</th>
                    <th>Severity</th>
                    <th>Status</th>
                </tr>
            </x-slot:head>

            @foreach ($reports as $report)
                @php $check = $report->validation; @endphp

                <tr>
                    <td class="whitespace-nowrap font-medium">
                        {{ $report->reference }}
                        <span class="block text-xs font-normal text-muted-foreground">
                            {{ $check?->validated_at?->format('M d, Y') ?? 'filed ' . $report->created_at?->format('M d, Y') }}
                        </span>
                    </td>

                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($report->crops as $crop)
                                <x-ui.badge variant="primary">
                                    {{ $crop->crop_specify ?: $crop->crop?->name }}
                                </x-ui.badge>
                            @endforeach
                        </div>
                    </td>

                    <td class="whitespace-nowrap">
                        {{ $check?->technician?->display_name
                            ?? $check?->technician?->username
                            ?? '-' }}
                    </td>

                    {{-- Section 45: the farmer's estimate is never replaced by
                         the technician's, so both are shown and labelled. --}}
                    <td class="whitespace-nowrap">
                        <span class="text-muted-foreground">
                            {{ round($report->crops->avg('estimated_damage_percent')) }}%
                        </span>
                        <span class="mx-1 text-muted-foreground">/</span>
                        <span class="font-semibold">
                            {{ $check?->assessed_damage_percent !== null
                                ? round($check->assessed_damage_percent) . '%'
                                : 'not yet' }}
                        </span>
                    </td>

                    <td>
                        @if ($check?->severity)
                            <x-ui.status :value="$check->severity" />
                        @else
                            <span class="text-xs text-muted-foreground">Not assessed</span>
                        @endif
                    </td>

                    <td><x-ui.status :value="$report->status" /></td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-ui.card>
