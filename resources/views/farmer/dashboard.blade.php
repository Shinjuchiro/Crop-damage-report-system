@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Kumusta, ' . $farmer->first_name . '!')
@section('subheading', 'Here is your farm activity. Ito po ang inyong mga naitalang gawain.')

@section('header-actions')
    <x-ui.button size="lg" :href="route('farmer.planting.create')">
        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Record Planting
    </x-ui.button>
@endsection

@section('content')

    {{-- ----------------------------------------------------------------
         Account standing. This sits at the top because it is the thing a
         farmer is most likely to be worried about, and because Inactive
         has a cause and a cure that should be stated plainly rather than
         left as a red word.
    ----------------------------------------------------------------- --}}
    <x-ui.card class="mb-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full
                            bg-accent text-lg font-bold text-accent-foreground">
                    {{ strtoupper(substr($farmer->first_name, 0, 1) . substr($farmer->last_name, 0, 1)) }}
                </div>

                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-card-foreground">{{ $farmer->full_name }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ $farmer->association?->name ?? 'No association' }}
                        @if ($farmer->barangay) &middot; Brgy. {{ $farmer->barangay->name }} @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.status :value="$farmer->user->status === 'active' ? 'verified' : $farmer->user->status" />
                <x-ui.status :value="$farmer->activity_status" />
            </div>
        </div>

        @if ($farmer->activity_status !== 'active')
            <x-ui.alert variant="warning" class="mt-4" title="Your account is marked inactive">
                There has been no recorded farm activity for
                {{ $farmer->months_inactive }}
                {{ \Illuminate\Support\Str::plural('month', $farmer->months_inactive) }}.
                Record a crop planting activity and your account returns to active straight away.
                <span class="mt-1 block opacity-90">
                    Magtala po ng bagong pagtatanim upang maging aktibo muli ang inyong account.
                </span>
            </x-ui.alert>
        @endif
    </x-ui.card>

    {{-- Figures. Every one is counted from the database on page load. --}}
    <div class="stagger mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Planting Records"
                   :value="number_format($plantingCount)"
                   hint="Mga naitalang pagtatanim"
                   :href="route('farmer.planting.index')" />

        <x-ui.stat label="Damage Reports"
                   :value="number_format($reportTotal)"
                   hint="Mga isinumiteng ulat" />

        <x-ui.stat label="Awaiting Verification"
                   :value="number_format($pendingReports)"
                   tone="warning"
                   hint="Hinihintay ang technician" />

        <x-ui.stat label="Verified Reports"
                   :value="number_format($verifiedReports)"
                   tone="primary"
                   hint="Nasuri na ng technician" />
    </div>

    <div class="grid gap-5 lg:grid-cols-2">

        {{-- Recent planting --}}
        <x-ui.card title="Recent Planting Activity" description="Mga huling naitala" :padded="false">
            <x-slot:actions>
                <x-ui.button size="sm" variant="outline" :href="route('farmer.planting.index')">
                    View all
                </x-ui.button>
            </x-slot:actions>

            @forelse ($recentPlanting as $record)
                <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-card-foreground">
                            @foreach ($record->crops as $crop)
                                {{ $crop->crop_specify ?: $crop->crop?->name }}@if (! $loop->last), @endif
                            @endforeach
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Submitted {{ $record->date_submitted?->format('M d, Y') }}
                            &middot; {{ number_format($record->crops->sum('area_hectares'), 2) }} ha total
                        </p>
                    </div>

                    <x-ui.button size="sm" variant="ghost"
                                 :href="route('farmer.planting.show', $record)">View</x-ui.button>
                </div>
            @empty
                <x-ui.empty title="No planting recorded yet"
                            message="Record what you planted, when, and how much land it took. This also keeps your account active." />
            @endforelse
        </x-ui.card>

        {{-- Recent reports --}}
        <x-ui.card title="Recent Damage Reports" description="Mga huling ulat ng pinsala" :padded="false">
            @forelse ($recentReports as $report)
                <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-card-foreground">
                            DR-{{ str_pad($report->id, 4, '0', STR_PAD_LEFT) }}
                            &middot;
                            @foreach ($report->disasters as $disaster)
                                {{ $disaster->name }}@if (! $loop->last), @endif
                            @endforeach
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ $report->created_at?->format('M d, Y') }}
                            &middot; {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha affected
                        </p>
                    </div>

                    <x-ui.status :value="$report->status" />
                </div>
            @empty
                <x-ui.empty title="No damage reported"
                            icon="M20 6L9 17l-5-5"
                            message="That is good news. If a typhoon or flood damages your crops, report it here so the office can send help." />
            @endforelse
        </x-ui.card>
    </div>
@endsection
