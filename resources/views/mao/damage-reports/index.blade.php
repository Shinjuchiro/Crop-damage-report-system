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
        <x-ui.stat label="Not Assigned" :value="number_format($summary['unassigned'])" hint="Waiting for a technician"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2" />
        <x-ui.stat label="Assigned" :value="number_format($summary['assigned'])" hint="Given to a technician"
                   icon="M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15zM21 21l-5.2-5.2" />
        <x-ui.stat label="Without a Planting Match" :value="number_format($summary['no_planting'])"
                   hint="No matching or no planting record; the technician checks in the field"
                   icon="M12 9.5v4M12 17h.01M10.3 3.9L2.5 17.5A1.7 1.7 0 004 20h16a1.7 1.7 0 001.5-2.5L13.7 3.9a1.7 1.7 0 00-3 0z" />
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

                        {{-- Assignment and planting match, never the verification
                             result: that is Validation Monitoring's. --}}
                        <x-ui.select name="assignment" placeholder="All Assignments" onchange="this.form.submit()"
                                     :options="['unassigned' => 'Not Assigned', 'assigned' => 'Assigned']"
                                     :selected="request('assignment')" class="sm:w-44" />

                        <x-ui.select name="planting_match" placeholder="Any Crop Planting" onchange="this.form.submit()"
                                     :options="['matched' => 'Matching Record Found', 'mismatch' => 'No Matching Planting Record', 'none' => 'No Planting Record Found']"
                                     :selected="request('planting_match')" class="sm:w-60" />

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

                        @if (request()->hasAny(['search', 'association_id', 'disaster_id', 'assignment', 'planting_match']))
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
                                              :badge="$report->assignment_label"
                                              :fields="['Crop Planting' => \App\Support\PlantingMatch::label($report->planting_match)]" />
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
                                <th class="px-4 py-3 text-center">Assignment</th>
                                <th class="px-4 py-3 text-center">Crop Planting</th>
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
                                                         :colspan="5"
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
                                        <x-monitor.badge kind="assignment" :value="$report->is_assigned ? 'assigned' : 'unassigned'" />
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <x-monitor.badge kind="match" :value="$report->planting_match" />
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex justify-end gap-2">
                                            <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-14 text-center">
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
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" :wide="true" class="mt-5 lg:mt-0"
                            empty-text="Select a report from the list to view its full details.">
            @if ($selected)
                {{-- The same wide modal the association sees (monitor/_damage-modal).
                     The assign form below this block is the office's alone. --}}
                @include('monitor._damage-modal', [
                    'report'             => $selected,
                    'role'               => 'mao',
                    'technicians'        => $technicians,
                    'availableDisasters' => $availableDisasters,
                    'canEditDisasters'   => $canEditDisasters,
                ])

                @php
                    $historyRows = $history->map(fn ($h) => [
                        'label'   => $h->full_reference,
                        'meta'    => $h->created_at?->format('M d, Y') . ' · ' . ($h->assigned_technician_id ? 'Assigned' : 'Not Assigned'),
                        'href'    => request()->fullUrlWithQuery(['selected' => $h->id]),
                        'status'  => null,
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
                        x-effect="$el.value = assigning && assigning.current ? String(assigning.current) : ''"
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
