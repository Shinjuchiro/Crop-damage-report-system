@extends('layouts.app')

@section('title', 'Dashboard')
{{-- The greeting banner carries the name and the association instead. --}}
@section('hideHeading', true)

@section('content')

    <x-ui.greeting :name="auth()->user()->display_name"
                   role="Association Officer"
                   :subtitle="$association->name"
                   :href="route('association.profile')" />

    {{-- Today's weather over Tanza. Renders nothing at all if the
         forecast cannot be fetched. --}}
    <x-ui.weather />

{{--
    Association dashboard, proposal section 60.

    The order down the page follows what an officer needs to act on:
    first the assistance sitting with them undistributed, because that is aid
    that has arrived and not reached anybody yet, then the membership, then
    what the members have filed.

    Every number comes from DashboardController. Nothing here is hardcoded,
    including the member count, which is always a live COUNT rather than a
    stored figure.
--}}

<div class="space-y-4">

    {{-- The one thing that should never be ignored --}}
    @if ($summary['open_allocations'] > 0)
        <x-ui.alert variant="warning" title="Assistance is waiting to be handed out">
            The office has allocated assistance to {{ $association->name }} that has not been fully
            distributed to members yet.
            <span class="mt-2 block">
                <x-ui.button size="sm" :href="route('association.assistance.index')">
                    Open Assistance
                </x-ui.button>
            </span>
        </x-ui.alert>
    @endif

    {{-- ================= MEMBERSHIP ================= --}}
    {{-- Two across on a phone, not one, now that the stat card is compact
         below sm: all four figures fit on one screen. --}}
    <div class="stagger grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-ui.stat label="Total Members" :value="number_format($summary['total_members'])"
                   hint="Kabuuang kasapi"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9"
                   :href="route('association.members.index')" />

        <x-ui.stat label="Active Members" :value="number_format($summary['active_members'])"
                   tone="primary" :hint="'Active in the last ' . \App\Models\Farmer::INACTIVITY_MONTHS . ' months'"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9M16 2.7a4 4 0 010 7.6"
                   :href="route('association.members.index', ['status' => 'active'])" />

        <x-ui.stat label="Inactive Members" :value="number_format($summary['inactive_members'])"
                   :hint="'No planting activity for ' . \App\Models\Farmer::INACTIVITY_MONTHS . ' months'"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM17 11h5"
                   :href="route('association.members.index', ['status' => 'inactive'])" />

        <x-ui.stat label="Affected Members" :value="number_format($summary['affected_members'])"
                   tone="warning" hint="Have filed a damage report"
                   icon="M12 9.5v4M12 17h.01M10.3 3.9L2.5 17.5A1.7 1.7 0 004 20h16a1.7 1.7 0 001.5-2.5L13.7 3.9a1.7 1.7 0 00-3 0z"
                   :href="route('association.members.index', ['affected' => 'yes'])" />
    </div>

    {{-- ================= REPORTS AND ASSISTANCE ================= --}}
    <div class="stagger grid grid-cols-2 gap-3 sm:gap-4">
        <x-ui.stat label="Damage Reports" :value="number_format($summary['total_reports'])"
                   icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01"
                   :href="route('association.reports.index')" />


        <x-ui.stat label="Verified Reports" :value="number_format($summary['verified_reports'])"
                   tone="primary" hint="Eligible for assistance"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                   :href="route('association.reports.index', ['status' => 'verified'])" />

    </div>

    {{-- MEMBERS, NOT QUANTITIES (Oct 2026, client decision)

         These three cards were Allocated, Distributed and Remaining, all
         sums of goods. There are no quantities: how much a named farmer
         received is a financial detail about an identifiable person and is
         out of this system.

         The same shape, counted in people. Selected is how many members the
         office has designated, Received is how many of them it has handed
         to, and Still waiting is the gap - which is the number an officer
         acts on, because those are the members they still have to call in.
         All three count this association's own members only. --}}
    <x-ui.card title="Assistance" description="Ang tulong para sa inyong mga kasapi">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-border bg-muted p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Members selected</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($summary['designated_members']) }}</p>
            </div>
            <div class="rounded-xl border border-primary/40 bg-accent p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-accent-foreground/70">Already received</p>
                <p class="mt-1 text-2xl font-bold text-accent-foreground">
                    {{ number_format($summary['members_handed_to']) }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-muted p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Still waiting</p>
                <p class="mt-1 text-2xl font-bold">
                    {{ number_format(max($summary['designated_members'] - $summary['members_handed_to'], 0)) }}
                </p>
            </div>
        </div>

        <p class="mt-3 text-xs text-muted-foreground">
            The Municipal Agriculture Office hands assistance to your members and records it. Please tell the
            members still waiting when and where to collect theirs.
        </p>
    </x-ui.card>

    {{-- ================= TWO WORKING LISTS ================= --}}
    <div class="grid gap-4 xl:grid-cols-2">

        <x-ui.card title="Members still to collect" description="Mga kasaping hindi pa nakakakuha" :padded="false">
            @if ($needsDistribution->isEmpty())
                <x-ui.empty title="Nothing waiting"
                            icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                            message="Every member the office selected has already received theirs." />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($needsDistribution as $allocation)
                        @php
                            // This association's own members on this batch,
                            // counted by the controller's scoped withCount.
                            $mine  = $allocation->beneficiaries_count;
                            $given = $allocation->distributions_count;
                            $left  = max($mine - $given, 0);
                        @endphp

                        <li>
                            <a href="{{ route('association.assistance.show', $allocation) }}"
                               class="block px-4 py-3.5 transition hover:bg-muted sm:px-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold">
                                            AA-{{ str_pad($allocation->id, 3, '0', STR_PAD_LEFT) }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            Allocated {{ $allocation->allocated_at?->format('M d, Y') }}
                                        </p>
                                    </div>
                                    <x-ui.status :value="$allocation->status" />
                                </div>

                                @if ($mine > 0)
                                    <p class="mt-2 text-sm">
                                        <span class="font-semibold">{{ $left }}</span>
                                        <span class="text-muted-foreground">
                                            of your {{ $mine }} selected {{ \Illuminate\Support\Str::plural('member', $mine) }}
                                            still to collect
                                        </span>
                                    </p>

                                    {{-- A bar, because "8 of 10" is read faster
                                         as a shape than as two numbers. --}}
                                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-secondary">
                                        <div class="h-full rounded-full bg-primary"
                                             style="width: {{ min(100, round($given / $mine * 100)) }}%"></div>
                                    </div>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-slot:footer>
                <div class="flex justify-end">
                    <a href="{{ route('association.assistance.index') }}"
                       class="text-sm font-medium text-primary hover:underline">View All</a>
                </div>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card title="Latest damage reports" description="Pinakabagong ulat ng mga kasapi" :padded="false">
            @if ($recentReports->isEmpty())
                <x-ui.empty title="No damage reports yet"
                            message="Reports your members file appear here as soon as they submit them." />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($recentReports as $report)
                        <li class="px-4 py-3.5 sm:px-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">
                                        {{ $report->farmer?->full_name ?? 'Unknown farmer' }}
                                    </p>
                                    <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                        {{ $report->full_reference }}
                                        &middot; {{ $report->damage_cause_label }}
                                        &middot; {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Location not set' }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ $report->created_at?->format('M d, Y') }}
                                    </p>
                                </div>
                                <x-ui.status :value="$report->status" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-slot:footer>
                <div class="flex justify-end">
                    <a href="{{ route('association.reports.index') }}"
                       class="text-sm font-medium text-primary hover:underline">View All</a>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </div>
</div>
@endsection
