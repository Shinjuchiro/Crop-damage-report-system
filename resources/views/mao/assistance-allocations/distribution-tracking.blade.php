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

    @php
        $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
        $backUrl = request()->fullUrlWithoutQuery(['selected']);
    @endphp

    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card>
                <x-ui.filter-bar :fields="['association_id', 'distribution_status', 'receipt_status']">
                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                 class="sm:w-48" :selected="$filters['association_id'] ?? null"
                                 :options="$associations->pluck('name', 'id')" />

                    <x-ui.select name="distribution_status" placeholder="All Distribution Statuses" onchange="this.form.submit()"
                                 class="sm:w-56" :selected="$filters['distribution_status'] ?? null"
                                 :options="['pending_distribution' => 'Pending Distribution', 'distributed' => 'Distributed', 'completed' => 'Completed', 'cancelled' => 'Cancelled']" />

                    <x-ui.select name="receipt_status" placeholder="All Receipt Statuses" onchange="this.form.submit()"
                                 class="sm:w-48" :selected="$filters['receipt_status'] ?? null"
                                 :options="['pending_confirmation' => 'Waiting', 'confirmed_received' => 'Confirmed', 'not_received' => 'Not Received']" />
                </x-ui.filter-bar>

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
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $distribution->id ? 'bg-muted/60' : '' }}">
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
                                        <x-ui.button :href="$viewUrl($distribution->id)" variant="view" size="sm">View</x-ui.button>
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
            </x-ui.card>

            <div class="mt-4">{{ $distributions->links() }}</div>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a distribution from the list to view its details.">
            @if ($selected)
                @php $receipt = $selected->receipt_status; @endphp

                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            DIST-{{ str_pad($selected->id, 3, '0', STR_PAD_LEFT) }}
                        </p>
                        <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $selected->farmer?->full_name ?? 'Unknown farmer' }}</h3>
                        <p class="text-sm text-muted-foreground">
                            {{ $selected->allocation?->association?->name ?? 'No association' }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    <span class="inline-flex rounded bg-secondary px-3 py-1 text-xs font-medium text-foreground">
                        {{ ucwords(str_replace('_', ' ', $selected->distribution_status)) }}
                    </span>
                    <span class="inline-flex rounded px-3 py-1 text-xs font-medium
                        {{ $receipt === 'confirmed_received'
                            ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'
                            : ($receipt === 'not_received' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                        {{ $receipt === 'pending_confirmation' ? 'Waiting' : ucwords(str_replace('_', ' ', $receipt)) }}
                    </span>
                </div>

                <div class="border-t border-border pt-4">
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assistance</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ $selected->allocation?->assistance?->name ?? $selected->in_kind_description ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Quantity</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ $selected->quantity !== null ? number_format($selected->quantity, 2) : '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Distributed</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ $selected->distributed_at?->format('M d, Y') }}
                                <span class="block text-xs font-normal text-muted-foreground">
                                    by {{ $selected->distributedBy?->display_name ?? '-' }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                @if ($selected->allocation)
                    <div class="mt-5 border-t border-border pt-4">
                        <a href="{{ route('mao.assistance-allocations.show', $selected->allocation) }}"
                           class="block w-full rounded-lg border border-input px-3 py-2 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                            View Full Allocation
                        </a>
                    </div>
                @endif
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
