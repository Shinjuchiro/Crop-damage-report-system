@extends('layouts.app')

@section('title', 'Planting Reports')
@section('heading', 'Planting Reports')
@section('heading-fil', 'Ulat sa Pagtatanim')
@section('subheading', "Crop planting activity recorded by farmers in the barangays you've worked in.")

@section('content')

{{--
    Wider than Assigned Reports on purpose: every planting record filed by a
    farmer in one of this technician's barangays, not only farmers whose
    damage reports were handed to this technician specifically. Read only,
    same as the Association module's equivalent page - a technician watches
    the barangay, they do not edit a farmer's planting record.
--}}

<div class="space-y-4">

    <x-ui.card>
        <form method="GET" action="{{ route('technician.planting.index') }}"
              class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">

            <div class="space-y-1.5 lg:col-span-2">
                <label class="block text-sm font-medium" for="q">Search farmer</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="First or last name"
                       class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="barangay">Barangay</label>
                <select id="barangay" name="barangay"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All your barangays</option>
                    @foreach ($barangays as $option)
                        <option value="{{ $option->id }}"
                                @selected((string) ($filters['barangay'] ?? '') === (string) $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="month">Month</label>
                <select id="month" name="month"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All months</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((string) ($filters['month'] ?? '') === (string) $m)>
                            {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                        </option>
                    @endfor
                </select>
            </div>

            <div class="flex gap-2">
                <x-ui.button type="submit" class="flex-1 sm:flex-none">Apply</x-ui.button>
                <x-ui.button variant="outline" :href="route('technician.planting.index')">Clear</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card :padded="false">
        @if ($records->isEmpty())
            <x-ui.empty title="No planting activity found"
                        icon="M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z"
                        message="Once the office assigns you a damage report in a barangay, planting activity recorded there by any farmer will appear here.">
                <x-ui.button variant="outline" :href="route('technician.planting.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($records as $record)
                    <li class="px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="truncate text-sm font-semibold">
                                {{ $record->farmer?->full_name ?? 'Unknown farmer' }}
                            </p>
                            <span class="shrink-0 text-xs text-muted-foreground">
                                {{ $record->date_submitted?->format('M d, Y') }}
                            </span>
                        </div>

                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ $record->farmer?->barangay?->name ?? 'Barangay not set' }}
                            &middot; {{ $record->farmer?->association?->name ?? 'No association' }}
                            &middot; {{ number_format($record->crops->sum('area_hectares'), 2) }} ha
                        </p>

                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($record->crops as $crop)
                                <x-ui.badge variant="primary">
                                    {{ $crop->crop_specify ?: $crop->crop?->name }}
                                </x-ui.badge>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden sm:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Farmer</th>
                            <th>Barangay</th>
                            <th>Association</th>
                            <th>Crops</th>
                            <th>Earliest Planting</th>
                            <th>Total Area</th>
                            <th>Date Submitted</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($records as $record)
                        <tr>
                            <td class="font-medium">{{ $record->farmer?->full_name ?? 'Unknown' }}</td>

                            <td class="text-muted-foreground">
                                {{ $record->farmer?->barangay?->name ?? '-' }}
                            </td>

                            <td class="text-muted-foreground">
                                {{ $record->farmer?->association?->name ?? 'No association' }}
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

                            <td class="whitespace-nowrap text-muted-foreground">
                                {{ $record->crops->min('date_planted')?->format('M d, Y') ?? '-' }}
                            </td>

                            <td class="whitespace-nowrap">
                                {{ number_format($record->crops->sum('area_hectares'), 2) }} ha
                            </td>

                            <td class="whitespace-nowrap text-muted-foreground">
                                {{ $record->date_submitted?->format('M d, Y') }}
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif

        @if ($records->hasPages())
            <x-slot:footer>{{ $records->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
