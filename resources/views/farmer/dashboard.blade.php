@extends('layouts.app')

@section('title', 'Dashboard')
{{-- The greeting banner at the top of the page says all of this, so the
     heading line above the content would only repeat it. --}}
@section('hideHeading', true)

{{-- The Record Planting button that used to sit up here is gone: the
     Planting tab in the bottom bar and the Planting Records card below both
     lead to the same place, and on a phone it was a lone button floating
     above the greeting with nothing beside it. --}}

@section('content')

    {{-- ----------------------------------------------------------------
         First-run onboarding. Four swipeable cards shown once per account,
         on the first dashboard load after the office approves this farmer.

         It replaced the single "Registration Approved" dialog that used to
         sit here: same trigger, same flag (farmers.approval_welcome_shown_at,
         stamped in Farmer\DashboardController the moment we decide to show
         it), but it introduces planting, damage reporting and assistance
         rather than only announcing the approval, which is the first slide.

         dashboard.welcome-dismiss is left routed for anything still posting
         to it, though this screen no longer needs it: the flag is already
         stamped by the time the page renders.
    ----------------------------------------------------------------- --}}
    @if ($showApprovalWelcome)
        @include('farmer._onboarding', ['farmer' => $farmer])
    @endif

    {{-- The greeting, in place of the old heading line. --}}
    <x-ui.greeting :name="$farmer->first_name"
                   role="Farmer"
                   :href="route('farmer.profile')" />

    {{-- Today's weather over Tanza. Renders nothing at all if the
         forecast cannot be fetched. --}}
    <x-ui.weather />

    {{-- The figures. Every one is counted from the database on page load.

         Two across on a phone, not one. With the compact stat card these
         fit side by side comfortably, and a farmer can see all four
         figures without scrolling past them one at a time, which is what
         the old full-width cards forced. --}}
    <div class="stagger mb-5 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-ui.stat label="Planting Records"
                   :value="number_format($plantingCount)"
                   hint="Mga naitalang pagtatanim"
                   icon="M12 21v-8m0 0C12 9 9 6 5 6c0 4 3 7 7 7zm0 0c0-4 3-7 7-7 0 4-3 7-7 7z"
                   :href="route('farmer.planting.index')" />

        <x-ui.stat label="Damage Reports"
                   :value="number_format($reportTotal)"
                   hint="Mga isinumiteng ulat"
                   icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01" />

        <x-ui.stat label="Awaiting Verification"
                   :value="number_format($pendingReports)"
                   tone="warning"
                   hint="Hinihintay ang technician"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2" />

        <x-ui.stat label="Verified Reports"
                   :value="number_format($verifiedReports)"
                   tone="primary"
                   hint="Nasuri na ng technician"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8" />
    </div>

    {{-- The name / association / Verified / Active strip was here and is gone
         (Oct 2026, client request): the header already shows the name, and My
         Profile shows the status. Only the inactive warning stays, because it
         has a cause and a cure the farmer should be told plainly. --}}
    @if ($farmer->activity_status !== 'active')
        <x-ui.alert variant="warning" class="mb-5" title="Your account is marked inactive">
            There has been no recorded farm activity for
            {{ $farmer->months_inactive }}
            {{ \Illuminate\Support\Str::plural('month', $farmer->months_inactive) }}.
            Record a crop planting activity and your account returns to active straight away.
            <span class="mt-1 block opacity-90">
                Magtala po ng bagong pagtatanim upang maging aktibo muli ang inyong account.
            </span>
        </x-ui.alert>
    @endif

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

                    <x-ui.button size="sm" variant="view"
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
