@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Welcome back, ' . auth()->user()->display_name . '!')
@section('subheading', $association->name)

@section('content')

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
    <div class="stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Total Members" :value="number_format($summary['total_members'])"
                   hint="Kabuuang kasapi"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9"
                   :href="route('association.members.index')" />

        <x-ui.stat label="Active Members" :value="number_format($summary['active_members'])"
                   tone="primary" hint="Active in the last 3 months"
                   :href="route('association.members.index', ['status' => 'active'])" />

        <x-ui.stat label="Inactive Members" :value="number_format($summary['inactive_members'])"
                   hint="No planting activity for 3 months"
                   :href="route('association.members.index', ['status' => 'inactive'])" />

        <x-ui.stat label="Affected Members" :value="number_format($summary['affected_members'])"
                   tone="warning" hint="Have filed a damage report"
                   :href="route('association.members.index', ['affected' => 'yes'])" />
    </div>

    {{-- ================= REPORTS AND ASSISTANCE ================= --}}
    <div class="stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Damage Reports" :value="number_format($summary['total_reports'])"
                   icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01"
                   :href="route('association.reports.index')" />

        <x-ui.stat label="Awaiting Verification" :value="number_format($summary['pending_reports'])"
                   tone="warning" hint="Not yet inspected"
                   :href="route('association.reports.index', ['status' => 'pending'])" />

        <x-ui.stat label="Verified Reports" :value="number_format($summary['verified_reports'])"
                   tone="primary" hint="Eligible for assistance"
                   :href="route('association.reports.index', ['status' => 'verified'])" />

        <x-ui.stat label="Awaiting Confirmation"
                   :value="number_format($summary['awaiting_confirmation'])"
                   hint="Members have not confirmed receipt"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2"
                   :href="route('association.assistance.index')" />
    </div>

    {{-- Allocated against distributed. Two figures that only mean anything
         beside each other: the gap is what is still sitting here. --}}
    <x-ui.card title="Assistance" description="Ang tulong na natanggap at naipamahagi">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-border bg-muted p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Allocated</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($summary['allocated_quantity'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-primary/40 bg-accent p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-accent-foreground/70">Distributed</p>
                <p class="mt-1 text-2xl font-bold text-accent-foreground">
                    {{ number_format($summary['distributed_quantity'], 2) }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-muted p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Remaining</p>
                <p class="mt-1 text-2xl font-bold">
                    {{ number_format(max($summary['allocated_quantity'] - $summary['distributed_quantity'], 0), 2) }}
                </p>
            </div>
        </div>

        <p class="mt-3 text-xs text-muted-foreground">
            Quantities are counted across every in-kind allocation. Cash assistance is recorded without a
            quantity, so it does not appear in these three figures.
        </p>
    </x-ui.card>

    {{-- ================= TWO WORKING LISTS ================= --}}
    <div class="grid gap-4 xl:grid-cols-2">

        <x-ui.card title="Waiting to be distributed" description="Hindi pa naipamamahagi" :padded="false">
            @if ($needsDistribution->isEmpty())
                <x-ui.empty title="Nothing waiting"
                            icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                            message="Every allocation the office sent you has been given out." />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($needsDistribution as $allocation)
                        @php
                            $given = (float) ($allocation->distributed_so_far ?? 0);
                            $total = (float) $allocation->allocated_quantity;
                            $left  = $allocation->allocated_quantity === null ? null : max($total - $given, 0);
                        @endphp

                        <li>
                            <a href="{{ route('association.assistance.show', $allocation) }}"
                               class="block px-4 py-3.5 transition hover:bg-muted sm:px-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold">
                                            {{ $allocation->assistance?->name ?? $allocation->in_kind_description ?? 'Assistance' }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            @if ($allocation->disaster)
                                                For {{ $allocation->disaster->name }} &middot;
                                            @endif
                                            allocated {{ $allocation->allocated_at?->format('M d, Y') }}
                                        </p>
                                    </div>
                                    <x-ui.status :value="$allocation->status" />
                                </div>

                                @if ($left !== null)
                                    <p class="mt-2 text-sm">
                                        <span class="font-semibold">{{ number_format($left, 2) }}</span>
                                        <span class="text-muted-foreground">
                                            left of {{ number_format($total, 2) }}
                                        </span>
                                    </p>

                                    {{-- A bar, because "80 of 100" is read faster
                                         as a shape than as two numbers. --}}
                                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-secondary">
                                        <div class="h-full rounded-full bg-primary"
                                             style="width: {{ $total > 0 ? min(100, round($given / $total * 100)) : 0 }}%"></div>
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
                                        {{ $report->reference }}
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
