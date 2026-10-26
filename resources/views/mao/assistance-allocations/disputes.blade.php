@extends('layouts.app')

@section('title', 'Assistance Disputes')
@section('heading', 'Assistance Disputes')
@section('subheading', 'Distributions a farmer marked as not received. Read-only - the office recorded handing it out, the farmer disagrees, and this is where the office can see that disagreement.')

@section('header-actions')
    <a href="{{ route('mao.assistance-allocations.index') }}"
       class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
        Back to Allocations
    </a>
@endsection

@section('content')
<div>

    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-200">
        Distribution and receipt are two separate facts in this system: the office records handing
        something over, and the farmer separately confirms whether they actually received it. Neither ever
        overwrites the other, so when a farmer says "not received," it stays on record here until the
        office follows it up with the farmer.
    </div>

    @php
        $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
        $backUrl = request()->fullUrlWithoutQuery(['selected']);
    @endphp

    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

        {{-- LIST --}}
        <div>
            <x-ui.card>
                <x-ui.filter-bar :fields="['association_id']">
                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                 :options="$associations->pluck('name', 'id')"
                                 :selected="request('association_id')" class="sm:w-56" />
                </x-ui.filter-bar>

                {{-- ---------- PHONE: one card per dispute ----------
                     529px of this table is off-screen on a phone. Cards
                     below sm, table from sm up. --}}
                <ul class="space-y-3 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                    @forelse ($disputes as $distribution)
                        <li>
                            <x-ui.record-card :href="$viewUrl($distribution->id)"
                                              :eyebrow="'DIST-' . str_pad($distribution->id, 3, '0', STR_PAD_LEFT)"
                                              :title="$distribution->farmer?->full_name ?? 'Unknown'"
                                              :subtitle="$distribution->farmer?->association?->name ?? 'No association'"
                                              status="not_received"
                                              :fields="[
                                                  'Assistance'     => $distribution->allocation?->display_name ?? 'Assistance',
                                                  'Distributed on' => $distribution->distributed_at?->format('M d, Y'),
                                                  'Reported'       => $distribution->receipt_confirmed_at?->format('M d, Y'),
                                              ]"
                                              action="Review dispute" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">Nothing disputed</p>
                        </li>
                    @endforelse
                </ul>

                {{-- ---------- TABLET AND UP ---------- --}}
                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3 font-medium">Distribution</th>
                                <th class="px-5 py-3 font-medium">Farmer</th>
                                <th class="px-5 py-3 font-medium">Association</th>
                                <th class="px-5 py-3 font-medium">Assistance</th>
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
                                    {{-- The FARMER's association, not the allocation's
                                         (fixed Oct 2026). This read
                                         $distribution->allocation->association->name,
                                         a column that has been null on every new
                                         allocation since migration
                                         2024_01_22_000001, so the column printed "-"
                                         for every recent row while the association was
                                         known all along through the farmer. --}}
                                    <td class="px-5 py-3 text-muted-foreground">
                                        {{ $distribution->farmer?->association?->name ?? 'No association' }}
                                    </td>
                                    <td class="px-5 py-3 text-muted-foreground">
                                        {{ $distribution->allocation?->display_name ?? 'Assistance' }}
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
                                    <td colspan="7" class="px-5 py-14 text-center">
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
                @php
                    $row = fn ($label, $value) => '<div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">'
                        . e($label) . '</dt><dd class="min-w-0 break-words text-right font-medium text-foreground">' . e($value ?: '-') . '</dd></div>';
                @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        DIST-{{ str_pad($selected->id, 3, '0', STR_PAD_LEFT) }}
                    </p>
                    <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $selected->farmer?->full_name ?? 'Unknown farmer' }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{ $selected->farmer?->association?->name ?? 'No association' }}
                    </p>
                    <div class="mt-2">
                        <span class="inline-flex rounded px-2.5 py-1 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                            Not Received
                        </span>
                    </div>
                </div>

                <div class="space-y-4 border-t border-border pt-4 text-sm">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Distribution</p>
                        <dl class="space-y-1.5">
                            {!! $row('Assistance', $selected->allocation?->display_name ?? 'Assistance') !!}
                            @if ($selected->allocation)
                                {!! $row('Allocation', 'AA-' . str_pad((string) $selected->allocation->id, 3, '0', STR_PAD_LEFT)) !!}
                            @endif
                            @if ($selected->damageReport)
                                {!! $row('Damage Report', $selected->damageReport->full_reference) !!}
                            @endif
                            {!! $row('Distributed', $selected->distributed_at?->format('M d, Y')) !!}
                            {!! $row('Distributed By', $selected->distributedBy?->display_name) !!}
                        </dl>
                    </div>

                    {{-- The Quantity row was here. Every figure went in Oct
                         2026 (client decision): how much a named farmer
                         received is a financial detail about an identifiable
                         person. A dispute is about whether they received
                         anything at all. --}}
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farmer's Report</p>
                        <dl class="space-y-1.5">
                            {!! $row('Reported Not Received', $selected->receipt_confirmed_at?->format('M d, Y g:i A')) !!}
                            {!! $row("Farmer's Note", $selected->receipt_note ?? 'No note left.') !!}
                        </dl>
                    </div>
                </div>
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
