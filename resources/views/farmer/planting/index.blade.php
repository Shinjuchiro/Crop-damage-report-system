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

@section('content')

    @if ($farmer->activity_status !== 'active')
        <x-ui.alert variant="warning" class="mb-5" title="Recording a planting brings your account back to active">
            Magtala po ng pagtatanim upang maging aktibo muli ang inyong account.
        </x-ui.alert>
    @endif

    {{-- Cards rather than a table, same as My Reports: a farmer on a phone
         reads one planting record at a time, and a wide table just forces
         horizontal scrolling to see anything past the first two columns. --}}
    <div class="space-y-4">

        @forelse ($records as $record)
            <x-ui.card class="card-hover">
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

                    <x-ui.button variant="outline" class="shrink-0"
                                 :href="route('farmer.planting.show', $record)">View</x-ui.button>
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
@endsection
