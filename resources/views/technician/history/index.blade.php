@extends('layouts.app')

@section('title', 'Inspection History')
@section('heading', 'Inspection History')
@section('heading-fil', 'Kasaysayan ng Pagsusuri')
@section('subheading', 'Every inspection you have submitted, newest first.')

@section('content')

{{--
    Driven off the validations table rather than the report status.

    That matters: once the office approves or rejects a report the status
    moves on, but the inspection still happened and the technician who did
    it should still be able to find their own work. Reading from validations
    means nothing ever disappears from this page.

    Read only, deliberately. A submitted inspection is a record, and section
    52 says it stops being editable the moment it is submitted.
--}}

<div class="space-y-4">

    <x-ui.card>
        <form method="GET" action="{{ route('technician.history.index') }}"
              class="grid gap-3 sm:grid-cols-3 sm:items-end">

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="q">Search farmer</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Farmer name"
                       class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="severity">Severity</label>
                <select id="severity" name="severity"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All severities</option>
                    @foreach ($severities as $key => $band)
                        <option value="{{ $key }}" @selected(($filters['severity'] ?? '') === $key)>
                            {{ $band['label'] }} ({{ $band['range'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <x-ui.button type="submit" class="flex-1 sm:flex-none">Apply</x-ui.button>
                <x-ui.button variant="outline" :href="route('technician.history.index')">Clear</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card :padded="false">
        @if ($inspections->isEmpty())
            <x-ui.empty title="No submitted inspections yet"
                        icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2"
                        message="Once you complete and submit a field inspection it is recorded here permanently, even after the office approves the report.">
                <x-ui.button variant="outline" :href="route('technician.validation.index')">Go to Validation</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($inspections as $inspection)
                    <li>
                        <a href="{{ route('technician.reports.show', $inspection->damageReport) }}"
                           class="block px-4 py-4 transition active:bg-muted">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">{{ $inspection->damageReport?->reference }}</p>
                                    <p class="truncate text-sm">
                                        {{ $inspection->damageReport?->farmer?->full_name ?? 'Unknown farmer' }}
                                    </p>
                                </div>
                                <x-ui.status :value="$inspection->severity" />
                            </div>

                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ round($inspection->assessed_damage_percent) }}% assessed
                                &middot; {{ $inspection->photos_count }} photo{{ $inspection->photos_count === 1 ? '' : 's' }}
                                &middot; {{ $inspection->validated_at?->format('M d, Y') }}
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden sm:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Report Id</th>
                            <th>Farmer</th>
                            <th>Barangay</th>
                            <th>Severity</th>
                            <th>Farmer / You</th>
                            <th>Photos</th>
                            <th>Submitted</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($inspections as $inspection)
                        @php
                            $report   = $inspection->damageReport;
                            $estimate = $report?->crops->avg('estimated_damage_percent');
                        @endphp

                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $report?->reference ?? '-' }}</td>

                            <td>
                                <span class="font-medium">{{ $report?->farmer?->full_name ?? 'Unknown' }}</span>
                            </td>

                            <td class="text-muted-foreground">
                                {{ $report?->reportedBarangay?->name ?? $report?->farmer?->barangay?->name ?? '-' }}
                            </td>

                            <td><x-ui.status :value="$inspection->severity" /></td>

                            {{-- Both figures side by side. Section 45: the
                                 farmer's estimate is never overwritten, so the
                                 gap between the two stays visible forever. --}}
                            <td class="whitespace-nowrap">
                                <span class="text-muted-foreground">
                                    {{ $estimate !== null ? round($estimate) . '%' : '-' }}
                                </span>
                                <span class="mx-1 text-muted-foreground">/</span>
                                <span class="font-semibold">{{ round($inspection->assessed_damage_percent) }}%</span>
                            </td>

                            <td class="text-muted-foreground">{{ $inspection->photos_count }}</td>

                            <td class="whitespace-nowrap text-muted-foreground">
                                {{ $inspection->validated_at?->format('M d, Y g:i A') }}
                            </td>

                            <td class="whitespace-nowrap text-right">
                                @if ($report)
                                    <x-ui.button size="sm" variant="outline"
                                                 :href="route('technician.reports.show', $report)">
                                        View
                                    </x-ui.button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif

        @if ($inspections->hasPages())
            <x-slot:footer>{{ $inspections->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
