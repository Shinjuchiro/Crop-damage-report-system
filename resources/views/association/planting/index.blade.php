@extends('layouts.app')

@section('title', 'Crop Planted')

{{-- The layout's own heading is switched off: the Crop Management Report
     header below prints the heading above the tab strip, so the two tabs
     read as one report rather than as two pages with a title each. --}}
@section('hideHeading', true)

@section('content')

{{--
    Proposal section 60. Read only.

    The first of the association's two Crop Management Report tabs. There
    used to be a hub page of two cards above these lists, and a back link
    here to return to it; the cards are the tab strip now and the hub route
    redirects here (Association\CropManagementController).

    This list is also how an officer sees who is at risk of going inactive:
    planting is the only thing that counts as a qualifying activity
    (section 21), so a member who never appears here is heading for Inactive
    once they pass the inactivity limit (Farmer::INACTIVITY_MONTHS).
--}}

@php
    /* The detail modal, same mechanism as every other list in the system:
       ?selected=<id> on this page's own URL, so an open record survives a
       refresh and the Back button closes it. The controller only ever finds
       it inside this association's own membership. */
    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);

    /* GROUPED BY MEMBER, OR FLAT (revision sections 10 and 12)

       The controller does the ordering - grouped means ordered by member,
       so each member's records arrive together - and this works out how
       many records each member has on the page in hand, which is what the
       group heading counts.

       Counted on the page rather than in the database on purpose: the
       heading says how many of this member's records are in front of you,
       which is the honest claim when a list is paginated.

       A running "Record 1, Record 2" used to sit in the first column too.
       It is gone: the column shows the record's own number now, and two
       numbers side by side only invited the question of which one to
       quote. */
    $grouped  = ($view ?? 'grouped') !== 'flat';
    $rows     = $records->getCollection();
    $perGroup = $rows->groupBy(fn ($row) => $row->farmer?->id)->map->count();

    // Which member's block starts open: the one whose record is open in the
    // modal, or the only one when a search narrows the list to one member.
    $openId = $grouped
        ? ($selected?->farmer_id ?? ($records->total() === 1 ? $rows->first()?->farmer_id : null))
        : null;
@endphp

