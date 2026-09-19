@extends('layouts.app')

@section('title', 'Assigned Reports')
@section('heading', 'Assigned Reports')
@section('heading-fil', 'Mga Nakatalagang Ulat')
@section('subheading', 'Every damage report the office has assigned to you.')

@section('content')

{{--
    The full assignment list. The dashboard shows the first six rows of this
    same data; this is where a technician comes when they need all of it,
    with filters and pagination.

    On a phone the table would need sideways scrolling, so below sm the rows
    are rendered as cards instead. Same records, same order, just laid out
    for a thumb.
--}}

<div class="space-y-4">

    {{-- Filters --}}
    <x-ui.card>
        <form method="GET" action="{{ route('technician.reports.index') }}"
              class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">

            <div class="space-y-1.5 sm:col-span-2 lg:col-span-1">
                <label class="block text-sm font-medium" for="q">Search</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Farmer name or DR-0001"
                       class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="status">Status</label>
                <select id="status" name="status"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\DamageReport::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="barangay">Barangay</label>
                <select id="barangay" name="barangay"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $option)
                        <option value="{{ $option->id }}"
                                @selected((string) ($filters['barangay'] ?? '') === (string) $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <x-ui.button type="submit" class="flex-1 sm:flex-none">Apply</x-ui.button>
                <x-ui.button variant="outline" :href="route('technician.reports.index')">Clear</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card :padded="false">
        @if ($reports->isEmpty())
            <x-ui.empty title="No reports match this"
                        message="Either nothing has been assigned to you yet, or the filters above are hiding everything. Try clearing them.">
                <x-ui.button variant="outline" :href="route('technician.reports.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE: one card per report ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($reports as $report)
                    <li>
                        <a href="{{ $report->status === 'under_verification'
                                ? route('technician.inspection.edit', $report)
                                : route('technician.reports.show', $report) }}"
                           class="block px-4 py-4 transition active:bg-muted">

                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">{{ $report->reference }}</p>
                                    <p class="truncate text-sm">{{ $report->farmer?->full_name ?? 'Unknown farmer' }}</p>
                                </div>
                                <x-ui.status :value="$report->status" />
                            </div>

                            <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <div>
                                    <dt class="inline font-medium">Barangay:</dt>
                                    <dd class="inline">{{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="inline font-medium">Submitted:</dt>
                                    <dd class="inline">{{ $report->created_at?->format('M d, Y') }}</dd>
                                </div>
                            </dl>

                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($report->crops as $crop)
                                    <x-ui.badge variant="primary">{{ $crop->crop_specify ?: $crop->crop?->name }}</x-ui.badge>
                                @endforeach
                            </div>

                            {{-- Match signal only (section 62) - never eligible/ineligible --}}
                            <p class="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                                @if ($report->hasPlantingMatch)
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                    Matching planting record on file
                                @else
                                    No matching planting record
                                @endif
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP: the table ---------- --}}
            <div class="hidden sm:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Report Id</th>
                            <th>Farmer</th>
                            <th>Barangay</th>
                            <th>Crop</th>
                            <th>Planting Record</th>
                            <th>Severity</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($reports as $report)
                        @php
                            $assessed = $report->validation?->assessed_damage_percent;
                            $estimate = $report->crops->avg('estimated_damage_percent');
                            $shown    = $assessed ?? $estimate;
                        @endphp

                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $report->reference }}</td>

                            <td>
                                <span class="font-medium">{{ $report->farmer?->full_name ?? 'Unknown' }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ $report->farmer?->association?->name ?? 'No association' }}
                                </span>
                            </td>

                            <td class="text-muted-foreground">
                                {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? '-' }}
                            </td>

                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($report->crops as $crop)
                                        <x-ui.badge variant="primary">{{ $crop->crop_specify ?: $crop->crop?->name }}</x-ui.badge>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Match signal only (section 62) - never an eligible/ineligible
                                 label. A "no match" report is still fully inspectable. --}}
                            <td class="whitespace-nowrap text-xs text-muted-foreground">
                                @if ($report->hasPlantingMatch)
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                        Matching record on file
                                    </span>
                                @else
                                    No matching record
                                @endif
                            </td>

                            <td class="whitespace-nowrap">
                                @if ($shown === null)
                                    <span class="text-muted-foreground">-</span>
                                @else
                                    <span class="font-semibold">{{ round($shown) }}%</span>
                                    <span class="block text-xs text-muted-foreground">
                                        {{ $assessed !== null ? 'you assessed' : 'farmer estimate' }}
                                    </span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap text-muted-foreground">
                                {{ $report->created_at?->format('M d, Y') }}
                            </td>

                            <td><x-ui.status :value="$report->status" /></td>

                            <td class="whitespace-nowrap text-right">
                                @if ($report->status === 'under_verification')
                                    <x-ui.button size="sm" :href="route('technician.inspection.edit', $report)">
                                        Continue
                                    </x-ui.button>
                                @else
                                    <x-ui.button size="sm" variant="view"
                                                 :href="route('technician.reports.show', $report)">
                                        View Details
                                    </x-ui.button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif

        @if ($reports->hasPages())
            <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
