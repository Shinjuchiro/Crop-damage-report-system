@extends('layouts.app')

@section('title', 'Validation')
@section('heading', 'Validation')
@section('heading-fil', 'Pagpapatunay')
@section('subheading', 'The inspections in front of you right now. This page should be empty at the end of a good day.')

@section('content')

{{--
    VALIDATION: THE WORK QUEUE  (technician module revision, Sept 2026)

    Rebuilt from a grid of identical cards into a dispatch list, because
    the grid answered the wrong question. Three cards of equal weight, each
    with the same stack of icon-and-text rows, tell a technician what is
    assigned to them. They do not tell them what to do next, which is the
    only thing this page is for.

    So the list is ordered the way the work is: anything already started
    sits at the top under its own heading, because a half finished
    inspection is the thing most worth closing out, and it is the one state
    where the office is waiting on you. Everything else is numbered in the
    order the controller returns, oldest assignment first, and carries how
    long it has been sitting. That figure changes colour as it ages, which
    is the closest this page comes to nagging.

    Only open work appears here. Anything submitted drops off and turns up
    in Inspection History instead.

    View Details goes to the briefing, not straight to the form: the
    planted-against-damaged comparison is what a technician should read
    before they start.
--}}

@php
    $started    = $reports->getCollection()->filter(fn ($r) => $r->status === 'under_verification');
    $notStarted = $reports->getCollection()->filter(fn ($r) => $r->status !== 'under_verification');
@endphp

<div class="space-y-4">

    <x-ui.card :padded="false">

        {{-- Filters live in the card header rather than a card of their own.
             One floating filter card above a grid of cards was three
             stacked boxes doing one job. --}}
        <div class="border-b border-border px-4 pt-4 sm:px-5 sm:pt-5">
            <x-ui.filter-bar :fields="['q', 'barangay']">
                <x-ui.input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Farmer name or DR-0001" class="sm:w-52" />

                <x-ui.select name="barangay" placeholder="All barangays" onchange="this.form.submit()"
                             :options="$barangays->pluck('name', 'id')"
                             :selected="$filters['barangay'] ?? null" class="sm:w-52" />
            </x-ui.filter-bar>
        </div>

        @if ($reports->isEmpty())
            <x-ui.empty title="You are all caught up"
                        icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                        message="Nothing assigned to you is waiting for an inspection. Anything you have already submitted is in Inspection History.">
                <span class="text-xs text-muted-foreground">Wala pong naghihintay na pagsusuri.</span>
                <x-ui.button variant="outline" :href="route('technician.history.index')">Inspection History</x-ui.button>
            </x-ui.empty>
        @else

            {{-- The count line. Plain sentence, no tiles: two numbers do not
                 need three boxes and a chart to be understood. --}}
            <p class="border-b border-border bg-muted/40 px-4 py-2.5 text-sm text-muted-foreground sm:px-5">
                @if ($started->isNotEmpty())
                    <span class="font-semibold text-foreground">{{ $started->count() }}</span>
                    in progress,
                @endif
                <span class="font-semibold text-foreground">{{ $notStarted->count() }}</span>
                not started on this page.
            </p>

            {{-- ======================= IN PROGRESS ======================= --}}
            @if ($started->isNotEmpty())
                <p class="flex items-center gap-2 border-b border-border px-4 py-2 text-[11px]
                          font-bold uppercase tracking-wider text-primary sm:px-5">
                    <span class="h-1.5 w-1.5 rounded-full bg-primary" aria-hidden="true"></span>
                    Started, not yet submitted
                </p>

                <ul class="divide-y divide-border">
                    @foreach ($started as $report)
                        @include('technician.validation._queue-row', [
                            'report'   => $report,
                            'position' => null,
                        ])
                    @endforeach
                </ul>
            @endif

            {{-- ======================= NOT STARTED ======================= --}}
            @if ($notStarted->isNotEmpty())
                @if ($started->isNotEmpty())
                    <p class="border-y border-border px-4 py-2 text-[11px] font-bold uppercase
                              tracking-wider text-muted-foreground sm:px-5">
                        Waiting for you
                    </p>
                @endif

                <ul class="divide-y divide-border">
                    @foreach ($notStarted as $report)
                        @include('technician.validation._queue-row', [
                            'report'   => $report,
                            'position' => $loop->iteration,
                        ])
                    @endforeach
                </ul>
            @endif
        @endif

        @if ($reports->hasPages())
            <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
