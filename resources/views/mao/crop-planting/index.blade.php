@extends('layouts.app')

@section('title', 'Crop Planting Monitoring')
@section('hideHeading', true)

@php
    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')

<x-crop-management.header tab="planting">
    {{-- This page's own figures, rendered above the tabs. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Submitted This Month" :value="number_format($summary['this_month'])"
                   icon="M8 2v3M16 2v3M3.5 9h17M5 5h14a1.5 1.5 0 011.5 1.5v13A1.5 1.5 0 0119 21H5a1.5 1.5 0 01-1.5-1.5v-13A1.5 1.5 0 015 5z" />
        <x-ui.stat label="Farmers Reporting" :value="number_format($summary['farmers'])"
                     hint="Farmers with at least one record"
                     icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9" />
        {{-- "Area Planted", with the hint, not "Total Area Planted" (Oct 2026).

             The figure sums area_hectares across every crop row of every
             planting record ever filed. A farmer who plants the same half
             hectare in the dry season and again in the wet season adds a
             whole hectare to it, which is a true statement about planting
             and a false one about farmland.

             Read as land, this number tells the office Tanza has more
             hectares under cultivation than it does, and the longer the
             system runs the further out it goes. Revision section 14:
             "Avoid adding repeated historical farm areas in ways that
             incorrectly represent unique farmland", and final rule 16: do
             not misrepresent affected areas.

             The sum is not wrong and is not changed: cumulative planted
             area is a reasonable thing for an agricultural office to track.
             What was wrong was a label that read as acreage. The hint says
             which of the two this is. --}}
        <x-ui.stat label="Area Planted" :value="number_format($summary['area_planted'], 2)" suffix="ha"
                   hint="Across all records; a field planted twice counts twice"
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
                <ul class="space-y-3 lg:hidden">
                    @php $phoneLast = null; @endphp

                    @forelse ($plantings as $planting)
                        @php $cardFarmer = $planting->plantingRecord?->farmer; @endphp

                        @if ($grouped && $cardFarmer?->id !== $phoneLast)
                            @php $phoneLast = $cardFarmer?->id; @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2 pt-2 first:pt-0">
                                <span class="text-sm font-bold">{{ $cardFarmer?->full_name ?? 'Unknown' }}</span>
                                <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                                    {{ $perGroup[$cardFarmer?->id] ?? 1 }}
                                    {{ \Illuminate\Support\Str::plural('record', $perGroup[$cardFarmer?->id] ?? 1) }}
                                </span>
                            </li>
                        @endif

                        <li class="{{ $grouped ? 'ml-3' : '' }}">
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
                            @php $lastFarmer = null; @endphp

                            @forelse ($plantings as $planting)
                                @php $farmer = $planting->plantingRecord?->farmer; @endphp

                                @if ($grouped && $farmer?->id !== $lastFarmer)
                                    @php $lastFarmer = $farmer?->id; @endphp
                                    <x-record.group-head :farmer="$farmer"
                                                         :count="$perGroup[$farmer?->id] ?? 1"
                                                         :total="$farmerTotals[$farmer?->id] ?? null"
                                                         noun="planting record"
                                                         :colspan="4"
                                                         :href="$farmer ? route('mao.farmers.show', $farmer) : null" />
                                @endif

                                <tr class="hover:bg-muted/60 {{ $selected?->id === $planting->crop_planting_record_id ? 'bg-muted/60' : '' }}">
                                    <td class="px-4 py-4 text-foreground">
                                        @if ($grouped)
                                            <span class="pl-3 text-muted-foreground">Record {{ $seq[$planting->id] }}</span>
                                        @else
                                            {{ $farmer ? 'FARM-' . str_pad($farmer->id, 3, '0', STR_PAD_LEFT) : '-' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 font-bold text-foreground">
                                        @if ($grouped)
                                            <span class="font-medium text-muted-foreground">
                                                {{ $planting->crop_specify ?: $planting->crop?->name ?? '-' }}
                                            </span>
                                        @else
                                            {{ $farmer?->full_name ?? 'Unknown' }}
                                        @endif
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

                {{-- "Showing 1 to 3 of 3 entries" on the left, the pages on
                     the right, as the design draws it. firstItem() is null on
                     an empty page, which is why the count is written rather
                     than assumed. --}}
                <div class="mt-6 flex flex-col gap-3 border-t border-border pt-5
                            sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">
                        @if ($plantings->total() > 0)
                            Showing {{ number_format($plantings->firstItem()) }}
                            to {{ number_format($plantings->lastItem()) }}
                            of {{ number_format($plantings->total()) }}
                            {{ \Illuminate\Support\Str::plural('entry', $plantings->total()) }}
                        @else
                            No entries
                        @endif
                    </p>

                    <div>{{ $plantings->links() }}</div>
                </div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a record from the list to view its full details.">
            @if ($selected)
                @php $plantingRecord = $selected; $farmer = $plantingRecord->farmer; @endphp

                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Planting Record &middot; Submitted {{ $plantingRecord->date_submitted?->format('M d, Y') }}
                        </p>
                        <h3 class="mt-0.5 text-lg font-bold text-foreground">
                            <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">
                                {{ $farmer->full_name }}
                            </a>
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ $farmer->association?->name ?? 'No association' }} &middot; {{ $farmer->barangay?->name ?? 'No barangay' }}
                        </p>

                        <x-ui.open-full-page :href="route('mao.crop-planting.show', $plantingRecord)" />
                    </div>
                </div>

                <div class="border-t border-border pt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crops Planted</p>
                        <p class="text-xs text-muted-foreground">
                            Total: <span class="font-semibold text-foreground">{{ number_format($plantingRecord->crops->sum('area_hectares'), 2) }} ha</span>
                        </p>
                    </div>
                    @foreach ($plantingRecord->crops as $recordCrop)
                        <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                            <p class="font-medium text-foreground">
                                {{ $recordCrop->crop?->name ?? '-' }}
                                @if ($recordCrop->crop_specify) ({{ $recordCrop->crop_specify }}) @endif
                            </p>
                            <p class="text-muted-foreground">
                                Planted {{ $recordCrop->date_planted?->format('M d, Y') }} &middot; {{ $recordCrop->area_hectares }} ha
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-border pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Photos</p>
                    @if ($plantingRecord->photos->isNotEmpty())
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($plantingRecord->photos as $photo)
                                <a href="{{ $photo->url }}" target="_blank"
                                   class="block overflow-hidden rounded-lg border border-border">
                                    <img src="{{ $photo->url }}" alt="Planting photo"
                                         class="h-16 w-full object-cover transition hover:scale-105">
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-muted-foreground">No photos were attached to this record.</p>
                    @endif
                </div>

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
