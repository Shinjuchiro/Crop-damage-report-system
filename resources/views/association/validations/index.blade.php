@extends('layouts.app')

@section('title', 'Validation Monitoring')

{{-- The layout's own heading is switched off: the Crop Management Report
     header below prints the heading above the tab strip, so the two tabs
     read as one report rather than as two pages with a title each. --}}
@section('hideHeading', true)

@section('content')

{{--
    Proposal section 61. Read only.

    The second of the association's two Crop Management Report tabs. The hub
    page of cards that used to sit above these lists is now the tab strip,
    and its route redirects to the first tab.

    The Status column is the point of this page. An officer should be able to
    answer "has anybody inspected Aling Rosa's farm yet" without phoning the
    office, and see at a glance which members are now eligible for assistance,
    which means a report a technician has actually verified.
--}}

@php
    /* The detail modal, same mechanism as every other list in the system:
       ?selected=<id> on this page's own URL. The controller only ever finds
       the record inside this association's own membership. */
    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);

    /* Grouped by member, or flat. See the note in the planting list - the
       controller does the ordering, this works out each member's count for
       the heading above their block (revision section 11).

       A running "Report 1, Report 2" used to sit in the first column as
       well. It was dropped: the reference already names the row, and two
       numbers side by side only invited the question of which one to
       quote. */
    $grouped  = ($view ?? 'grouped') !== 'flat';
    $rows     = $reports->getCollection();
    $perGroup = $rows->groupBy(fn ($row) => $row->farmer?->id)->map->count();
@endphp

<x-crop-management.header tab="validation" role="association">
    {{-- This page's own figures, rendered above the tabs. "Verified" is the
         one an officer is asked about most: it is the point at which the
         office has something it can act on. It is not a promise of
         assistance, which is a separate decision (section 10.5). --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Completed Inspection" :value="number_format($summary['completed'])"
                   hint="Technician has submitted"
                   icon="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4" />
        <x-ui.stat label="Approved" :value="number_format($summary['approved'])"
                   tone="primary" hint="Approved by the office"
                   icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-ui.stat label="Rejected" :value="number_format($summary['rejected'])"
                   hint="Rejected by the office"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM15 9l-6 6M9 9l6 6" />
    </div>
</x-crop-management.header>

<div class="space-y-4">

    <x-record.view-toggle :view="$grouped ? 'grouped' : 'flat'" />

    <x-ui.card :padded="false">
        {{-- Export Report sits beside the filters, outside the filter form
             itself: it is a menu of two links that build their own URL from
             the filters already in the query string. --}}
        <div class="flex flex-col gap-3 border-b border-border px-4 pt-4 sm:px-5 sm:pt-5 lg:flex-row lg:items-start lg:justify-between">
            <x-ui.filter-bar :fields="['q', 'status', 'planting_match']" :tight="true" class="lg:flex-1">
                <x-ui.input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Search member" class="sm:w-52" />

                <x-ui.select name="status" placeholder="All assessment statuses" onchange="this.form.submit()"
                             :options="['pending_review' => 'Pending Review', 'approved' => 'Approved', 'rejected' => 'Rejected']"
                             :selected="$filters['status'] ?? null" class="sm:w-56" />

                <x-ui.select name="planting_match" placeholder="Any crop planting" onchange="this.form.submit()"
                             :options="['matched' => 'Matching Record Found', 'mismatch' => 'No Matching Planting Record', 'none' => 'No Planting Record Found']"
                             :selected="$filters['planting_match'] ?? null" class="sm:w-60" />
            </x-ui.filter-bar>

        </div>

        @if ($reports->isEmpty())
            <x-ui.empty title="No completed inspections found"
                        icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"
                        message="Inspections appear here once a technician has finished one for a member's report.">
                <x-ui.button variant="outline" :href="route('association.validations.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="space-y-3 p-4 lg:hidden">
                @php $phoneLast = null; @endphp

                @foreach ($reports as $report)
                    @if ($grouped && $report->farmer?->id !== $phoneLast)
                        @php $phoneLast = $report->farmer?->id; @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 pt-2 first:pt-0">
                            <span class="text-sm font-bold">{{ $report->farmer?->full_name ?? 'Unknown member' }}</span>
                            <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                                {{ $perGroup[$report->farmer?->id] ?? 1 }}
                                {{ \Illuminate\Support\Str::plural('report', $perGroup[$report->farmer?->id] ?? 1) }}
                            </span>
                        </li>
                    @endif

                    {{-- The card carries the same four facts the table
                         does, and the whole card is the tap that opens the
                         record: the cause, the barangay, the date and the
                         crops are in View now. --}}
                    <li class="{{ $grouped ? 'ml-3' : '' }}">
                        <x-ui.record-card :href="$viewUrl($report->id)"
                                          :eyebrow="$grouped ? $report->reference : $report->full_reference"
                                          :title="$report->farmer?->full_name ?? 'Unknown member'"
                                          :badge="$report->review_state === 'approved' ? 'Approved' : ($report->review_state === 'rejected' ? 'Rejected' : 'Pending Review')"
                                          :fields="['Crop Planting' => \App\Support\PlantingMatch::label($report->planting_match)]" />
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden lg:block">
                <x-ui.table>
                    <x-slot:head>
                        {{-- FOUR COLUMNS (revision sections 3.1 and 9.5)

                             Cause, the verifying technician, the filed date,
                             the barangay and the photo count used to sit
                             here. Every one of them is in View, along with
                             the crops, the two damage figures and the
                             photographs themselves. --}}
                        <tr>
                            <th>Report ID</th>
                            <th>Member</th>
                            <th class="text-center">Crop Planting</th>
                            <th class="text-center">Assessment Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @php $lastFarmer = null; @endphp

                    @foreach ($reports as $report)
                        @if ($grouped && $report->farmer?->id !== $lastFarmer)
                            @php $lastFarmer = $report->farmer?->id; @endphp
                            <x-record.group-head :farmer="$report->farmer"
                                                 :count="$perGroup[$report->farmer?->id] ?? 1"
                                                 :total="$farmerTotals[$report->farmer?->id] ?? null"
                                                 noun="inspection"
                                                 :colspan="5"
                                                 :href="$report->farmer ? route('association.members.show', $report->farmer) : null" />
                        @endif

                        <tr>
                            <td class="whitespace-nowrap font-medium">
                                <span class="{{ $grouped ? 'pl-3' : '' }}">{{ $grouped ? $report->reference : $report->full_reference }}</span>
                            </td>

                            {{-- The name in both views, muted under a group
                                 heading that already carries it in bold. --}}
                            <td class="{{ $grouped ? 'text-muted-foreground' : 'font-medium' }}">
                                {{ $report->farmer?->full_name ?? 'Unknown' }}
                            </td>

                            <td class="text-center">
                                <x-monitor.badge kind="match" :value="$report->planting_match" />
                            </td>

                            <td class="text-center">
                                <x-monitor.badge kind="validation" :value="$report->review_state" />
                            </td>

                            <td class="text-right">
                                <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>
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

    {{-- DETAIL
         The member's submission and the technician's findings, side by side
         and never merged (section 9.4). Only ever rendered for a report
         belonging to this association's own members. --}}
    <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" :wide="true"
                       empty-text="Select a completed inspection from the list to view its comparison and summary.">
        @if ($selected)
            @include('monitor._validation-modal', ['report' => $selected, 'role' => 'association'])
        @endif
    </x-ui.detail-panel>
</div>
@endsection
