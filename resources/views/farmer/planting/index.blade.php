@extends('layouts.app')

@section('title', 'Crop Planting')
@section('heading', 'Crop Planting Records')
@section('subheading', 'Everything you have recorded planting. Mga naitala ninyong pagtatanim.')

@section('header-actions')
    <x-ui.button size="lg" :href="route('farmer.planting.create')">
        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Record Planting
    </x-ui.button>
@endsection

@php
    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')

    @if ($farmer->activity_status !== 'active')
        <x-ui.alert variant="warning" class="mb-5" title="Recording a planting brings your account back to active">
            Magtala po ng pagtatanim upang maging aktibo muli ang inyong account.
        </x-ui.alert>
    @endif

    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a record's View is followed)
         the detail panel full-screen with its own Back link. Cards rather
         than a table on the list side, same reasoning as before: a farmer
         reads one record at a time rather than scanning a wide table. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }} space-y-4">

            @forelse ($records as $record)
                <x-ui.card class="card-hover {{ $selected?->id === $record->id ? 'ring-2 ring-primary' : '' }}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                        <div class="min-w-0 space-y-2">
                            <span class="text-sm font-bold text-card-foreground">
                                CP-{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }}
                            </span>

                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($record->crops as $crop)
                                    <x-ui.badge variant="primary">
                                        {{ $crop->crop_specify ?: $crop->crop?->name }}
                                        &middot; {{ number_format($crop->area_hectares, 2) }} ha
                                    </x-ui.badge>
                                @endforeach
                            </div>

                            <p class="text-sm text-muted-foreground">
                                Planted {{ $record->crops->min('date_planted')?->format('M d, Y') ?? 'date not set' }}
                                &middot; {{ number_format($record->crops->sum('area_hectares'), 2) }} ha total
                                &middot; submitted {{ $record->date_submitted?->format('M d, Y') }}
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            <x-ui.button variant="view" :href="$viewUrl($record->id)">View</x-ui.button>
                            <x-ui.button variant="outline" :href="route('farmer.planting.edit', $record)">Edit</x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card :padded="false">
                    <x-ui.empty title="No planting recorded yet"
                                message="Record what you planted, when you planted it, and how much land it took. This is also what keeps your account active." />
                </x-ui.card>
            @endforelse

            @if ($records->hasPages())
                <div class="pt-2">{{ $records->links() }}</div>
            @endif
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a record from the list to view its details.">
            @if ($selected)
                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Planting Record</p>
                    <h3 class="mt-0.5 text-lg font-bold text-foreground">
                        CP-{{ str_pad($selected->id, 4, '0', STR_PAD_LEFT) }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Submitted {{ $selected->date_submitted?->format('M d, Y') }}
                    </p>
                </div>

                <div class="border-t border-border pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crops</p>
                    <div class="space-y-2">
                        @foreach ($selected->crops as $crop)
                            <div class="rounded-lg bg-muted/50 px-3 py-2 text-xs">
                                <p class="font-bold text-foreground">
                                    {{ $crop->crop?->name }}
                                    @if ($crop->crop_specify)
                                        <span class="font-normal text-muted-foreground">&middot; {{ $crop->crop_specify }}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-muted-foreground">
                                    Planted {{ $crop->date_planted?->format('M d, Y') }}
                                    &middot; {{ number_format($crop->area_hectares, 2) }} ha
                                </p>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs font-medium text-foreground">
                        Total area planted: {{ number_format($selected->crops->sum('area_hectares'), 2) }} ha
                    </p>
                </div>

                <p class="mt-4 border-t border-border pt-4 text-xs text-muted-foreground">
                    Spotted a mistake? Use Edit to correct the crop, date or area.
                    Kung may mali, gamitin ang Edit upang ayusin.
                </p>

                <div class="mt-5 border-t border-border pt-4">
                    <a href="{{ route('farmer.planting.edit', $selected) }}"
                       class="block w-full rounded-lg border border-input px-3 py-2 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                        Edit
                    </a>
                </div>
            @endif
        </x-ui.detail-panel>
    </div>
@endsection
