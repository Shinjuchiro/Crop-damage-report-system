@extends('layouts.app')

@section('title', 'Validation')
@section('heading', 'Validation')
@section('heading-fil', 'Pagpapatunay')
@section('subheading', 'The inspections in front of you right now. This page should be empty at the end of a good day.')

@section('content')

{{--
    Only open work appears here: not started, or started and not yet
    submitted. Anything finished drops off this page and turns up in
    Inspection History instead.

    Started inspections are listed first, because a half finished inspection
    is the thing most worth closing out.
--}}

<div class="space-y-4">

    {{-- No shared list card to merge into here - each report below is its
         own card in a grid, not rows in one table, so the filter bar keeps
         a card of its own. --}}
    <x-ui.card>
        <x-ui.filter-bar :fields="['q', 'barangay']">
            <x-ui.input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Farmer name or DR-0001" class="sm:w-52" />

            <x-ui.select name="barangay" placeholder="All barangays" onchange="this.form.submit()"
                         :options="$barangays->pluck('name', 'id')"
                         :selected="$filters['barangay'] ?? null" class="sm:w-52" />
        </x-ui.filter-bar>
    </x-ui.card>

    @if ($reports->isEmpty())
        <x-ui.card>
            <x-ui.empty title="You are all caught up"
                        icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                        message="Nothing assigned to you is waiting for an inspection. Anything you have already submitted is in Inspection History.">
                <span class="text-xs text-muted-foreground">Wala pong naghihintay na pagsusuri.</span>
                <x-ui.button variant="outline" :href="route('technician.history.index')">Inspection History</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @else

        {{-- Cards rather than a table, on every screen size.
             This is a to-do list, not a report, and each entry needs its own
             action button. Cards make that obvious and work the same on a
             phone in the field as on a laptop at the office. --}}
        <div class="stagger grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach ($reports as $report)
                @php $started = $report->status === 'under_verification'; @endphp

                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $report->reference }}</p>
                            <p class="truncate text-base font-medium">
                                {{ $report->farmer?->full_name ?? 'Unknown farmer' }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ $report->farmer?->association?->name ?? 'No association' }}
                            </p>
                        </div>
                        <x-ui.status :value="$report->status" />
                    </div>

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-start gap-2">
                            <dt class="sr-only">Barangay</dt>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                 stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 21s7-5.7 7-11a7 7 0 10-14 0c0 5.3 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>
                            </svg>
                            <dd class="min-w-0">
                                {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Location not set' }}
                            </dd>
                        </div>

                        <div class="flex items-start gap-2">
                            <dt class="sr-only">Crops</dt>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                 stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z"/>
                            </svg>
                            <dd class="min-w-0">{{ $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->join(', ') ?: 'Not set' }}</dd>
                        </div>

                        <div class="flex items-start gap-2">
                            <dt class="sr-only">Assigned</dt>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                 stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>
                            </svg>
                            <dd class="min-w-0">
                                Assigned {{ $report->assigned_at?->diffForHumans() ?? 'recently' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4">
                        @if ($started)
                            <x-ui.button size="lg" class="w-full"
                                         :href="route('technician.inspection.edit', $report)">
                                Continue Inspection
                            </x-ui.button>
                        @else
                            <x-ui.button size="lg" variant="outline" class="w-full"
                                         :href="route('technician.reports.show', $report)">
                                View Report Details
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        @if ($reports->hasPages())
            <div>{{ $reports->links() }}</div>
        @endif
    @endif
</div>
@endsection
