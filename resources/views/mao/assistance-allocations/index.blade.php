@extends('layouts.app')

@section('title', 'Assistance Allocation')

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

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Summary --}}
    <div class="stagger mb-5 grid gap-4 sm:grid-cols-3">
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

    {{-- Title + filters + add --}}
    <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select name="status"
                    class="min-w-40 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
                <option value="">All Status</option>
                @foreach ($statuses as $value)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>

            <select name="association_id"
                    class="min-w-48 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
                <option value="">All Association</option>
                @foreach ($associations as $association)
                    <option value="{{ $association->id }}" @selected(request('association_id') == $association->id)>
                        {{ $association->name }}
                    </option>
                @endforeach
            </select>

            <button class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0a2f15]">
                Filter
            </button>

            @if (request()->hasAny(['status', 'association_id', 'assistance_id', 'search']))
                <a href="{{ route('mao.assistance-allocations.index') }}"
                   class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
            @endif
        </form>

        <a href="{{ route('mao.assistance-allocations.create') }}"
           class="shrink-0 rounded-lg bg-primary px-5 py-2.5 text-center text-sm font-semibold text-white hover:brightness-110">
            + Add Allocation
        </a>
    </div>

    @if ($assistances->isEmpty())
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60 px-4 py-3 text-sm text-amber-800">
            No assistance has been set up yet. Add one under
            <a href="{{ route('mao.assistance.index') }}" class="font-medium underline">Assistance Catalogue</a>
            before allocating.
        </div>
    @endif

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                    <th class="px-3 py-3">Allocation ID</th>
                    <th class="px-3 py-3">Assistance</th>
                    <th class="px-3 py-3">Association</th>
                    <th class="px-3 py-3">Type</th>
                    <th class="px-3 py-3">Description</th>
                    <th class="px-3 py-3 text-right">Quantity</th>
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
                        <td class="px-3 py-4">
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $allocation->assistance?->type === 'cash' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' }}">
                                {{ $allocation->assistance?->type === 'cash' ? 'Cash' : 'In-Kind' }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-muted-foreground">
                            {{ $allocation->in_kind_description ?: ($allocation->assistance?->description ?: '-') }}
                        </td>
                        <td class="px-3 py-4 text-right tabular-nums text-foreground">
                            {{ $allocation->allocated_quantity !== null ? number_format($allocation->allocated_quantity, 2) : '-' }}
                        </td>
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
                                Use Add Allocation to send assistance to a Farmers' Association.
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
