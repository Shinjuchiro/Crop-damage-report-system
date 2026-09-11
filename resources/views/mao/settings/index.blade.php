@extends('layouts.app')

@section('title', 'Settings')
@section('heading', 'Settings')
@section('subheading', 'Reference data the rest of the system depends on.')

@section('content')

    {{-- Written on the UI kit. Note there is not a single fixed colour here:
         every surface, line and piece of text names a token instead, which is
         what makes dark mode work without a second version of the page. --}}

    <div class="stagger grid gap-5 sm:grid-cols-2 xl:grid-cols-4">

        @php
            $cards = [
                [
                    'title' => "Farmers' Associations",
                    'body'  => 'Every farmer belongs to an association, and all assistance is allocated through them.',
                    'count' => $counts['associations'],
                    'unit'  => 'associations',
                    'route' => route('mao.associations.index'),
                    'icon'  => 'M12 3a3 3 0 100 6 3 3 0 000-6zM5.5 21v-1.5a4 4 0 014-4h5a4 4 0 014 4V21',
                ],
                [
                    'title' => 'Crops',
                    'body'  => 'The crop types farmers can choose in their profile, planting records and damage reports.',
                    'count' => $counts['crops'],
                    'unit'  => 'crop types',
                    'route' => route('mao.crops.index'),
                    'icon'  => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z',
                ],
                [
                    'title' => 'Assistance Catalogue',
                    'body'  => 'Pools of cash and in-kind assistance that can be allocated to associations.',
                    'count' => $counts['assistance'],
                    'unit'  => 'assistance types',
                    'route' => route('mao.assistance.index'),
                    'icon'  => 'M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3l4.5-2.2L12 18l4.5-2.2L21 18v3',
                ],
                [
                    'title' => 'Disaster Events',
                    'body'  => 'Typhoons, floods and other events farmers cite when reporting crop damage.',
                    'count' => $counts['disasters'],
                    'unit'  => 'events recorded',
                    'route' => route('mao.disasters.index'),
                    'icon'  => 'M12 9v4M12 17h.01M10.3 3.9L2.4 17.5A2 2 0 004.1 20.5h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
                ],
            ];
        @endphp

        @foreach ($cards as $card)
            <a href="{{ $card['route'] }}"
               class="card-hover group rounded-xl border border-border bg-card p-6 shadow-sm hover:border-primary/50">

                <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-accent text-accent-foreground">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="{{ $card['icon'] }}"/>
                    </svg>
                </span>

                <h3 class="text-base font-semibold text-card-foreground transition-colors group-hover:text-primary">
                    {{ $card['title'] }}
                </h3>

                <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ $card['body'] }}</p>

                <p class="mt-4 text-sm font-semibold text-card-foreground">
                    {{ number_format($card['count']) }}
                    <span class="font-normal text-muted-foreground">{{ $card['unit'] }}</span>
                </p>
            </a>
        @endforeach
    </div>

    <x-ui.card class="mt-6" title="Barangays"
               description="Fixed municipal boundaries, so they are not edited from here.">
        <p class="text-sm text-muted-foreground">
            {{ number_format($counts['barangays']) }} barangays are loaded from the database seeder and
            carry their PSGC code, which is what the map uses to match a boundary to a record.
        </p>
    </x-ui.card>
@endsection
