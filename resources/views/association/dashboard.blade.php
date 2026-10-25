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

    {{-- ================= MEMBERSHIP ================= --}}
    {{-- Six cards in one grid: two across on a phone, three across from sm up,
         so the rows are always even (3 + 3) and no card is stretched wider. --}}
    <div class="stagger grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
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

        <x-ui.stat label="Damage Reports" :value="number_format($summary['total_reports'])"
                   icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01"
                   :href="route('association.reports.index')" />


        <x-ui.stat label="Verified Reports" :value="number_format($summary['verified_reports'])"
                   tone="primary" hint="Eligible for assistance"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                   :href="route('association.reports.index', ['status' => 'verified'])" />
    </div>

    {{-- MAP OVERVIEW (Oct 2026): replaces the three-box Assistance card
         (Members selected / Already received / Still waiting). The same
         overview as the MAO and Technician dashboards, limited to the
         barangays this association governs. The "Members still to collect"
         list below still carries the assistance side. --}}
    <x-ui.card title="Maps and Visualization" description="Your members' reports by barangay">
        {{-- The very same map as Maps and Visualization (one shared component). --}}
        <x-association.coverage-map :bubbles="$map['bubbles']" :pins="$map['pins']" :governed="$map['governed']"
                                    id="assocDashMap" height="h-80 sm:h-96" :scroll="true" />
        <x-slot:footer>
            <div class="flex justify-end">
                <a href="{{ route('association.map.index') }}" class="text-sm font-medium text-primary hover:underline">View all</a>
            </div>
        </x-slot:footer>
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