<x-crop-management.header tab="planting" role="association">
    {{-- This page's own figures, rendered above the tabs. These are the
         numbers the hub's Crop Planted card used to carry, with the two an
         officer is most often asked for beside them. --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Total Crop Planted" :value="number_format($summary['total'])"
                   hint="Crop Planted reports from your members"
                   icon="M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14zM4 21h16" />
        {{-- Hectares, summed once per planted crop. A field planted twice in a
             year is two plantings, so it counts twice; the hint says so. --}}
        <x-ui.stat label="Total Area Planted" :value="number_format($summary['area'], 2)" suffix="ha"
                   hint="Across all reports; a field planted twice counts twice"
                   icon="M4 8V5a1 1 0 011-1h3M20 8V5a1 1 0 00-1-1h-3M4 16v3a1 1 0 001 1h3M20 16v3a1 1 0 01-1 1h-3" />
    </div>
</x-crop-management.header>

<div class="space-y-4">

    <x-record.view-toggle :view="$grouped ? 'grouped' : 'flat'" />

    <x-ui.card :padded="false">
        {{-- The filter row, with Export Report beside it. The button sits
             OUTSIDE the filter form on purpose: it is a menu of two links,
             and a link inside a GET form still works but would carry every
             filter field along as a duplicate of what it already builds
             into its own URL. --}}
        <div class="flex flex-col gap-3 border-b border-border px-4 pt-4 sm:px-5 sm:pt-5 lg:flex-row lg:items-start lg:justify-between">
            <x-ui.filter-bar :fields="['q', 'month', 'year']" :tight="true" class="lg:flex-1">
                <x-ui.input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Search member" class="sm:w-52" />

                <x-ui.select name="month" placeholder="All months" onchange="this.form.submit()"
                             :options="collect(range(1, 12))->mapWithKeys(
                                 fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')]
                             )"
                             :selected="$filters['month'] ?? null" class="sm:w-40" />

                <x-ui.select name="year" placeholder="All years" onchange="this.form.submit()"
                             :options="collect(range((int) date('Y'), (int) date('Y') - 5))->mapWithKeys(fn ($y) => [$y => $y])"
                             :selected="$filters['year'] ?? null" class="sm:w-32" />
            </x-ui.filter-bar>

            <x-record.export-button class="pb-4 lg:pb-0" />
        </div>

        @if ($records->isEmpty())
            <x-ui.empty title="No planting activity found"
                        icon="M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z"
                        message="Members record what they plant from their own dashboard. Nothing has been recorded for this period yet.">
                <x-ui.button variant="outline" :href="route('association.planting.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="space-y-3 p-4 lg:hidden" @if ($grouped) x-data="{ open: {{ $openId ?? 'null' }} }" @endif>
                @php $phoneLast = null; @endphp

                @foreach ($records as $record)
                    @if ($grouped && $record->farmer?->id !== $phoneLast)
                        @php $phoneLast = $record->farmer?->id; @endphp
                        {{-- One row per member; tap to open their records. --}}
                        <li class="flex cursor-pointer flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/60 px-3 py-2.5"
                            @click="open = open === {{ (int) $record->farmer?->id }} ? null : {{ (int) $record->farmer?->id }}"
                            role="button" tabindex="0">
                            <span class="flex items-center gap-2 text-sm font-bold">
                                {{ $record->farmer?->full_name ?? 'Unknown member' }}
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                                    {{ $perGroup[$record->farmer?->id] ?? 1 }}
                                    {{ \Illuminate\Support\Str::plural('record', $perGroup[$record->farmer?->id] ?? 1) }}
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                                     :class="open === {{ (int) $record->farmer?->id }} ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                     stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                            </span>
                        </li>
                    @endif

                    {{-- The card carries the same four facts the table
                         does, and the whole card is the tap that opens the
                         record: the crops, the area, the barangay and the
                         dates are in View now. --}}
                    <li class="{{ $grouped ? 'ml-3' : '' }}"
                        @if ($grouped) x-show="open === {{ (int) $record->farmer?->id }}" x-cloak @endif>
                        <x-ui.record-card :href="$viewUrl($record->id)"
                                          :eyebrow="'REC-' . str_pad($record->id, 4, '0', STR_PAD_LEFT)"
                                          :title="$record->farmer?->full_name ?? 'Unknown member'"
                                          :status="$record->farmer?->activity_status" />
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            @if ($grouped)
                {{-- GROUPED (Oct 2026): one folded row per farmer, FARMER ID |
                     FARMER | NUMBER OF RECORDS. Their records, with their own
                     heading, are only on the page while that farmer is open.
                     No Association column here: every member of this screen
                     is in the same association. --}}
                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left">
                        <thead class="bg-muted/70">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <th class="px-4 py-3">Farmer ID</th>
                                <th class="px-4 py-3">Farmer</th>
                                <th class="px-4 py-3 text-center">Number of Records</th>
                            <th class="w-12 px-4 py-3"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm" x-data="{ open: {{ $openId ?? 'null' }} }">
                            @foreach ($rows->groupBy(fn ($r) => (int) $r->farmer_id) as $farmerId => $group)
                                @php $farmer = $group->first()->farmer; @endphp
                                <x-record.farmer-block seen="planting" :farmer="$farmer"
                                                       :count="$group->count()"
                                                       :group-id="$farmerId"
                                                       :association="false"
                                                       :headers="[['Record No.'], ['Status', 'text-center'], ['Action', 'text-right']]">
                                    @foreach ($group as $record)
                                        <tr class="hover:bg-muted/60 {{ $selected?->id === $record->id ? 'bg-muted/60' : '' }}">
                                            <td class="whitespace-nowrap px-4 py-4 font-medium">REC-{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }}</td>
                                            <td class="px-4 py-4 text-center"><x-ui.status :value="$record->farmer?->activity_status" /></td>
                                            <td class="px-4 py-4 text-right">
                                                <x-ui.button :href="$viewUrl($record->id)" variant="view" size="sm">View</x-ui.button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </x-record.farmer-block>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
            <div class="hidden lg:block">
                <x-ui.table>
                    <x-slot:head>
                        {{-- FOUR COLUMNS (revision section 3.1)

                             Barangay, the earliest planting date and the
                             submitted date used to sit here. They are in
                             View now, with the crops, the areas and the
                             photographs that were never on this list at
                             all. --}}
                        <tr>
                            <th>Record No.</th>
                            <th>Member</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($records as $record)
                        <tr>
                            <td class="whitespace-nowrap font-medium">
                                REC-{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }}
                            </td>

                            <td class="font-medium">
                                {{ $record->farmer?->full_name ?? 'Unknown' }}
                            </td>

                            {{-- The member's own Active/Inactive standing,
                                 which is the status this row has: a planting
                                 record has no state of its own, and this is
                                 the thing a planting keeps alive (section
                                 21). --}}
                            <td>
                                <x-ui.status :value="$record->farmer?->activity_status" />
                            </td>

                            <td class="text-right">
                                <x-ui.button :href="$viewUrl($record->id)" variant="view" size="sm">View</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
            @endif
        @endif

        @if ($records->hasPages())
            <x-slot:footer>{{ $records->links() }}</x-slot:footer>
        @endif
    </x-ui.card>

    {{-- DETAIL
         Everything the four columns no longer carry. Only ever rendered for
         a record belonging to this association's own members, because that
         is the only thing the controller will find. --}}
    <x-ui.detail-panel :selected="$selected" :back-url="$backUrl"
                       empty-text="Select a record from the list to view its full details.">
        @if ($selected)
            @include('monitor._planting-modal', ['record' => $selected, 'relatedDamage' => $relatedDamage, 'role' => 'association'])
        @endif
    </x-ui.detail-panel>
</div>
@endsection
