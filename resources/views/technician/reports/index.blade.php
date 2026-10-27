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
            <ul class="space-y-3 p-4 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                @foreach ($reports as $report)
                    <li>@include('technician.partials.report-card', [
                            'report'            => $report,
                            'showPlantingMatch' => true,
                        ])</li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP: one folded row per farmer ----------
                 Same shape as the MAO's Crop Management Report: FARMER ID |
                 FARMER | ASSOCIATION | NUMBER OF REPORTS. Opening a farmer
                 shows all of their reports to you. One is open at a time. --}}
            @php
                $rows   = $reports->getCollection();
                $openId = $rows->pluck('farmer_id')->unique()->count() === 1 ? $rows->first()?->farmer_id : null;
            @endphp

            <div class="hidden p-4 sm:p-5 lg:block">
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left">
                        <thead class="bg-muted/70">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <th class="px-4 py-3">Farmer ID</th>
                                <th class="px-4 py-3">Farmer</th>
                                <th class="px-4 py-3">Association</th>
                                <th class="px-4 py-3 text-center">Number of Reports</th>
                                <th class="w-12 px-4 py-3"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm" x-data="{ open: {{ $openId ?? 'null' }} }">
                            @foreach ($rows->groupBy(fn ($row) => (int) $row->farmer_id) as $farmerId => $group)
                                @php $farmer = $group->first()->farmer; @endphp
                                <x-record.farmer-block :farmer="$farmer"
                                                       :count="$group->count()"
                                                       :group-id="$farmerId"
                                                       :headers="[['Report ID'], ['Barangay'], ['Submitted'], ['Status'], ['Action', 'text-right']]">
                                    @foreach ($group as $report)
                                        <tr class="hover:bg-muted/60">
                                            <td class="whitespace-nowrap px-4 py-4 font-medium text-foreground">{{ $report->full_reference }}</td>
                                            <td class="px-4 py-4 text-muted-foreground">
                                                {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? '-' }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-4 text-muted-foreground">
                                                {{ $report->created_at?->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-4"><x-ui.status :value="$report->status" /></td>
                                            {{-- One action, always the same one. This page is read
                                                 only: reviewing what the farmer submitted is all
                                                 it does, and recording an inspection is
                                                 Validation's job. --}}
                                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                                <x-ui.button size="sm" variant="view"
                                                             :href="route('technician.reports.show', $report)">
                                                    View Details
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </x-record.farmer-block>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($reports->isNotEmpty())
            <x-slot:footer>
                @php [$from, $to, $total] = \App\Support\FarmerGroups::range($reports); @endphp
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">
                        Showing {{ number_format($from) }} to {{ number_format($to) }}
                        of {{ number_format($total) }} {{ \Illuminate\Support\Str::plural('farmer', $total) }}
                    </p>
                    @if ($reports->hasPages())
                        <div>{{ $reports->links() }}</div>
                    @endif
                </div>
            </x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
