@extends('layouts.app')

@section('title', 'Damage Reports')
@section('heading', 'Damage Reports')
@section('heading-fil', 'Mga Ulat ng Pinsala')
@section('subheading', "Every damage report filed in the barangays you've worked in, whoever is handling it.")

@section('content')

{{--
    Wider than Assigned Reports on purpose: this shows all damage activity
    in this technician's barangays, not only the reports assigned to them.
    Read only - "View Details" only appears on a row when the report is
    actually assigned to the signed in technician, because InspectionController
    still checks ownership on every action. For everything else, this page is
    the answer to "has anyone picked this up yet".
--}}

<div class="space-y-4">

    <x-ui.card :padded="false">
        <div class="border-b border-border px-4 pt-4 sm:px-5 sm:pt-5">
            <x-ui.filter-bar :fields="['q', 'barangay', 'status']">
                <x-ui.input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Search farmer" class="sm:w-52" />

                <x-ui.select name="barangay" placeholder="All your barangays" onchange="this.form.submit()"
                             :options="$barangays->pluck('name', 'id')"
                             :selected="$filters['barangay'] ?? null" class="sm:w-44" />

                <x-ui.select name="status" placeholder="All statuses" onchange="this.form.submit()"
                             :options="$statuses" :selected="$filters['status'] ?? null" class="sm:w-40" />
            </x-ui.filter-bar>
        </div>

        @if ($reports->isEmpty())
            <x-ui.empty title="No damage reports found"
                        icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"
                        message="Once the office assigns you a report in a barangay, every damage report filed there appears here, whoever is inspecting it.">
                <x-ui.button variant="outline" :href="route('technician.damage.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($reports as $report)
                    @php $mine = $report->assigned_technician_id === auth()->id(); @endphp
                    <li>
                        @if ($mine)
                            <a href="{{ route('technician.reports.show', $report) }}" class="block px-4 py-4 transition active:bg-muted">
                        @else
                            <div class="px-4 py-4">
                        @endif
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">
                                        {{ $report->farmer?->full_name ?? 'Unknown farmer' }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">{{ $report->reference }}</p>
                                </div>
                                <x-ui.status :value="$report->status" />
                            </div>

                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ $report->damage_cause_label }}
                                &middot; {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Location not set' }}
                                &middot; {{ $report->created_at?->format('M d, Y') }}
                            </p>

                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ $mine ? 'Assigned to you' : 'Assigned to ' . ($report->assignedTechnician?->display_name ?? 'nobody yet') }}
                            </p>

                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($report->crops as $crop)
                                    <x-ui.badge variant="primary">
                                        {{ $crop->crop_specify ?: $crop->crop?->name }}
                                    </x-ui.badge>
                                @endforeach
                            </div>
                        @if ($mine)
                            </a>
                        @else
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden sm:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Report</th>
                            <th>Farmer</th>
                            <th>Barangay</th>
                            <th>Cause</th>
                            <th>Crops</th>
                            <th>Farmer / Technician</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($reports as $report)
                        @php $mine = $report->assigned_technician_id === auth()->id(); @endphp
                        <tr>
                            <td class="whitespace-nowrap font-medium">
                                {{ $report->reference }}
                                <span class="block text-xs text-muted-foreground">
                                    {{ $report->created_at?->format('M d, Y') }}
                                </span>
                                @if ($report->photos_count)
                                    <span class="block text-xs text-muted-foreground">
                                        {{ $report->photos_count }} photo{{ $report->photos_count === 1 ? '' : 's' }}
                                    </span>
                                @endif
                            </td>

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
                                {{ $report->damage_cause_label }}
                                @if ($report->disasters->isNotEmpty())
                                    <span class="block text-xs text-muted-foreground">
                                        {{ $report->disasters->pluck('name')->join(', ') }}
                                    </span>
                                @endif
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
                                <span class="text-muted-foreground">
                                    {{ round($report->crops->avg('estimated_damage_percent')) }}%
                                </span>
                                <span class="mx-1 text-muted-foreground">/</span>
                                <span class="font-semibold">
                                    {{ $report->validation?->assessed_damage_percent !== null
                                        ? round($report->validation->assessed_damage_percent) . '%'
                                        : 'not yet' }}
                                </span>
                            </td>

                            <td>
                                <x-ui.status :value="$report->status" />
                                <span class="mt-1 block text-xs text-muted-foreground">
                                    {{ $mine ? 'You' : ($report->assignedTechnician?->display_name ?? 'Unassigned') }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap text-right">
                                @if ($mine)
                                    <x-ui.button size="sm" variant="view"
                                                 :href="route('technician.reports.show', $report)">
                                        View
                                    </x-ui.button>
                                @else
                                    <span class="text-xs text-muted-foreground">Not yours</span>
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
