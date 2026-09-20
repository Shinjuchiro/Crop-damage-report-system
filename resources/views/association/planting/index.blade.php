@extends('layouts.app')

@section('title', 'Planting Activities')
@section('heading', 'Planting Activities')
@section('heading-fil', 'Mga Pagtatanim')
@section('subheading', 'What members of ' . $association->name . ' have planted.')

@section('content')

{{--
    Proposal section 60. Read only.

    This list is also how an officer sees who is at risk of going inactive:
    planting is the only thing that counts as a qualifying activity
    (section 21), so a member who never appears here is heading for Inactive
    after three months.
--}}

<div class="space-y-4">

    <x-ui.card :padded="false">
        <div class="border-b border-border px-4 pt-4 sm:px-5 sm:pt-5">
            <x-ui.filter-bar :fields="['q', 'month', 'year']">
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
        </div>

        @if ($records->isEmpty())
            <x-ui.empty title="No planting activity found"
                        icon="M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z"
                        message="Members record what they plant from their own dashboard. Nothing has been recorded for this period yet.">
                <x-ui.button variant="outline" :href="route('association.planting.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($records as $record)
                    <li class="px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="truncate text-sm font-semibold">
                                {{ $record->farmer?->full_name ?? 'Unknown member' }}
                            </p>
                            <span class="shrink-0 text-xs text-muted-foreground">
                                {{ $record->date_submitted?->format('M d, Y') }}
                            </span>
                        </div>

                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ $record->farmer?->barangay?->name ?? 'Barangay not set' }}
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
                            <th>Member</th>
                            <th>Barangay</th>
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
