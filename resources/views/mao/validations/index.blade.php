@extends('layouts.app')

@section('title', 'Validation Monitoring')
@section('hideHeading', true)

@php
    $statusBadges = [
        'pending'            => 'bg-secondary text-foreground',
        'assigned'           => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'under_verification' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'verified'           => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'flagged'            => 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
        'approved'           => 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300',
        'rejected'           => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ];

    $severityBadges = [
        'slight'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'moderate' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'partial'  => 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
        'total'    => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ];

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
<div>

    {{-- Section 23: this page is a HISTORY of completed field work only -
         reports still pending, assigned, or under verification live in Crop
         Damage Monitoring, which is also where a technician gets assigned in
         the first place. --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Completed Inspections" :value="number_format($summary['completed'])" hint="Technician has submitted" />
        <x-ui.stat label="Verified" :value="number_format($summary['verified'])" hint="Awaiting MAO decision" />
        <x-ui.stat label="Approved" :value="number_format($summary['approved'])" />
        <x-ui.stat label="Rejected" :value="number_format($summary['rejected'])" />
    </div>

    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a row's View is followed) the
         detail panel full-screen with its own Back link. --}}
    <div class="lg:grid lg:grid-cols-[1fr_420px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Validation Monitoring">
                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                 :options="$associations->pluck('name', 'id')" :selected="request('association_id')" class="sm:w-52" />

                    <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                                 :options="$barangays->pluck('name', 'id')" :selected="request('barangay_id')" class="sm:w-52" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer"
                                class="sm:min-w-[12rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'association_id', 'barangay_id']))
                        <a href="{{ route('mao.validations.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                                <th class="px-3 py-3">Report ID</th>
                                <th class="px-3 py-3">Farmer</th>
                                <th class="px-3 py-3">Association</th>
                                <th class="px-3 py-3">Technician</th>
                                <th class="px-3 py-3">Inspected On</th>
                                <th class="px-3 py-3 text-center">Severity</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse ($reports as $report)
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $report->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4 text-foreground">{{ $report->reference }}</td>
                                    <td class="px-3 py-4">
                                        <p class="font-medium text-foreground">{{ $report->farmer?->full_name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $report->farmer?->barangay?->name }}</p>
                                    </td>
                                    <td class="px-3 py-4 text-muted-foreground">{{ $report->farmer?->association?->name ?? '-' }}</td>
                                    <td class="px-3 py-4 text-foreground">
                                        {{ $report->assignedTechnician?->full_name ?: $report->assignedTechnician?->username ?? '-' }}
                                    </td>
                                    <td class="px-3 py-4 text-muted-foreground">{{ $report->validation?->validated_at?->format('M d, Y') ?? '-' }}</td>
                                    <td class="px-3 py-4 text-center">
                                        @if ($report->validation?->severity)
                                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium capitalize {{ $severityBadges[$report->validation->severity] ?? 'bg-secondary text-foreground' }}">
                                                {{ $report->validation->severity }}
                                            </span>
                                        @else
                                            <span class="text-muted-foreground">-</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$report->status] ?? $statusBadges['pending'] }}">
                                            {{ ucwords(str_replace('_', ' ', $report->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-14 text-center">
                                        <p class="text-sm font-medium text-muted-foreground">No completed inspections yet</p>
                                        <p class="mx-auto mt-1 max-w-md text-xs text-muted-foreground">
                                            A report appears here once a technician has finished the field inspection
                                            and submitted it. Reports still waiting for or undergoing inspection are in
                                            <a href="{{ route('mao.damage-reports.index') }}" class="font-medium text-green-800 hover:underline">Crop Damage Monitoring</a>.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 border-t border-border pt-5">{{ $reports->links() }}</div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a completed inspection from the list to view its comparison and summary.">
            @if ($selected)
                @php $report = $selected; $farmer = $report->farmer; $validation = $report->validation; @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $report->reference }}</p>
                    <h3 class="mt-0.5 text-lg font-semibold text-foreground">
                        <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                    </h3>
                    <p class="text-sm text-muted-foreground">{{ $farmer->association?->name ?? '-' }} &middot; {{ $farmer->barangay?->name ?? '-' }}</p>
                    <span class="mt-2 inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $statusBadges[$report->status] ?? $statusBadges['pending'] }}">
                        {{ ucwords(str_replace('_', ' ', $report->status)) }}
                    </span>
                </div>

                <div class="space-y-5 border-t border-border pt-4 text-sm">
                    {{-- Planted Report vs Damage Report --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Planted Report vs. Damage Report
                        </p>
                        @forelse ($plantingComparison as $row)
                            @php $reportCrop = $row['reportCrop']; $planted = $row['planted']; @endphp
                            <div class="mb-2 overflow-hidden rounded-lg border border-border">
                                <p class="border-b border-border bg-muted px-3 py-1.5 text-xs font-semibold text-foreground">
                                    {{ $reportCrop->crop?->name ?? '-' }}
                                </p>
                                <div class="grid grid-cols-2 divide-x divide-border text-xs">
                                    <div class="px-3 py-2">
                                        <p class="mb-1 font-medium text-muted-foreground">Planting Record</p>
                                        @if ($planted)
                                            <p class="text-foreground">Planted: {{ $planted->date_planted?->format('M d, Y') }}</p>
                                            <p class="text-foreground">Area: {{ number_format($planted->area_hectares, 2) }} ha</p>
                                        @else
                                            <p class="text-muted-foreground">No matching planting record found.</p>
                                        @endif
                                    </div>
                                    <div class="px-3 py-2">
                                        <p class="mb-1 font-medium text-muted-foreground">Damage Report</p>
                                        <p class="text-foreground">Planted: {{ $reportCrop->date_planted?->format('M d, Y') ?? '-' }}</p>
                                        <p class="text-foreground">Damaged Area: {{ $reportCrop->damaged_area_hectares }} ha</p>
                                        <p class="text-foreground">Farmer Est. Damage: {{ $reportCrop->estimated_damage_percent }}%</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground">No crops recorded on this report.</p>
                        @endforelse
                    </div>

                    {{-- Inspection Summary --}}
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Inspection Summary</p>
                        @if ($validation)
                            <dl class="space-y-1.5">
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Technician</dt><dd class="font-medium text-foreground">{{ $validation->technician?->full_name ?: $validation->technician?->username ?? '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Severity</dt><dd class="font-medium capitalize text-foreground">{{ $validation->severity ?? 'Not assessed' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Farmer Estimated Damage</dt><dd class="font-medium text-foreground">{{ $report->crops->avg('estimated_damage_percent') !== null ? round($report->crops->avg('estimated_damage_percent')) . '%' : '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Technician Assessed Damage</dt><dd class="font-medium text-foreground">{{ $validation->assessed_damage_percent !== null ? $validation->assessed_damage_percent . '%' : '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Inspection Submitted</dt><dd class="font-medium text-foreground">{{ $validation->validated_at?->format('M d, Y g:i A') ?? 'In progress' }}</dd></div>
                                <div><dt class="mb-1 text-muted-foreground">Inspection Notes</dt><dd class="font-medium text-foreground">{{ $validation->notes ?: 'No notes recorded.' }}</dd></div>
                            </dl>

                            @if ($validation->photos->isNotEmpty())
                                <p class="mb-2 mt-3 text-xs text-muted-foreground">Inspection photos</p>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ($validation->photos as $photo)
                                        <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" class="block overflow-hidden rounded-lg border border-border">
                                            <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Inspection photo" class="h-16 w-full object-cover transition hover:scale-105">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <p class="text-xs text-muted-foreground">No inspection has been submitted for this report yet.</p>
                        @endif
                    </div>

                    {{-- Technician-verified location --}}
                    @if ($validation && $validation->latitude && $validation->longitude)
                        <div class="border-t border-border pt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Technician-Verified Location</p>
                            <div id="verifiedMap-{{ $report->id }}" class="h-48 w-full rounded-lg border border-border"
                                 data-lat="{{ $validation->latitude }}" data-lng="{{ $validation->longitude }}"
                                 data-label="{{ $farmer->full_name }} &middot; {{ $farmer->barangay?->name }}"></div>
                        </div>
                    @endif

                    <div class="border-t border-border pt-4">
                        <a href="{{ route('mao.damage-reports.index', ['selected' => $report->id]) }}"
                           class="text-xs font-semibold text-green-800 hover:underline">
                            Open in Crop Damage Monitoring &rarr;
                        </a>
                    </div>
                </div>
            @endif
        </x-ui.detail-panel>
    </div>
</div>

@if ($selected && $selected->validation && $selected->validation->latitude && $selected->validation->longitude)
    @push('head')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    @endpush
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const el = document.getElementById('verifiedMap-{{ $selected->id }}');
                if (! el || typeof L === 'undefined') return;

                const lat = parseFloat(el.dataset.lat);
                const lng = parseFloat(el.dataset.lng);
                const map = L.map(el).setView([lat, lng], 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                L.marker([lat, lng]).addTo(map).bindPopup(el.dataset.label).openPopup();
            });
        </script>
    @endpush
@endif
@endsection
