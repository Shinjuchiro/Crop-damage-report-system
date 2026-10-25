@extends('layouts.app')

@section('title', 'Crop Damage Monitoring')
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

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')

<x-crop-management.header tab="damage">
    {{-- This page's own figures, rendered above the tabs.

         THREE CARDS, IN THE SAME GRID AS THE OTHER TWO TABS (section 5)

         This was four cards in `sm:grid-cols-2 xl:grid-cols-4`, while Crop
         Planted and Validation Monitoring both had three in
         `sm:grid-cols-3`. So the row of figures changed shape as you moved
         between tabs - four boxes on one, three on the next, and a different
         number of columns at every breakpoint. Revision section 5 asks for
         the opposite: "Keep the three summary cards in consistent positions
         across tabs."

         WHICH CARD WENT, AND WHY THAT ONE

         Total Affected Area. The other three are the pipeline this screen
         exists to move - what has not been assigned, what a technician is
         working on, what has come back verified - and Pending is the one
         that needs somebody to act. Dropping any of them would take a stage
         of the workflow off the screen that manages it.

         The area total also has a problem of its own, which is the better
         reason. It is a sum of affected hectares over every damage report on
         this list, all time, so the same field reported in two separate
         typhoons is counted twice. Section 14 names that exact fault:
         "Avoid adding repeated historical farm areas in ways that
         incorrectly represent unique farmland." It read as "this much
         farmland in Tanza is damaged" and it was not that number.

         Affected area is still reported, per period and without the
         double-count, in Reports & Analytics, which is where a total
         belongs. Nothing else on this page read the figure, so the query
         behind it came out of the controller too rather than being left to
         run unread on every page load. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Pending" :value="number_format($summary['pending'])" hint="Not yet assigned"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2" />
        <x-ui.stat label="Under Verification" :value="number_format($summary['under_verification'])"
                   icon="M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15zM21 21l-5.2-5.2" />
        <x-ui.stat label="Verified" :value="number_format($summary['verified'])"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8" />
    </div>
</x-crop-management.header>
<div x-data="{ assigning: null }">


    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

        {{-- LIST --}}
        <div>
            @php
                /* Grouped by farmer, or flat (revision section 11). The
                   controller orders; this works out each farmer's count on
                   the page, for the heading above their block.

                   There used to be a running "Report 1, Report 2" number in
                   the first column as well. It was dropped: every row
                   already carries its reference, and a second number beside
                   DR-0003 only invited the question of which of the two to
                   quote. */
                $grouped  = ($view ?? 'grouped') !== 'flat';
                $rows     = $reports->getCollection();
                $perGroup = $rows->groupBy(fn ($row) => $row->farmer?->id)->map->count();
            @endphp

            <x-record.view-toggle :view="$grouped ? 'grouped' : 'flat'" class="mb-4" />

            <x-ui.card title="Crop Damage Monitoring">
                <x-slot:actions>
                    <a href="{{ route('mao.archive.index', ['type' => 'damage_reports']) }}"
                       class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                        View archived
                    </a>
                </x-slot:actions>

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

                        <x-ui.select name="disaster_id" placeholder="All Disasters" onchange="this.form.submit()"
                                     :options="$disasters->pluck('name', 'id')" :selected="request('disaster_id')" class="sm:w-52" />

                        <x-ui.select name="status" placeholder="All Statuses" onchange="this.form.submit()"
                                     :options="collect($statuses)->mapWithKeys(fn ($v) => [$v => ucwords(str_replace('_', ' ', $v))])"
                                 :selected="request('status')" class="sm:w-44" />

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

                        @if (request()->hasAny(['search', 'association_id', 'disaster_id', 'status']))
                            <a href="{{ route('mao.damage-reports.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                        @endif
                    </form>

                    <x-record.export-button class="lg:order-first" />
                </div>

                {{-- ---------- PHONE: one card per report ----------
                     Cards rather than a sideways-scrolling table, from the
                     shared x-ui.record-card. Each one now carries exactly
                     what the four columns carry - reference, name, status
                     and the tap that opens it - since the disaster, the date
                     and the inspection state moved into View with the rest
                     (revision section 3.1). --}}
                <ul class="space-y-3 lg:hidden">
                    @php $phoneLast = null; @endphp

                    @forelse ($reports as $report)
                        @if ($grouped && $report->farmer?->id !== $phoneLast)
                            @php $phoneLast = $report->farmer?->id; @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2 pt-2 first:pt-0">
                                <span class="text-sm font-bold">{{ $report->farmer?->full_name ?? 'Unknown' }}</span>
                                <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                                    {{ $perGroup[$report->farmer?->id] ?? 1 }}
                                    {{ \Illuminate\Support\Str::plural('report', $perGroup[$report->farmer?->id] ?? 1) }}
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
                            <p class="text-sm font-medium text-muted-foreground">No damage reports yet</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Reports appear here as soon as farmers submit them.
                            </p>
                        </li>
                    @endforelse
                </ul>

                {{-- ---------- TABLET AND UP: the table ---------- --}}
                {{-- The heading row is a filled band rather than a heavy
                     rule, and its labels are small caps - the same treatment
                     x-ui.table gives every other table in the system, and
                     what the design draws. --}}
                <div class="hidden overflow-x-auto rounded-xl border border-border lg:block">
                    <table class="w-full text-left">
                        <thead class="bg-muted/70">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{-- FOUR COLUMNS (revision section 3.1 and 9.5)

                                     ID, Name, Status, Action. Association,
                                     Disaster Type, Date Reported and a
                                     second Validation badge used to sit
                                     between them. All four are in View, and
                                     the Validation badge in particular said
                                     again what Status already says: a report
                                     under verification has an inspection in
                                     progress by definition. --}}
                                <th class="px-4 py-3">Report ID</th>
                                <th class="px-4 py-3">Farmer</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Action</th>
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
                                                         noun="damage report"
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
                                         in bold. It used to be the barangay
                                         alone when grouped, which left the
                                         column saying something its own
                                         header did not. --}}
                                    <td class="px-4 py-4 {{ $grouped ? 'text-muted-foreground' : 'font-bold text-foreground' }}">
                                        {{ $report->farmer?->full_name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$report->status] ?? $statusBadges['pending'] }}">
                                            {{ ucwords(str_replace('_', ' ', $report->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex justify-end gap-2">
                                            <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-14 text-center">
                                        <p class="text-sm font-medium text-muted-foreground">No damage reports yet</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Reports appear here as soon as farmers submit them.
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
                            empty-text="Select a report from the list to view its full details.">
            @if ($selected)
                @php
                    $damageReport = $selected;
                    $farmer = $damageReport->farmer;
                    $validation = $damageReport->validation;
                    $canDecide = in_array($damageReport->status, ['verified', 'flagged'], true);
                @endphp

                <div x-data="{
                        decision: null,
                        editingDisasters: false,
                        confirmingDisasters: false,
                        disasterIds: {!! json_encode($damageReport->disasters->pluck('id')->map(fn ($id) => (string) $id)->values()) !!},
                        disasterLabels: {!! json_encode($availableDisasters->pluck('name', 'id')) !!},
                     }">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $damageReport->full_reference }}</p>
                            <h3 class="mt-0.5 text-lg font-bold text-foreground">
                                <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                            </h3>
                            <p class="text-sm text-muted-foreground">{{ $farmer->association?->name ?? '-' }} &middot; {{ $farmer->barangay?->name ?? '-' }}</p>
                        </div>
                    </div>

                    <span class="inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $statusBadges[$damageReport->status] ?? $statusBadges['pending'] }}">
                        {{ ucwords(str_replace('_', ' ', $damageReport->status)) }}
                    </span>

                    <x-ui.open-full-page :href="route('mao.damage-reports.show', $damageReport)" />

                    <div class="mt-4 space-y-4 border-t border-border pt-4 text-sm">
                        {{-- Cause + declared event --}}
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cause of Damage</p>
                            <p class="mb-2 rounded-lg bg-muted px-3 py-2 text-xs font-medium text-foreground">{{ $damageReport->damage_cause_label }}</p>

                            <div class="mb-1 flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">Declared event(s)</p>
                                @if ($canEditDisasters)
                                    <button type="button" x-show="! editingDisasters" @click="editingDisasters = true"
                                            class="text-xs font-semibold text-green-800 hover:underline">
                                        Correct
                                    </button>
                                @endif
                            </div>

                            <template x-if="! editingDisasters">
                                <div>
                                    @forelse ($damageReport->disasters as $disaster)
                                        <p class="text-xs text-foreground">{{ $disaster->name }} <span class="text-muted-foreground">({{ ucwords(str_replace('_', ' ', $disaster->type)) }})</span></p>
                                    @empty
                                        <p class="text-xs text-muted-foreground">No declared event attached.</p>
                                    @endforelse
                                </div>
                            </template>

                            @if ($canEditDisasters)
                                <div x-show="editingDisasters" x-cloak class="mt-2 space-y-1.5">
                                    @foreach ($availableDisasters as $disaster)
                                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-border px-2.5 py-2 text-xs">
                                            <input type="checkbox" value="{{ $disaster->id }}" x-model="disasterIds" class="mt-0.5 h-3.5 w-3.5 shrink-0 accent-[var(--primary)]">
                                            <span>{{ $disaster->name }}</span>
                                        </label>
                                    @endforeach
                                    <div class="flex gap-2 pt-1">
                                        <button type="button" @click="editingDisasters = false"
                                                class="flex-1 rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                            Cancel
                                        </button>
                                        <button type="button" @click="confirmingDisasters = true"
                                                class="flex-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110">
                                            Review
                                        </button>
                                    </div>
                                </div>

                                <div x-show="confirmingDisasters" x-cloak class="fixed inset-0 z-[95] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                                    <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                                        <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Disaster Event Changes</h3>
                                        <p class="mb-4 text-sm text-muted-foreground">This report will now be linked to:</p>
                                        <ul class="mb-4 space-y-1 text-sm text-foreground" x-show="disasterIds.length">
                                            <template x-for="id in disasterIds" :key="id">
                                                <li class="rounded bg-muted px-3 py-1.5" x-text="disasterLabels[id]"></li>
                                            </template>
                                        </ul>
                                        <p class="mb-4 text-sm text-muted-foreground" x-show="! disasterIds.length">
                                            No disaster event - only appropriate if the cause was pests, plant disease, or heat.
                                        </p>
                                        <form method="POST" action="{{ route('mao.damage-reports.disasters.update', $damageReport) }}">
                                            @csrf @method('PUT')
                                            <template x-for="id in disasterIds" :key="id">
                                                <input type="hidden" name="disasters[]" :value="id">
                                            </template>
                                            <div class="flex gap-3">
                                                <button type="button" @click="confirmingDisasters = false"
                                                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                                    Back to Edit
                                                </button>
                                                <button type="submit"
                                                        class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                                                    Confirm Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Damaged crops --}}
                        <div class="border-t border-border pt-4">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Damaged Crops</p>
                                <p class="text-xs text-muted-foreground">Total: <span class="font-semibold text-foreground">{{ number_format($damageReport->crops->sum('damaged_area_hectares'), 2) }} ha</span></p>
                            </div>
                            @forelse ($damageReport->crops as $reportCrop)
                                <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                                    <p class="font-medium text-foreground">{{ $reportCrop->crop?->name ?? '-' }} &middot; {{ $reportCrop->damaged_area_hectares }} ha</p>
                                    <p class="text-muted-foreground">
                                        Est. {{ $reportCrop->estimated_damage_percent }}% &middot;
                                        {{ $reportCrop->production_cost !== null ? '₱' . number_format($reportCrop->production_cost, 2) : '-' }} cost &middot;
                                        {{ $reportCrop->farmgate_price_per_kg !== null ? '₱' . number_format($reportCrop->farmgate_price_per_kg, 2) . '/kg' : '-' }}
                                    </p>
                                </div>
                            @empty
                                <p class="text-xs text-muted-foreground">No crops recorded.</p>
                            @endforelse
                            @if ($damageReport->description)
                                <p class="mt-2 text-xs text-muted-foreground"><span class="font-medium text-foreground">Farmer's description:</span> {{ $damageReport->description }}</p>
                            @endif
                        </div>

                        {{-- Farmer photos --}}
                        <div class="border-t border-border pt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farmer's Damage Photos</p>
                            @if ($damageReport->photos->isNotEmpty())
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ($damageReport->photos as $photo)
                                        <a href="{{ $photo->url }}" target="_blank" class="block overflow-hidden rounded-lg border border-border">
                                            <img src="{{ $photo->url }}" alt="Damage photo" class="h-16 w-full object-cover transition hover:scale-105">
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-muted-foreground">The farmer did not attach any photos.</p>
                            @endif
                        </div>

                        {{-- Farmer-reported location --}}
                        <div class="border-t border-border pt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farmer-Reported Location</p>
                            <p class="text-xs text-foreground">{{ $damageReport->farm_location_description ?: 'No location description provided.' }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ $damageReport->reportedBarangay?->name ?? $farmer->barangay?->name ?? '-' }}</p>
                            @if ($damageReport->reported_latitude && $damageReport->reported_longitude)
                                <div id="reportedMap-{{ $damageReport->id }}" class="mt-2 h-48 w-full rounded-lg border border-border"
                                     data-lat="{{ $damageReport->reported_latitude }}" data-lng="{{ $damageReport->reported_longitude }}"
                                     data-label="{{ $farmer->full_name }} &middot; {{ $damageReport->reportedBarangay?->name ?? $farmer->barangay?->name }}"></div>
                            @endif
                        </div>
                    </div>

                    {{-- This page shows the farmer's own submitted report only (section
                         23: "walang info na na-validate na") - a technician's assessment,
                         once one exists, belongs in Validation Monitoring's completed-
                         inspection history instead. What this page still owns is getting
                         a report TO a technician in the first place. --}}
                    @if (! in_array($damageReport->status, ['under_verification', 'verified', 'approved'], true) && $technicians->isNotEmpty())
                        <div class="mt-5 border-t border-border pt-4">
                            <button type="button"
                                    @click="assigning = {
                                        id: {{ $damageReport->id }},
                                        code: @js($damageReport->full_reference),
                                        farmer: @js($farmer->full_name ?? 'Unknown'),
                                        current: {{ $damageReport->assigned_technician_id ?: 'null' }}
                                    }"
                                    class="w-full rounded-lg bg-green-800 px-3 py-2 text-sm font-semibold text-white hover:bg-green-900">
                                {{ $damageReport->assignedTechnician ? 'Reassign Technician' : 'Assign Technician' }}
                            </button>
                        </div>
                    @elseif ($damageReport->assignedTechnician)
                        <div class="mt-5 border-t border-border pt-4 text-xs text-muted-foreground">
                            Assigned to <span class="font-bold text-foreground">{{ $damageReport->assignedTechnician->full_name ?: $damageReport->assignedTechnician->username }}</span>.
                            Inspection progress and results are in Validation Monitoring once complete.
                        </div>
                    @endif

                    {{-- MAO decision --}}
                    @if ($canDecide)
                        <div class="mt-5 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row">
                            <button type="button" @click="decision = 'approved'"
                                    class="w-full rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:brightness-110">
                                Approve
                            </button>
                            <button type="button" @click="decision = 'flagged'"
                                    class="w-full rounded-lg border border-orange-300 px-3 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-50">
                                Flag
                            </button>
                            <button type="button" @click="decision = 'rejected'"
                                    class="w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Reject
                            </button>
                        </div>

                        <div x-show="decision" x-cloak class="fixed inset-0 z-[95] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                            <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                                <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm <span x-text="decision"></span></h3>
                                <p class="mb-4 text-sm text-muted-foreground">
                                    You are about to mark report <strong>{{ $damageReport->full_reference }}</strong> for
                                    <strong>{{ $farmer->full_name }}</strong> as <span class="font-semibold" x-text="decision"></span>.
                                    Please review the inspection above before continuing.
                                </p>
                                <form method="POST" action="{{ route('mao.damage-reports.decide', $damageReport) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="decision" :value="decision">
                                    <label class="mb-1.5 block text-sm font-medium text-foreground">Remarks (optional)</label>
                                    <textarea name="remarks" rows="3" class="mb-4 w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring"></textarea>
                                    <div class="flex gap-3">
                                        <button type="button" @click="decision = null"
                                                class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                                            Confirm
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($damageReport->reported_latitude && $damageReport->reported_longitude)
                    @push('head')
                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
                    @endpush
                    @push('scripts')
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const el = document.getElementById('reportedMap-{{ $damageReport->id }}');
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
            @php
                    $historyRows = $history->map(fn ($h) => [
                        'label'   => $h->full_reference,
                        'meta'    => $h->created_at?->format('M d, Y'),
                        'href'    => request()->fullUrlWithQuery(['selected' => $h->id]),
                        'status'  => $h->status,
                        'current' => $h->id === $selected->id,
                    ])->all();
                @endphp
                <x-record.farmer-history title="All reports from this farmer" :items="$historyRows" />
            @endif
        </x-ui.detail-panel>
    </div>

    {{-- Assign technician (Section 9: moved here from Validation Monitoring,
         which now only shows completed inspections). Same backend as before -
         posts to the existing mao.validations.assign route. --}}
    <div x-show="assigning" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Assign a technician</h3>
            <p class="mb-4 text-sm text-muted-foreground">
                Report <strong x-text="assigning?.code"></strong> for
                <strong x-text="assigning?.farmer"></strong>.
                The technician will see it on their dashboard and conduct the field inspection.
            </p>

            <form method="POST" :action="`{{ url('mao/validations') }}/${assigning?.id}/assign`">
                @csrf @method('PUT')

                <label class="mb-1.5 block text-sm font-medium text-foreground">Technician</label>
                <select name="assigned_technician_id" required
                        class="mb-5 w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Select technician</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}">
                            {{ $technician->full_name ?: $technician->username }}
                        </option>
                    @endforeach
                </select>

                <div class="flex gap-3">
                    <button type="button" @click="assigning = null"
                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                        Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
