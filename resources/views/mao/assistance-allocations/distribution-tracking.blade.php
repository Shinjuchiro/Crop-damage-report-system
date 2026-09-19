@extends('layouts.app')

@section('title', 'Distribution Tracking')
@section('heading', 'Distribution Tracking')
@section('subheading', "Every hand-out an association has recorded for its members, and whether the farmer confirms receiving it.")

@section('header-actions')
    <a href="{{ route('mao.assistance-allocations.index') }}"
       class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
        Back to Assistance Allocation
    </a>
@endsection

@section('content')
<div>

    <x-ui.alert variant="info" class="mb-5">
        Distribution and receipt are two separate facts in this system: the association records handing
        something over, and the farmer separately confirms whether they actually received it. A distribution
        can be "Distributed" while its receipt is still "Waiting" - that gap is exactly what this page tracks.
        Anything a farmer marked "not received" also shows up on the
        <a href="{{ route('mao.assistance-allocations.disputes') }}" class="font-medium underline">Disputes</a> list.
    </x-ui.alert>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.select name="association_id" placeholder="All Associations" class="min-w-48"
                     :selected="$filters['association_id'] ?? null" :options="$associations->pluck('name', 'id')" />

        <x-ui.select name="distribution_status" placeholder="All Distribution Statuses" class="min-w-52"
                     :selected="$filters['distribution_status'] ?? null"
                     :options="['pending_distribution' => 'Pending Distribution', 'distributed' => 'Distributed', 'completed' => 'Completed', 'cancelled' => 'Cancelled']" />

        <x-ui.select name="receipt_status" placeholder="All Receipt Statuses" class="min-w-48"
                     :selected="$filters['receipt_status'] ?? null"
                     :options="['pending_confirmation' => 'Waiting', 'confirmed_received' => 'Confirmed', 'not_received' => 'Not Received']" />

        <x-ui.button type="submit">Filter</x-ui.button>

        @if (request()->hasAny(['association_id', 'distribution_status', 'receipt_status']))
            <a href="{{ route('mao.assistance-allocations.distribution-tracking') }}"
               class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-3 font-medium">Distribution</th>
                        <th class="px-5 py-3 font-medium">Farmer</th>
                        <th class="px-5 py-3 font-medium">Association</th>
                        <th class="px-5 py-3 font-medium">Assistance</th>
                        <th class="px-5 py-3 text-right font-medium">Quantity</th>
                        <th class="px-5 py-3 font-medium">Distributed On</th>
                        <th class="px-5 py-3 text-center font-medium">Distribution</th>
                        <th class="px-5 py-3 text-center font-medium">Receipt</th>
                        <th class="px-5 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    @forelse ($distributions as $distribution)
                        <tr class="hover:bg-muted/60">
                            <td class="px-5 py-3 text-foreground">
                                DIST-{{ str_pad($distribution->id, 3, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-5 py-3 font-bold text-foreground">
                                {{ $distribution->farmer?->full_name ?? 'Unknown' }}
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $distribution->allocation?->association?->name ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $distribution->allocation?->assistance?->name ?? $distribution->in_kind_description ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-foreground">
                                {{ $distribution->quantity !== null ? number_format($distribution->quantity, 2) : '-' }}
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $distribution->distributed_at?->format('M d, Y') }}
                                <span class="block text-xs">by {{ $distribution->distributedBy?->display_name ?? '-' }}</span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex rounded bg-secondary px-3 py-1 text-xs font-medium text-foreground">
                                    {{ ucwords(str_replace('_', ' ', $distribution->distribution_status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @php $receipt = $distribution->receipt_status; @endphp
                                <span class="inline-flex rounded px-3 py-1 text-xs font-medium
                                    {{ $receipt === 'confirmed_received'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'
                                        : ($receipt === 'not_received' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                                    {{ $receipt === 'pending_confirmation' ? 'Waiting' : ucwords(str_replace('_', ' ', $receipt)) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($distribution->allocation)
                                    <a href="{{ route('mao.assistance-allocations.show', $distribution->allocation) }}"
                                       class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                        View
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-14 text-center">
                                <p class="text-sm font-medium text-muted-foreground">No distributions recorded yet</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Associations record these from their own Assistance screen.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $distributions->links() }}</div>
</div>
@endsection
