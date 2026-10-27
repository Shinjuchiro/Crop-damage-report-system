@extends('layouts.app')

@section('title', 'My Reports')
@section('heading', 'My Reports')
@section('heading-fil', 'Aking mga Ulat')
@section('subheading', 'Everything you have submitted, and where it stands. Reports cannot be printed or exported from here.')

@section('content')

    {{-- All / Crop Damage / Crop Planting --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ([null => 'All'] + $types as $key => $label)
            <a href="{{ route('farmer.my-reports.index', $key ? ['type' => $key] : []) }}"
               @if ($type === $key) aria-current="page" @endif
               class="rounded-lg px-3.5 py-2 text-sm font-medium transition
                      {{ $type === $key
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'border border-input text-muted-foreground hover:bg-muted/60 hover:text-foreground' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <x-ui.card :padded="false">
        @if ($rows->isEmpty())
            <x-ui.empty title="Nothing submitted yet"
                        message="Your planting records and damage reports will appear here." />
        @else
            {{-- Phone: one card per record --}}
            <ul class="space-y-3 p-4 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                @foreach ($rows as $row)
                    <li>
                        <x-ui.record-card :href="$row['url']"
                                          :title="$row['id']"
                                          :subtitle="$row['type']"
                                          :fields="array_filter([
                                              'Submitted' => $row['date']?->format('M j, Y'),
                                              'Status'    => $row['status'],
                                              'Assistance' => $row['note'],
                                          ])"
                                          action="View" />
                    </li>
                @endforeach
            </ul>

            <x-ui.table class="hidden lg:block">
                <x-slot:head>
                    <tr>
                        <th>Report ID</th>
                        <th>Report Type</th>
                        <th>Submission Date</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </x-slot:head>

                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium text-foreground">{{ $row['id'] }}</td>
                        <td>{{ $row['type'] }}</td>
                        <td>{{ $row['date']?->format('M j, Y') ?? '-' }}</td>
                        <td>
                            <x-ui.badge :variant="$row['tone']">{{ $row['status'] }}</x-ui.badge>
                            @if ($row['note'])
                                <p class="mt-1 text-xs text-muted-foreground">{{ $row['note'] }}</p>
                            @endif
                        </td>
                        <td class="text-right">
                            <x-ui.button size="sm" variant="outline" :href="$row['url']">View</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            @if ($rows->hasPages())
                <div class="border-t border-border px-4 py-3">{{ $rows->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
