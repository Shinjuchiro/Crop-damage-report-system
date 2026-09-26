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

    <x-ui.card :padded="false">
        <div class="border-b border-border px-4 pt-4 sm:px-5 sm:pt-5">
            <x-ui.filter-bar :fields="['q', 'status', 'barangay']">
                <x-ui.input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Farmer name or DR-0001" class="sm:w-52" />

                <x-ui.select name="status" placeholder="All statuses" onchange="this.form.submit()"
                             :options="\App\Models\DamageReport::STATUSES"
                             :selected="$filters['status'] ?? null" class="sm:w-40" />

                <x-ui.select name="barangay" placeholder="All barangays" onchange="this.form.submit()"
                             :options="$barangays->pluck('name', 'id')"
                             :selected="$filters['barangay'] ?? null" class="sm:w-44" />
            </x-ui.filter-bar>
        </div>

        @if ($reports->isEmpty())
            <x-ui.empty title="No reports match this"
                        message="Either nothing has been assigned to you yet, or the filters above are hiding everything. Try clearing them.">
                <x-ui.button variant="outline" :href="route('technician.reports.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE: one card per report ----------
                 Shared with the dashboard queue: see
                 technician/partials/report-card.blade.php. --}}
            <ul class="space-y-3 p-4 sm:hidden">
                @foreach ($reports as $report)
                    <li>@include('technician.partials.report-card', [
                            'report'            => $report,
                            'showPlantingMatch' => true,
                        ])</li>
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
