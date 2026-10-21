@extends('layouts.app')

@section('title', 'Allocation History')
@section('hideHeading', true)

@php
    $statusBadges = [
        'pending'     => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'allocated'   => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'distributed' => 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300',
        'completed'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'cancelled'   => 'bg-secondary text-muted-foreground',
    ];

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
<div x-data="{ action: null }">

    {{-- Summary

         These three were written by hand: a 64px solid tile and a 30px
         number in a tall box each. On a phone that was three full-width
         slabs, mostly empty, before the list even began, and they were the
         last figures in the system not using the shared card.

         They are that card now, so they sit in a line: two across on a
         phone, three from sm up. The colours still tell them apart, amber
         for what is waiting, grey for what is settled but not yet handed
         out, green for what has reached a farmer. --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
        <x-ui.stat label="Pending" :value="number_format($summary['pending'])" tone="warning"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2" />

        <x-ui.stat label="Approved" :value="number_format($summary['approved'])"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8" />

        <x-ui.stat label="Released" :value="number_format($summary['released'])" tone="primary"
                   icon="M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3l4.5-2.2L12 18l4.5-2.2L21 18v3" />
    </div>

    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

        {{-- LIST --}}
        <div>
            <x-ui.card title="Allocation History" description="Every assistance allocation MAO has ever made, searchable and filterable.">
                <x-slot:actions>
                    <a href="{{ route('mao.assistance-allocations.index') }}"
                       class="text-sm text-muted-foreground hover:text-foreground">
                        Back to Assistance Allocation
                    </a>
                </x-slot:actions>

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="status" placeholder="All Status" onchange="this.form.submit()" class="sm:w-40"
                                 :selected="request('status')"
                                 :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])" />

                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()" class="sm:w-48"
                                 :selected="request('association_id')" :options="$associations->pluck('name', 'id')" />

                    {{-- TWO FILTERS WERE REMOVED HERE (Oct 2026)

                         "All Assistance" and "Search assistance name" were
                         left behind when the assistance ITEM went out of the
                         module (see App\Models\Assistance). An allocation no
                         longer names what is being given, so there is no item
                         to filter by and no name to search.

                         The assistance one was not merely dead, it was fatal:
                         it read $assistances->pluck('name', 'id') and
                         history() had already stopped passing $assistances -
                         its own comment says so - which made this a "Call to
                         a member function pluck() on null" and a 500 on every
                         load of this page. The search box was the quieter
                         half of the same mistake: it submitted ?search= and
                         history() filters on nothing but status and
                         association_id, so it silently did nothing.

                         Found by a static check for variables a view uses
                         that its controller does not pass. --}}

                    @if (request()->hasAny(['status', 'association_id']))
                        <a href="{{ route('mao.assistance-allocations.history') }}"
                           class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                {{-- ---------- PHONE: one card per allocation ----------
                     The same four facts the table carries, with the card
                     itself as the tap that opens the allocation. --}}
                <ul class="space-y-3 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                    @forelse ($allocations as $allocation)
                        <li>
                            <x-ui.record-card :href="$viewUrl($allocation->id)"
                                              :eyebrow="'AA-' . str_pad($allocation->id, 3, '0', STR_PAD_LEFT)"
                                              :title="$allocation->display_name"
                                              :subtitle="$allocation->association?->name ?? 'No association'"
                                              :status="$allocation->status" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No allocations yet</p>
                        </li>
                    @endforelse
                </ul>

                {{-- ---------- TABLET AND UP ---------- --}}
                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left">
                        <thead>
                            {{-- FOUR COLUMNS (revision section 3.1)

                                 The disaster, the beneficiary count and the
                                 date allocated are all in View, along with
                                 the assistance, the quantity, who allocated
                                 it, the supporting documents and every
                                 distribution made from it.

                                 "Allocation Status", not "Status", and the
                                 word matters. This column tracks a BATCH the
                                 office sent to an association, which has its
                                 own life: pending, allocated, distributed to
                                 the association, completed, cancelled. It is
                                 NOT one of the four assistance statuses in
                                 section 11.1, which describe one farmer's
                                 case. Read as a farmer's status, a row here
                                 saying "Distributed" would look like a
                                 promise that somebody received something,
                                 when all it means is that a sack of seed
                                 left the office. --}}
                            <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                                <th class="px-3 py-3">Allocation ID</th>
                                <th class="px-3 py-3">Association</th>
                                <th class="px-3 py-3 text-center">Allocation Status</th>
                                <th class="px-3 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse ($allocations as $allocation)
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $allocation->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4 text-foreground">AA-{{ str_pad($allocation->id, 3, '0', STR_PAD_LEFT) }}</td>
                                    <td class="px-3 py-4 text-muted-foreground">{{ $allocation->association?->name ?? '-' }}</td>
                                    <td class="px-3 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$allocation->status] ?? $statusBadges['pending'] }}">
                                            {{ ucfirst($allocation->status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <x-ui.button :href="$viewUrl($allocation->id)" variant="view" size="sm">View</x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-14 text-center">
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
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select an allocation from the list to view its details.">
            @if ($selected)
                @include('mao.assistance-allocations._allocation-detail', ['allocation' => $selected])
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
