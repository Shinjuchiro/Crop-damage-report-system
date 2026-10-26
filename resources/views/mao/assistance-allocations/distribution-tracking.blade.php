@extends('layouts.app')

@section('title', 'Distribution Tracking')
@section('heading', 'Distribution Tracking')
{{-- The wording here and in the notice below used to say the ASSOCIATION
     records the hand-over. It does not, and has not since the Oct 2026
     client decision: the office hands assistance to the farmer and records
     it itself, and the association monitors. Left uncorrected, this page was
     telling the office that somebody else keeps the record it is looking
     at. --}}
@section('subheading', "Every hand-over the office has recorded, and whether the farmer confirms receiving it.")

@section('header-actions')
    <a href="{{ route('mao.assistance-allocations.index') }}"
       class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
        Back to Assistance Allocation
    </a>
@endsection

@section('content')
<div>

    <x-ui.alert variant="info" class="mb-5">
        Distribution and receipt are two separate facts in this system: the office records handing
        something over, and the farmer separately confirms whether they actually received it. A distribution
        can be "Distributed" while its receipt is still "Waiting" - that gap is exactly what this page tracks.
        Anything a farmer marked "not received" also shows up on the
        <a href="{{ route('mao.assistance-allocations.disputes') }}" class="font-medium underline">Disputes</a> list.
    </x-ui.alert>

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

                {{-- ---------- PHONE: one card per distribution ----------
                     The worst of the set: 684px of a 1001px table sits
                     off-screen on a phone. Cards below sm, table from sm up. --}}
                <ul class="space-y-3 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                    @forelse ($distributions as $distribution)
                        <li>
                            <x-ui.record-card :href="$viewUrl($distribution->id)"
                                              :eyebrow="'DIST-' . str_pad($distribution->id, 3, '0', STR_PAD_LEFT)"
                                              :title="$distribution->farmer?->full_name ?? 'Unknown'"
                                              :status="$distribution->receipt_status === 'confirmed_received'
                                                  ? \App\Support\AssistanceCase::RECEIVED
                                                  : \App\Support\AssistanceCase::DESIGNATED" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No distributions recorded yet</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Associations record these from their own Assistance screen.
                            </p>
                        </li>
                    @endforelse
                </ul>

                {{-- ---------- TABLET AND UP ---------- --}}
                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                            {{-- FOUR COLUMNS, ONE STATUS (sections 3.1 and 11.1)

                                 This table used to carry two status columns
                                 side by side, Distribution and Receipt, which
                                 is the exact thing section 11.1 rules out:
                                 recording that something was handed over is
                                 an event, not a main status, and a farmer
                                 reading two statuses at once cannot tell
                                 which one answers "did they get it".

                                 One Status column now, in the revision's own
                                 words: Received once the farmer has confirmed,
                                 Designated for Assistance until then. The
                                 hand-over date, who handed it over, the
                                 association and the farmer's own receipt
                                 answer are all in View, which already
                                 printed every one of them. (The quantity
                                 used to be in that list too; there is no
                                 quantity any more.) --}}
                            <tr>
                                <th class="px-5 py-3 font-medium">Distribution ID</th>
                                <th class="px-5 py-3 font-medium">Farmer</th>
                                <th class="px-5 py-3 text-center font-medium">Status</th>
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
                                    <td class="px-5 py-3 text-center">
                                        @php
                                            /* Received only when the farmer has said so.
                                               A hand-over the farmer has not confirmed,
                                               and one they have actively disputed, both
                                               leave the case at Designated: the dispute
                                               is an event on the record, visible in View
                                               and on the Disputes screen, not a fifth
                                               main status (section 11.1). */
                                            $case = $distribution->receipt_status === 'confirmed_received'
                                                ? \App\Support\AssistanceCase::RECEIVED
                                                : \App\Support\AssistanceCase::DESIGNATED;
                                        @endphp

                                        <x-ui.status :value="$case" />

                                        @if ($distribution->receipt_status === 'not_received')
                                            <span class="mt-1 block text-xs font-medium text-destructive">
                                                Farmer reported not received
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <x-ui.button :href="$viewUrl($distribution->id)" variant="view" size="sm">View</x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-14 text-center">
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

                @php
                    $row = fn ($label, $value) => '<div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">'
                        . e($label) . '</dt><dd class="min-w-0 break-words text-right font-medium text-foreground">' . e($value ?: '-') . '</dd></div>';
                @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        DIST-{{ str_pad($selected->id, 3, '0', STR_PAD_LEFT) }}
                    </p>
                    <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $selected->farmer?->full_name ?? 'Unknown farmer' }}</h3>
                    {{-- The farmer's own association. This read the
                         allocation's association_id, null on every
                         allocation since migration 2024_01_22_000001. --}}
                    <p class="text-sm text-muted-foreground">
                        {{ $selected->farmer?->association?->name ?? 'No association' }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span class="inline-flex rounded bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">
                            {{ ucwords(str_replace('_', ' ', $selected->distribution_status)) }}
                        </span>
                        <span class="inline-flex rounded px-2.5 py-1 text-xs font-medium
                            {{ $receipt === 'confirmed_received'
                                ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'
                                : ($receipt === 'not_received' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                            {{ $receipt === 'pending_confirmation' ? 'Waiting' : ucwords(str_replace('_', ' ', $receipt)) }}
                        </span>
                    </div>
                </div>

                {{-- The Quantity row is gone with every other figure (Oct
                     2026, client decision): how much a named farmer received
                     is a financial detail about an identifiable person. The
                     allocation reference says which batch it came from. --}}
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
                            {!! $row('Receipt Confirmed', $selected->receipt_confirmed_at?->format('M d, Y g:i A')) !!}
                        </dl>
                    </div>

                    @if ($selected->receipt_note)
                        <div class="border-t border-border pt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farmer's Note</p>
                            <p class="text-foreground">{{ $selected->receipt_note }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
