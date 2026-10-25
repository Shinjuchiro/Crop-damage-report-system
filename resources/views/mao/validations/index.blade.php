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

    /* Three levels since Oct 2026. 'partial' is kept for the inspections
       recorded under the old four-level scale, so an old row still reads
       correctly here; nothing new can be saved with it. */
    $severityBadges = [
        'slight'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'moderate' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'total'    => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
        'partial'  => 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    ];

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')

{{-- Section 23: this page is a HISTORY of completed field work only -
     reports still pending, assigned, or under verification live in Crop
     Damage Monitoring, which is also where a technician gets assigned in
     the first place. --}}
<x-crop-management.header tab="validation">
    {{-- This page's own figures, rendered above the tabs. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Completed Inspections" :value="number_format($summary['completed'])" hint="Technician has submitted"
                   icon="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4" />
        <x-ui.stat label="Approved" :value="number_format($summary['approved'])"
                   icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-ui.stat label="Rejected" :value="number_format($summary['rejected'])"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM15 9l-6 6M9 9l6 6" />
    </div>
</x-crop-management.header>
<div>


    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

        {{-- LIST --}}
        <div>
            @php
                /* Grouped by farmer, or flat (revision section 12). Same
                   switch as the Crop Damage list; the controller orders and
                   this works out the per-farmer counts and the position of
                   each record inside its block. */
                $grouped  = ($view ?? 'grouped') !== 'flat';
                $rows     = $reports->getCollection();
                $perGroup = $rows->groupBy(fn ($row) => $row->farmer?->id)->map->count();

            @endphp

            <x-record.view-toggle :view="$grouped ? 'grouped' : 'flat'" class="mb-4" />

            <x-ui.card title="Validation Monitoring">
                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                {{-- Filters, Export Report and the search box, on one row.
                     Export sits beside the filters because it exports exactly
                     what they are showing. It is outside the <form> because
                     it is a link, not a submission. --}}
                <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">

                    <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                     :options="$associations->pluck('name', 'id')" :selected="request('association_id')" class="sm:w-52" />

                        <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                                     :options="$barangays->pluck('name', 'id')" :selected="request('barangay_id')" class="sm:w-52" />

                        {{-- The magnifier sits inside the field, as the design
                             draws it. pointer-events-none so it never steals
                             the click that should land in the input. --}}
                        <div class="relative sm:min-w-[14rem] sm:flex-1">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                                </svg>
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search farmer name"
                                   class="h-11 w-full rounded-lg border border-input bg-card pl-9 pr-3 text-sm
                                          placeholder:text-muted-foreground focus:border-primary focus:outline-none">
                        </div>

                        @if (request()->hasAny(['search', 'association_id', 'barangay_id']))
                            <a href="{{ route('mao.validations.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                        @endif
                    </form>

                    <x-record.export-button class="lg:order-first" />
                </div>

                {{-- Table --}}
                {{-- ---------- PHONE: one card per inspection ----------
                     Cards below lg, table from lg up. The card carries the
                     same four facts the table does; the technician and the
                     inspection date are in View. --}}
                <ul class="space-y-3 lg:hidden">
                    @php $phoneLast = null; @endphp

                    @forelse ($reports as $report)
                        @if ($grouped && $report->farmer?->id !== $phoneLast)
                            @php $phoneLast = $report->farmer?->id; @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2 pt-2 first:pt-0">
                                <span class="text-sm font-bold">{{ $report->farmer?->full_name ?? 'Unknown' }}</span>
                                <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                                    {{ $perGroup[$report->farmer?->id] ?? 1 }}
                                    {{ \Illuminate\Support\Str::plural('inspection', $perGroup[$report->farmer?->id] ?? 1) }}
                                </span>
                            </li>
                        @endif

                        <li class="{{ $grouped ? 'ml-3' : '' }}">
                            <x-ui.record-card :href="$viewUrl($report->id)"
                                              :eyebrow="$grouped ? null : $report->full_reference"
                                              :title="$grouped
                                                  ? $report->reference
                                                  : ($report->farmer?->full_name ?? 'Unknown')"
                                              :status="$report->status" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No completed inspections yet</p>
                            <p class="mx-auto mt-1 max-w-md text-xs text-muted-foreground">
                                A report appears here once a technician has finished the field inspection
                                and submitted it.
                            </p>
                        </li>
                    @endforelse
                </ul>

                {{-- ---------- TABLET AND UP ---------- --}}
                {{-- The heading row is a filled band rather than a heavy
                     rule, and its labels are small caps - the same treatment
                     x-ui.table gives every other table in the system, and
                     what the design draws. --}}
                <div class="hidden overflow-x-auto rounded-xl border border-border lg:block">
                    <table class="w-full text-left">
                        <thead class="bg-muted/70">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{-- FOUR COLUMNS (revision sections 3.1 and 10.6)

                                     ID, Name, Status, Action. Association,
                                     Technician and Inspected On used to sit
                                     between them; all three are in View,
                                     along with the planting comparison, the
                                     farmer's own submission, the inspection
                                     findings and the photographs. --}}
                                <th class="px-4 py-3">Report ID</th>
                                <th class="px-4 py-3">Farmer</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @php $lastFarmer = null; @endphp

                            @forelse ($reports as $report)
                                @if ($grouped && $report->farmer?->id !== $lastFarmer)
                                    @php $lastFarmer = $report->farmer?->id; @endphp
                                    <x-record.group-head :farmer="$report->farmer"
                                                         :count="$perGroup[$report->farmer?->id] ?? 1"
                                                         :total="$farmerTotals[$report->farmer?->id] ?? null"
                                                         noun="validation record"
                                                         :colspan="4"
                                                         :href="$report->farmer ? route('mao.farmers.show', $report->farmer) : null" />
                                @endif

                                <tr class="hover:bg-muted/60 {{ $selected?->id === $report->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-4 py-4 text-foreground">
                                        {{-- Indented under the farmer's heading when grouped, so
                                             the block still reads as theirs without repeating
                                             anything. --}}
                                        <span class="{{ $grouped ? 'pl-3' : '' }}">{{ $grouped ? $report->reference : $report->full_reference }}</span>
                                    </td>
                                    {{-- The name in both views, muted under a
                                         group heading that already carries it
                                         in bold. --}}
                                    <td class="px-4 py-4 {{ $grouped ? 'text-muted-foreground' : 'font-bold text-foreground' }}">
                                        {{ $report->farmer?->full_name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$report->status] ?? $statusBadges['pending'] }}">
                                            {{ ucwords(str_replace('_', ' ', $report->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-14 text-center">
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

                {{-- "Showing 1 to 3 of 3 entries" on the left, the pages on
                     the right, as the design draws it. firstItem() is null on
                     an empty page, which is why the count is written rather
                     than assumed. --}}
                <div class="mt-6 flex flex-col gap-3 border-t border-border pt-5
                            sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">
                        @if ($reports->total() > 0)
                            Showing {{ number_format($reports->firstItem()) }}
                            to {{ number_format($reports->lastItem()) }}
                            of {{ number_format($reports->total()) }}
                            {{ \Illuminate\Support\Str::plural('entry', $reports->total()) }}
                        @else
                            No entries
                        @endif
                    </p>

                    <div>{{ $reports->links() }}</div>
                </div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a completed inspection from the list to view its comparison and summary.">
            @if ($selected)
                @php $report = $selected; $farmer = $report->farmer; $validation = $report->validation; @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $report->full_reference }}</p>
                    <h3 class="mt-0.5 text-lg font-bold text-foreground">
                        <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                    </h3>
                    <p class="text-sm text-muted-foreground">{{ $farmer->association?->name ?? '-' }} &middot; {{ $farmer->barangay?->name ?? '-' }}</p>
                    <span class="mt-2 inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $statusBadges[$report->status] ?? $statusBadges['pending'] }}">
                        {{ ucwords(str_replace('_', ' ', $report->status)) }}
                    </span>

                    {{-- Validation Monitoring has no page of its own: what is
                         being inspected IS a damage report, so the full record
                         lives on that report's page, inspection included. The
                         label says so rather than pretending otherwise. --}}
                    <x-ui.open-full-page label="Open the full damage report"
                                         :href="route('mao.damage-reports.show', $report)" />
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
                                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Technician</dt><dd class="min-w-0 text-right font-bold text-foreground">{{ $validation->technician?->full_name ?: $validation->technician?->username ?? '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Severity</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ \App\Models\Validation::severityLabel($validation->severity) ?? 'Not assessed' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farmer Estimated Damage</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $report->crops->avg('estimated_damage_percent') !== null ? round($report->crops->avg('estimated_damage_percent')) . '%' : '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Technician Assessed Damage</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $validation->assessed_damage_percent !== null ? $validation->assessed_damage_percent . '%' : '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Inspection Submitted</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $validation->validated_at?->format('M d, Y g:i A') ?? 'In progress' }}</dd></div>
                                <div><dt class="mb-1 text-muted-foreground">Inspection Notes</dt><dd class="font-medium text-foreground">{{ $validation->notes ?: 'No notes recorded.' }}</dd></div>
                            </dl>

                            @if ($validation->photos->isNotEmpty())
                                <p class="mb-2 mt-3 text-xs text-muted-foreground">Inspection photos</p>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ($validation->photos as $photo)
                                        <a href="{{ $photo->url }}" target="_blank" class="block overflow-hidden rounded-lg border border-border">
                                            <img src="{{ $photo->url }}" alt="Inspection photo" class="h-16 w-full object-cover transition hover:scale-105">
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
            @php
                    $historyRows = $history->map(fn ($h) => [
                        'label'   => $h->full_reference,
                        'meta'    => $h->created_at?->format('M d, Y'),
                        'href'    => request()->fullUrlWithQuery(['selected' => $h->id]),
                        'status'  => $h->status,
                        'current' => $h->id === $selected->id,
                    ])->all();
                @endphp
                <x-record.farmer-history title="All inspections for this farmer" :items="$historyRows" />
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
