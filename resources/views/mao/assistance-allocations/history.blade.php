@extends('layouts.app')

@section('title', 'Allocation History')
@section('heading', 'Allocation History')
@section('subheading', 'Every assistance allocation MAO has ever made, searchable and filterable.')

@section('header-actions')
    <a href="{{ route('mao.assistance-allocations.index') }}"
       class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
        Back to Assistance Allocation
    </a>
@endsection

@php
    $statusBadges = [
        'pending'     => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'allocated'   => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'distributed' => 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300',
        'completed'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'cancelled'   => 'bg-secondary text-muted-foreground',
    ];
@endphp

@section('content')

    {{-- Summary --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="flex items-center gap-5 rounded-xl border border-border bg-card px-6 py-5 shadow-sm">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                </svg>
            </span>
            <div>
                <p class="text-sm font-medium text-foreground">Pending</p>
                <p class="text-3xl font-bold text-foreground">{{ number_format($summary['pending']) }}</p>
            </div>
        </div>

        <div class="flex items-center gap-5 rounded-xl border border-border bg-card px-6 py-5 shadow-sm">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-sky-600 text-white">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>
                </svg>
            </span>
            <div>
                <p class="text-sm font-medium text-foreground">Approved</p>
                <p class="text-3xl font-bold text-foreground">{{ number_format($summary['approved']) }}</p>
            </div>
        </div>

        <div class="flex items-center gap-5 rounded-xl border border-border bg-card px-6 py-5 shadow-sm">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary text-white">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3l4.5-2.2L12 18l4.5-2.2L21 18v3"/>
                </svg>
            </span>
            <div>
                <p class="text-sm font-medium text-foreground">Released</p>
                <p class="text-3xl font-bold text-foreground">{{ number_format($summary['released']) }}</p>
            </div>
        </div>
    </div>

<div class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6 space-y-5">

    <div class="mb-2 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <x-ui.select name="status" placeholder="All Status" class="min-w-40" :selected="request('status')"
                         :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])" />

            <x-ui.select name="association_id" placeholder="All Associations" class="min-w-48"
                         :selected="request('association_id')" :options="$associations->pluck('name', 'id')" />

            <x-ui.select name="assistance_id" placeholder="All Assistance" class="min-w-48"
                         :selected="request('assistance_id')" :options="$assistances->pluck('name', 'id')" />

            <x-ui.input name="search" placeholder="Search assistance name" value="{{ request('search') }}" class="max-w-52" />

            <x-ui.button type="submit">Filter</x-ui.button>

            @if (request()->hasAny(['status', 'association_id', 'assistance_id', 'search']))
                <a href="{{ route('mao.assistance-allocations.history') }}"
                   class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                    <th class="px-3 py-3">Allocation ID</th>
                    <th class="px-3 py-3">Assistance</th>
                    <th class="px-3 py-3">Association</th>
                    <th class="px-3 py-3">Disaster</th>
                    <th class="px-3 py-3 text-right">Quantity</th>
                    <th class="px-3 py-3 text-right">Beneficiaries</th>
                    <th class="px-3 py-3">Date Allocated</th>
                    <th class="px-3 py-3 text-center">Status</th>
                    <th class="px-3 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-sm">
                @forelse ($allocations as $allocation)
                    <tr class="hover:bg-muted/60">
                        <td class="px-3 py-4 text-foreground">AA-{{ str_pad($allocation->id, 3, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-3 py-4 font-medium text-foreground">{{ $allocation->assistance?->name ?? '-' }}</td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $allocation->association?->name ?? '-' }}</td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $allocation->disaster?->name ?? 'Not tied to an event' }}</td>
                        <td class="px-3 py-4 text-right tabular-nums text-foreground">
                            {{ $allocation->allocated_quantity !== null ? number_format($allocation->allocated_quantity, 2) : '-' }}
                        </td>
                        <td class="px-3 py-4 text-right tabular-nums text-foreground">{{ $allocation->beneficiaries_count }}</td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $allocation->allocated_at?->format('M d, Y') }}</td>
                        <td class="px-3 py-4 text-center">
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$allocation->status] ?? $statusBadges['pending'] }}">
                                {{ ucfirst($allocation->status) }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-center">
                            <a href="{{ route('mao.assistance-allocations.show', $allocation) }}"
                               class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                View Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-14 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No allocations yet</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Use Allocate Assistance on the main page to send assistance to a Farmers' Association.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 border-t border-border pt-5">{{ $allocations->links() }}</div>
</div>
@endsection
