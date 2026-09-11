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

    <x-ui.card :padded="false">
        @if ($records->isEmpty())
            <x-ui.empty title="No planting recorded yet"
                        message="Record what you planted, when you planted it, and how much land it took. This is also what keeps your account active.">
                <x-ui.button size="lg" :href="route('farmer.planting.create')">Record Planting</x-ui.button>
            </x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <th>Record</th>
                        <th>Crops</th>
                        <th>Planted</th>
                        <th>Total Area</th>
                        <th>Submitted</th>
                        <th class="text-right">Action</th>
                    </tr>
                </x-slot:head>

                @foreach ($records as $record)
                    <tr>
                        <td class="font-medium">
                            CP-{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }}
                        </td>

                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($record->crops as $crop)
                                    <x-ui.badge variant="primary">
                                        {{ $crop->crop_specify ?: $crop->crop?->name }}
                                    </x-ui.badge>
                                @endforeach
                            </div>
                        </td>

                        <td class="text-muted-foreground">
                            {{ $record->crops->min('date_planted')?->format('M d, Y') ?? '-' }}
                        </td>

                        <td>{{ number_format($record->crops->sum('area_hectares'), 2) }} ha</td>

                        <td class="text-muted-foreground">
                            {{ $record->date_submitted?->format('M d, Y') }}
                        </td>

                        <td class="text-right">
                            <x-ui.button size="sm" variant="outline"
                                         :href="route('farmer.planting.show', $record)">View</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        @if ($records->hasPages())
            <x-slot:footer>{{ $records->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
@endsection
