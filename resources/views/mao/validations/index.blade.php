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
    {{-- Completed Inspection counts reports whose technician has submitted
         the inspection; Approved and Rejected are the office's own review
         decisions on those (App\Support\MonitorStats::validation()). --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.stat label="Completed Inspection" :value="number_format($summary['completed'])"
                   hint="Technician has submitted"
                   icon="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4" />
        <x-ui.stat label="Approved" :value="number_format($summary['approved'])"
                   hint="Assessments approved by MAO"
                   icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-ui.stat label="Rejected" :value="number_format($summary['rejected'])"
                   hint="Assessments rejected by MAO"
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

                // Which farmer's block starts open: the one whose inspection
                // is open in the modal, or the only one when a search narrows
                // the list to a single farmer. Otherwise every block is folded.
                $openId = $grouped
                    ? ($selected?->farmer_id ?? ($reports->total() === 1 ? $rows->first()?->farmer_id : null))
                    : null;
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

                        <x-ui.select name="status" placeholder="All Results" onchange="this.form.submit()"
                                     :options="['pending_review' => 'Pending Review', 'approved' => 'Approved', 'rejected' => 'Rejected']"
                                     :selected="request('status')" class="sm:w-44" />

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

                        @if (request()->hasAny(['search', 'association_id', 'barangay_id', 'status']))
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
                <ul class="space-y-3 lg:hidden" @if ($grouped) x-data="{ open: {{ $openId ?? 'null' }} }" @endif>
                    @php $phoneLast = null; @endphp

                    @forelse ($reports as $report)
                        @if ($grouped && $report->farmer?->id !== $phoneLast)
                            @php $phoneLast = $report->farmer?->id; @endphp
                            {{-- One row per farmer; tap to open their inspections. --}}
                            <li class="flex cursor-pointer flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/60 px-3 py-2.5"
                                @click="open = open === {{ (int) $report->farmer?->id }} ? null : {{ (int) $report->farmer?->id }}"
                                role="button" tabindex="0">
                                <span class="flex items-center gap-2 text-sm font-bold">
                                    {{ $report->farmer?->full_name ?? 'Unknown' }}
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                                        {{ $perGroup[$report->farmer?->id] ?? 1 }}
                                        {{ \Illuminate\Support\Str::plural('inspection', $perGroup[$report->farmer?->id] ?? 1) }}
                                    </span>
                                    <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                                         :class="open === {{ (int) $report->farmer?->id }} ? 'rotate-90' : ''"
                                         fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                         stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                                </span>
                            </li>
                        @endif

                        <li class="{{ $grouped ? 'ml-3' : '' }}"
                            @if ($grouped) x-show="open === {{ (int) $report->farmer?->id }}" x-cloak @endif>
                            <x-ui.record-card :href="$viewUrl($report->id)"
                                              :eyebrow="$grouped ? null : $report->full_reference"
                                              :title="$grouped
                                                  ? $report->reference
                                                  : ($report->farmer?->full_name ?? 'Unknown')"
                                              :badge="$report->review_state === 'approved' ? 'Approved' : ($report->review_state === 'rejected' ? 'Rejected' : 'Pending Review')"
                                              :fields="$grouped ? [] : ['Crop Planting' => \App\Support\PlantingMatch::label($report->planting_match)]" />
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
                @if ($grouped)
                    {{-- GROUPED (Oct 2026): one folded row per farmer, FARMER ID |
                         FARMER | ASSOCIATION | NUMBER OF RECORDS. A farmer's inspections, with
                         their own heading, are only on the page while that farmer
                         is open. The planting comparison is in View. --}}
                    <div class="hidden overflow-x-auto rounded-xl border border-border lg:block">
                        <table class="w-full text-left">
                            <thead class="bg-muted/70">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <th class="px-4 py-3">Farmer ID</th>
                                    <th class="px-4 py-3">Farmer</th>
                                    <th class="px-4 py-3">Association</th>
                                    <th class="px-4 py-3 text-center">Number of Records</th>
                                <th class="w-12 px-4 py-3"><span class="sr-only">Open</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-sm" x-data="{ open: {{ $openId ?? 'null' }} }">
                                @forelse ($rows->groupBy(fn ($row) => (int) $row->farmer_id) as $farmerId => $group)
                                    @php $farmer = $group->first()->farmer; @endphp
                                    <x-record.farmer-block seen="validation" :farmer="$farmer"
                                                           :count="$group->count()"
                                                           :group-id="$farmerId"
                                                           :href="$farmer ? route('mao.farmers.show', $farmer) : null"
                                                           :headers="[['Report ID'], ['Assessment Status', 'text-center'], ['Action', 'text-center']]">
                                        @foreach ($group as $report)
                                            <tr class="hover:bg-muted/60 {{ $selected?->id === $report->id ? 'bg-muted/60' : '' }}">
                                                <td class="px-4 py-4 text-foreground">{{ $report->reference }}</td>
                                                <td class="px-4 py-4 text-center">
                                                    <x-monitor.badge kind="validation" :value="$report->review_state" />
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center justify-center gap-2">
                                                        <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </x-record.farmer-block>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-14 text-center">
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
                @else
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
                                <th class="px-4 py-3 text-center">Crop Planting</th>
                                <th class="px-4 py-3 text-center">Assessment Status</th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">

                            @forelse ($reports as $report)
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $report->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-4 py-4 text-foreground">{{ $report->full_reference }}</td>
                                    <td class="px-4 py-4 font-bold text-foreground">
                                        {{ $report->farmer?->full_name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <x-monitor.badge kind="match" :value="$report->planting_match" />
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <x-monitor.badge kind="validation" :value="$report->review_state" />
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-14 text-center">
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
                @endif

                {{-- "Showing 1 to 3 of 3 entries" on the left, the pages on
                     the right, as the design draws it. firstItem() is null on
                     an empty page, which is why the count is written rather
                     than assumed. --}}
                <div class="mt-6 flex flex-col gap-3 border-t border-border pt-5
                            sm:flex-row sm:items-center sm:justify-between">
                    @php
                        [$from, $to, $total] = $grouped
                            ? \App\Support\FarmerGroups::range($reports)
                            : [$reports->firstItem(), $reports->lastItem(), $reports->total()];
                        $noun = $grouped ? 'farmer' : 'entry';
                    @endphp
                    <p class="text-sm text-muted-foreground">
                        @if ($total > 0)
                            Showing {{ number_format($from) }}
                            to {{ number_format($to) }}
                            of {{ number_format($total) }}
                            {{ \Illuminate\Support\Str::plural($noun, $total) }}
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
                {{-- The same wide modal the association sees (monitor/_validation-modal).
                     Approve and Reject inside it are the office's alone. --}}
                @include('monitor._validation-modal', ['report' => $selected, 'role' => 'mao'])

                @php
                    $historyRows = $history->map(fn ($h) => [
                        'label'   => $h->full_reference,
                        'meta'    => $h->created_at?->format('M d, Y') . ' · ' . ($h->review_state === 'approved' ? 'Approved' : ($h->review_state === 'rejected' ? 'Rejected' : 'Pending Review')),
                        'href'    => request()->fullUrlWithQuery(['selected' => $h->id]),
                        'status'  => null,
                        'current' => $h->id === $selected->id,
                    ])->all();
                @endphp
                <x-record.farmer-history title="All inspections for this farmer" :items="$historyRows" />
            @endif
        </x-ui.detail-panel>
    </div>
</div>

@endsection
