@extends('layouts.app')

@section('title', 'Crop Planting Monitoring')
@section('hideHeading', true)

@php
    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
<div x-data="{ archiving: null }">

    {{-- Summary --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Planting Submissions" :value="number_format($summary['records'])" />
        <x-ui.stat label="Submitted This Month" :value="number_format($summary['this_month'])" />
        <x-ui.stat label="Farmers Reporting" :value="number_format($summary['farmers'])"
                     hint="Farmers with at least one record" />
        <x-ui.stat label="Total Area Planted" :value="number_format($summary['area_planted'], 2)" suffix="ha" />
    </div>

    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a row's View is followed)
         the detail panel full-screen with its own Back link. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Crop Planting Monitoring">
                <x-slot:actions>
                    <a href="{{ route('mao.archive.index', ['type' => 'crop_planting']) }}"
                       class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                        View archived
                    </a>
                </x-slot:actions>

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                 :options="$associations->pluck('name', 'id')" :selected="request('association_id')" class="sm:w-56" />

                    <x-ui.select name="crop_id" placeholder="All Crops" onchange="this.form.submit()"
                                 :options="$crops->pluck('name', 'id')" :selected="request('crop_id')" class="sm:w-44" />

                    <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                                 :options="$barangays->pluck('name', 'id')" :selected="request('barangay_id')" class="sm:w-48" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer"
                                class="sm:min-w-[12rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'association_id', 'crop_id', 'barangay_id']))
                        <a href="{{ route('mao.crop-planting.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                                <th class="px-3 py-3">Farmer ID</th>
                                <th class="px-3 py-3">Name</th>
                                <th class="px-3 py-3">Association</th>
                                <th class="px-3 py-3">Crop</th>
                                <th class="px-3 py-3">Planting Date</th>
                                <th class="px-3 py-3">Farm Area</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse ($plantings as $planting)
                                @php $farmer = $planting->plantingRecord?->farmer; @endphp
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $planting->crop_planting_record_id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4 text-foreground">
                                        {{ $farmer ? 'FARM-' . str_pad($farmer->id, 3, '0', STR_PAD_LEFT) : '-' }}
                                    </td>
                                    <td class="px-3 py-4 font-bold text-foreground">{{ $farmer?->full_name ?? 'Unknown' }}</td>
                                    <td class="px-3 py-4 text-muted-foreground">{{ $farmer?->association?->name ?? '-' }}</td>
                                    <td class="px-3 py-4 text-muted-foreground">
                                        {{ $planting->crop?->name ?? '-' }}
                                        @if ($planting->crop_specify)
                                            <span class="block text-xs text-muted-foreground">{{ $planting->crop_specify }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4 text-muted-foreground">{{ $planting->date_planted?->format('M d, Y') }}</td>
                                    <td class="px-3 py-4 tabular-nums text-muted-foreground">{{ $planting->area_hectares }} ha</td>
                                    <td class="px-3 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $farmer?->activity_status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                            {{ $farmer?->activity_status === 'active' ? 'Active' : 'In-Active' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex justify-end gap-2">
                                            <x-ui.button :href="$viewUrl($planting->crop_planting_record_id)" variant="view" size="sm">View</x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-14 text-center">
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

                <div class="mt-6 border-t border-border pt-5">{{ $plantings->links() }}</div>
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
                                <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank"
                                   class="block overflow-hidden rounded-lg border border-border">
                                    <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Planting photo"
                                         class="h-16 w-full object-cover transition hover:scale-105">
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-muted-foreground">No photos were attached to this record.</p>
                    @endif
                </div>

            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
