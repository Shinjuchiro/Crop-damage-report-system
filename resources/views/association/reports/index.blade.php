@extends('layouts.app')

@section('title', 'Damage Reports')
@section('heading', 'Damage Reports')
@section('heading-fil', 'Mga Ulat ng Pinsala')
@section('subheading', 'Reports filed by members of ' . $association->name . ', and where each one has got to.')

@section('content')

{{--
    Proposal section 61. Read only.

    The Status column is the point of this page. An officer should be able to
    answer "has anybody inspected Aling Rosa's farm yet" without phoning the
    office, and see at a glance which members are now eligible for assistance,
    which means a report a technician has actually verified.
--}}

<div class="space-y-4">

    <x-ui.card>
        <form method="GET" action="{{ route('association.reports.index') }}"
              class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="q">Search member</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="First or last name"
                       class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="status">Status</label>
                <select id="status" name="status"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="cause">Cause</label>
                <select id="cause" name="cause"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All causes</option>
                    @foreach ($causes as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['cause'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <x-ui.button type="submit" class="flex-1 sm:flex-none">Apply</x-ui.button>
                <x-ui.button variant="outline" :href="route('association.reports.index')">Clear</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card :padded="false">
        @if ($reports->isEmpty())
            <x-ui.empty title="No damage reports found"
                        icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"
                        message="Reports your members file appear here as soon as they submit them, along with what the technician found.">
                <x-ui.button variant="outline" :href="route('association.reports.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($reports as $report)
                    <li class="px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ $report->farmer?->full_name ?? 'Unknown member' }}
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

                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($report->crops as $crop)
                                <x-ui.badge variant="primary">
                                    {{ $crop->crop_specify ?: $crop->crop?->name }}
                                </x-ui.badge>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden sm:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Report</th>
                            <th>Member</th>
                            <th>Cause</th>
                            <th>Crops</th>
                            <th>Damaged Area</th>
                            <th>Farmer / Technician</th>
                            <th>Status</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($reports as $report)
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
                                    {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? '-' }}
                                </span>
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
                                {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha
                            </td>

                            {{-- Both figures, never one replacing the other --}}
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
                                @if ($report->assignedTechnician)
                                    <span class="mt-1 block text-xs text-muted-foreground">
                                        {{ $report->assignedTechnician->display_name }}
                                    </span>
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
