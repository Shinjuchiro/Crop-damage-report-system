@extends('layouts.app')

@section('title', 'Assistance Disputes')
@section('heading', 'Assistance Disputes')
@section('subheading', 'Distributions a farmer marked as not received. Read-only - the association recorded handing it out, the farmer disagrees, and this is where the office can see that disagreement.')

@section('header-actions')
    <a href="{{ route('mao.assistance-allocations.index') }}"
       class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
        Back to Allocations
    </a>
@endsection

@section('content')
<div>

    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-200">
        Distribution and receipt are two separate facts in this system: the association records handing
        something over, and the farmer separately confirms whether they actually received it. Neither ever
        overwrites the other, so when a farmer says "not received," it stays on record here until the
        office follows it up directly with the association.
    </div>

    @php
        $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
        $backUrl = request()->fullUrlWithoutQuery(['selected']);
    @endphp

    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card>
                <x-ui.filter-bar :fields="['association_id']">
                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                 :options="$associations->pluck('name', 'id')"
                                 :selected="request('association_id')" class="sm:w-56" />
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
                                <th class="px-5 py-3 font-medium">Reported Not Received</th>
                                <th class="px-5 py-3 text-right font-medium">Action</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                            @forelse ($disputes as $distribution)
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
                                        {{ $distribution->allocation?->assistance?->name
                                            ?? $distribution->in_kind_description
                                            ?? '-' }}
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-foreground">
                                        {{ $distribution->quantity !== null ? number_format($distribution->quantity, 2) : '-' }}
                                    </td>
                                    <td class="px-5 py-3 text-muted-foreground">
                                        {{ $distribution->distributed_at?->format('M d, Y') }}
                                        <span class="block text-xs">by {{ $distribution->distributedBy?->display_name ?? '-' }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-muted-foreground">
                                        {{ $distribution->receipt_confirmed_at?->format('M d, Y g:i A') ?? '-' }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <x-ui.button :href="$viewUrl($distribution->id)" variant="view" size="sm">View</x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-14 text-center">
                                        <p class="text-sm font-medium text-muted-foreground">No disputes</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Every farmer who has answered so far has confirmed receiving their assistance.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <div class="mt-4">{{ $disputes->links() }}</div>
        </div>

        {{-- DETAIL (read-only by design - see the page subheading) --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a dispute from the list to view its details.">
            @if ($selected)
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

                <span class="inline-flex rounded px-3 py-1 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                    Not Received
                </span>

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
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Reported not received</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ $selected->receipt_confirmed_at?->format('M d, Y g:i A') ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Farmer's note</dt>
                            <dd class="mt-0.5 text-foreground">{{ $selected->receipt_note ?? 'No note left.' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-5 flex flex-col gap-2 border-t border-border pt-4">
                    @if ($selected->allocation)
                        <a href="{{ route('mao.assistance-allocations.show', $selected->allocation) }}"
                           class="block w-full rounded-lg border border-input px-3 py-2 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                            View Full Allocation
                        </a>
                    @endif
                    @if ($selected->damageReport)
                        <a href="{{ route('mao.damage-reports.show', $selected->damageReport) }}"
                           class="block w-full rounded-lg border border-input px-3 py-2 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                            View Damage Report
                        </a>
                    @endif
                </div>
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
