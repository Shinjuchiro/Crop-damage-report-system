@extends('layouts.app')

@section('title', 'Crop Planting Monitoring')
@section('hideHeading', true)

@php
    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')

@php $flash = \App\Support\MonitorStats::planting(); @endphp
<x-crop-management.header tab="planting">
    {{-- This tab's figures (Oct 2026), live queries from
         App\Support\MonitorStats, the same definitions the Association's
         own tab uses for its members. Total Crop Planted counts planting
         reports, not the crops inside them; Total Area Planted adds each
         planted crop row once. Archived and deleted records are left out,
         exactly like the list below. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-ui.stat label="Total Crop Planted" :value="number_format($flash['total'])"
                   hint="Crop Planted reports recorded"
                   icon="M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14zM4 21h16" />
        <x-ui.stat label="Total Area Planted" :value="number_format($flash['area'], 2)" suffix="ha"
                   hint="Hectares across all planted crops"
                   icon="M4 8V5a1 1 0 011-1h3M20 8V5a1 1 0 00-1-1h-3M4 16v3a1 1 0 001 1h3M20 16v3a1 1 0 01-1 1h-3" />
    </div>
</x-crop-management.header>
<div x-data="{ archiving: null }">


    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

        {{-- LIST --}}
        <div>
            @php
                /* GROUPED BY FARMER, OR FLAT (revision section 10)

                   The controller does the ordering - grouped means a
                   farmer's crops arrive together - and this works out, for
                   the page in hand, how many each farmer has and where each
                   one sits inside their block. That feeds the group
                   heading's count and the first column's "Record 1, Record
                   2", which is how the revision's examples read.

                   Counted on the page, not in the database: the heading
                   says how many are in front of you, which is the honest
                   claim when a list is paginated. */
                $grouped  = ($view ?? 'grouped') !== 'flat';
                $rows     = $plantings->getCollection();
                $perGroup = $rows->groupBy(fn ($row) => $row->plantingRecord?->farmer?->id)->map->count();

                // Which farmer's block starts open: the one whose planting is
                // open in the modal, or the only one when a search narrows
                // the list to a single farmer. Otherwise every block is folded.
                $openId = $grouped
                    ? ($selected?->farmer_id ?? ($plantings->total() === 1 ? $rows->first()?->plantingRecord?->farmer_id : null))
                    : null;

                $seq = [];
                $running = [];
                foreach ($rows as $row) {
                    $key = $row->plantingRecord?->farmer?->id;
                    $running[$key] = ($running[$key] ?? 0) + 1;
                    $seq[$row->id] = $running[$key];
                }
            @endphp

            <x-record.view-toggle :view="$grouped ? 'grouped' : 'flat'" class="mb-4" />

            <x-ui.card title="Crop Planting Monitoring">
                <x-slot:actions>
                    <a href="{{ route('mao.archive.index', ['type' => 'crop_planting']) }}"
                       class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                        View archived
                    </a>
                </x-slot:actions>

                {{-- Filters, Export Report and the search box, on one row.

                     Export sits beside the filters on purpose: it exports
                     exactly what they are showing, so standing next to them
                     says so without a word of explanation. It is outside the
                     <form> because it is a link, not a submission - putting a
                     link inside a GET form is harmless but misleading to
                     anyone reading this later.

                     No visible Search button: Enter in the field, or choosing
                     a dropdown option, submits the form. --}}
                <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">

                    <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                     :options="$associations->pluck('name', 'id')" :selected="request('association_id')" class="sm:w-52" />

                        <x-ui.select name="crop_id" placeholder="All Crops" onchange="this.form.submit()"
                                     :options="$crops->pluck('name', 'id')" :selected="request('crop_id')" class="sm:w-40" />

                        <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                                     :options="$barangays->pluck('name', 'id')" :selected="request('barangay_id')" class="sm:w-44" />

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

                        @if (request()->hasAny(['search', 'association_id', 'crop_id', 'barangay_id']))
                            <a href="{{ route('mao.crop-planting.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                        @endif
                    </form>

                    <x-record.export-button class="lg:order-first" />
                </div>

                {{-- Table --}}
                {{-- ---------- PHONE: one card per planting ----------
                     Archive stays on the table and the detail panel. --}}
                <ul class="space-y-3 lg:hidden" @if ($grouped) x-data="{ open: {{ $openId ?? 'null' }} }" @endif>
                    @php $phoneLast = null; @endphp

                    @forelse ($plantings as $planting)
                        @php $cardFarmer = $planting->plantingRecord?->farmer; @endphp

                        @if ($grouped && $cardFarmer?->id !== $phoneLast)
                            @php $phoneLast = $cardFarmer?->id; @endphp
                            {{-- One row per farmer; tap to open their plantings. --}}
                            <li class="flex cursor-pointer flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/60 px-3 py-2.5"
                                @click="open = open === {{ (int) $cardFarmer?->id }} ? null : {{ (int) $cardFarmer?->id }}"
                                role="button" tabindex="0">
                                <span class="flex items-center gap-2 text-sm font-bold">
                                    {{ $cardFarmer?->full_name ?? 'Unknown' }}
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                                        {{ $perGroup[$cardFarmer?->id] ?? 1 }}
                                        {{ \Illuminate\Support\Str::plural('record', $perGroup[$cardFarmer?->id] ?? 1) }}
                                    </span>
                                    <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                                         :class="open === {{ (int) $cardFarmer?->id }} ? 'rotate-90' : ''"
                                         fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                         stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                                </span>
                            </li>
                        @endif

                        <li class="{{ $grouped ? 'ml-3' : '' }}"
                            @if ($grouped) x-show="open === {{ (int) $cardFarmer?->id }}" x-cloak @endif>
                            <x-ui.record-card :href="$viewUrl($planting->crop_planting_record_id)"
                                              :eyebrow="$grouped
                                                  ? 'RECORD ' . $seq[$planting->id]
                                                  : ($cardFarmer ? 'FARM-' . str_pad($cardFarmer->id, 3, '0', STR_PAD_LEFT) : '-')"
                                              :title="$grouped
                                                  ? ($planting->crop_specify ?: $planting->crop?->name ?? 'Crop')
                                                  : ($cardFarmer?->full_name ?? 'Unknown')"
                                              :status="$cardFarmer?->activity_status" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No planting records match these filters</p>
                        </li>
                    @endforelse
                </ul>

                {{-- The heading row is a filled band rather than a heavy
                     rule, and its labels are small caps - the same treatment
                     x-ui.table gives every other table in the system, and
                     what the design draws. Written out here rather than
                     switching to that component because this table carries
                     group heading rows and a hover state keyed to the open
                     record, neither of which the shared component knows
                     about. --}}
                @if ($grouped)
                    {{-- GROUPED (Oct 2026): one folded row per farmer, FARMER ID |
                         FARMER | ASSOCIATION | NUMBER OF RECORDS. A farmer's planting records,
                         with their own heading, are only on the page while that
                         farmer is open. --}}
                    <div class="hidden overflow-hidden rounded-xl border border-border lg:block">
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
                                @forelse ($plantings->getCollection()->groupBy(fn ($row) => (int) $row->plantingRecord?->farmer_id) as $farmerId => $group)
                                    @php $farmer = $group->first()->plantingRecord?->farmer; @endphp
                                    <x-record.farmer-block :farmer="$farmer"
                                                           :count="$group->count()"
                                                           :group-id="$farmerId"
                                                           :href="$farmer ? route('mao.farmers.show', $farmer) : null"
                                                           :headers="[['Record'], ['Crop'], ['Status', 'text-center'], ['Action', 'text-right']]">
                                        @foreach ($group as $planting)
                                            <tr class="hover:bg-muted/60 {{ $selected?->id === $planting->crop_planting_record_id ? 'bg-muted/60' : '' }}">
                                                <td class="px-4 py-4 text-muted-foreground">Record {{ $seq[$planting->id] }}</td>
                                                <td class="px-4 py-4 font-medium text-foreground">
                                                    {{ $planting->crop_specify ?: $planting->crop?->name ?? '-' }}
                                                </td>
                                                <td class="px-4 py-4 text-center">
                                                    <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $farmer?->activity_status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                                        {{ $farmer?->activity_status === 'active' ? 'Active' : 'In-Active' }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex justify-end gap-2">
                                                        <x-ui.button :href="$viewUrl($planting->crop_planting_record_id)" variant="view" size="sm">View</x-ui.button>

                                                        <button type="button"
                                                                @click="archiving = { id: {{ $planting->crop_planting_record_id }}, name: @js($farmer?->full_name ?? 'this record') }"
                                                                class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
                                                            Archive
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </x-record.farmer-block>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-14 text-center">
                                            <p class="text-sm font-medium text-muted-foreground">No planting records yet</p>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Records appear here once farmers submit their crop planting activity.
                                            </p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                <div class="hidden overflow-hidden rounded-xl border border-border lg:block">
                    <table class="w-full text-left">
                        <thead class="bg-muted/70">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{-- FOUR COLUMNS (revision section 3.1)

                                     ID, Name, Status, Action, and nothing
                                     else. Association and Planting Date used
                                     to sit between them; both are in View,
                                     which is where the revision asks for
                                     anything the office does not need in
                                     order to pick a row out of a list. --}}
                                <th class="px-4 py-3">Farmer ID</th>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">

                            @forelse ($plantings as $planting)
                                @php $farmer = $planting->plantingRecord?->farmer; @endphp

                                <tr class="hover:bg-muted/60 {{ $selected?->id === $planting->crop_planting_record_id ? 'bg-muted/60' : '' }}">
                                    <td class="px-4 py-4 text-foreground">
                                        {{ $farmer ? 'FARM-' . str_pad($farmer->id, 3, '0', STR_PAD_LEFT) : '-' }}
                                    </td>
                                    <td class="px-4 py-4 font-bold text-foreground">
                                        {{ $farmer?->full_name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $farmer?->activity_status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                            {{ $farmer?->activity_status === 'active' ? 'Active' : 'In-Active' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex justify-end gap-2">
                                            <x-ui.button :href="$viewUrl($planting->crop_planting_record_id)" variant="view" size="sm">View</x-ui.button>

                                            <button type="button"
                                                    @click="archiving = { id: {{ $planting->crop_planting_record_id }}, name: @js($farmer?->full_name ?? 'this record') }"
                                                    class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
                                                Archive
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-14 text-center">
                                        <p class="text-sm font-medium text-muted-foreground">No planting records yet</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Records appear here once farmers submit their crop planting activity.
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
                            ? \App\Support\FarmerGroups::range($plantings)
                            : [$plantings->firstItem(), $plantings->lastItem(), $plantings->total()];
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

                    <div>{{ $plantings->links() }}</div>
                </div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" :wide="true" class="mt-5 lg:mt-0"
                            empty-text="Select a record from the list to view its full details.">
            @if ($selected)
                {{-- The same wide modal the association sees (monitor/_planting-modal),
                     so both roles read a planting report the same way. --}}
                @include('monitor._planting-modal', ['record' => $selected, 'relatedDamage' => $relatedDamage, 'role' => 'mao'])

                @php
                    $historyRows = $history->map(fn ($h) => [
                        'label'   => 'Planting record, ' . $h->date_submitted?->format('M d, Y'),
                        'meta'    => $h->crops->map(fn ($c) => $c->crop?->name)->filter()->implode(', '),
                        'href'    => request()->fullUrlWithQuery(['selected' => $h->id]),
                        'status'  => null,
                        'current' => $h->id === $selected->id,
                    ])->all();
                @endphp
                <x-record.farmer-history title="All planting records from this farmer" :items="$historyRows" />
            @endif
        </x-ui.detail-panel>
    </div>

    {{-- Archive confirmation --}}
    <div x-show="archiving" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Archive this planting record?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                <strong x-text="archiving?.name"></strong>'s record will be removed from active monitoring.
                Nothing is deleted, it does not change the farmer's Active/Inactive history, and it can be
                restored at any time from the Archive page.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/crop-planting') }}/${archiving?.id}/archive`" class="flex-1">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        Confirm Archive
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
