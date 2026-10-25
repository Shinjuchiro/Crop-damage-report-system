@extends('layouts.app')

@section('title', 'Allocation')
@section('heading', 'Allocation AA-' . str_pad((string) $allocation->id, 3, '0', STR_PAD_LEFT))
@section('subheading', 'Allocated to ' . $association->name . ' on ' . ($allocation->allocated_at?->format('F d, Y') ?? 'an unrecorded date'))

@section('header-actions')
    <x-ui.button variant="outline" :href="route('association.assistance.index')">Back to assistance</x-ui.button>
@endsection

@section('content')

@php
    /* READ-ONLY SINCE OCT 2026 (client decision)

       The office hands assistance to the farmer and records it; the
       association follows along here. The "Record a distribution" button and
       the balance cards are gone with the quantities - there is no figure on
       an allocation any more, for privacy, so there is nothing to be left
       of. What replaces them is progress counted in people: how many of the
       designated members have been handed theirs. */

    $designated = $allocation->beneficiaries->count();
    $handedTo   = $allocation->distributions->pluck('farmer_id')->unique()->count();
    $pct        = $designated > 0 ? min(100, round($handedTo / $designated * 100)) : 0;
@endphp

<div class="space-y-4">

    @if ($errors->any())
        <x-ui.alert variant="destructive">{{ $errors->first() }}</x-ui.alert>
    @endif

    {{-- What this allocation is --}}
    <x-ui.card>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.status :value="$allocation->status" />
        </div>

        @php
            $details = [
                'Allocation'  => 'AA-' . str_pad((string) $allocation->id, 3, '0', STR_PAD_LEFT),
                'For disaster'=> $allocation->disaster?->name ?? 'Not tied to one',
                'For crop'    => $allocation->crop?->name ?? 'Any crop',
                'Allocated by'=> $allocation->allocatedBy?->display_name ?? 'Municipal Agriculture Office',
                'Allocated on'=> $allocation->allocated_at?->format('F d, Y') ?? '-',
                'Members designated' => (string) $designated,
                'Members given'      => (string) $handedTo,
            ];
        @endphp

        <dl class="mt-4 grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($details as $label => $value)
                <div class="min-w-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                    <dd class="mt-1 break-words text-sm font-medium">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        {{-- A "What it is" block read the allocation's free-typed name.
             An allocation does not name what is being given any more (Oct
             2026, client decision): it says assistance, and the reference
             identifies the batch. --}}

        @if ($allocation->remarks)
            <div class="mt-3 rounded-lg bg-muted px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Office remarks</p>
                <p class="mt-1 whitespace-pre-line text-sm">{{ $allocation->remarks }}</p>
            </div>
        @endif
    </x-ui.card>

    {{-- How far through it the office is --}}
    <x-ui.card title="Progress" description="Ilan na po ang nabigyan">
        @if ($designated === 0)
            <p class="text-sm text-muted-foreground">
                The office has not designated any of your members on this allocation yet.
                <span class="mt-1 block">Wala pa pong kasaping naitalaga sa tulong na ito.</span>
            </p>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-border bg-muted p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Members designated</p>
                    <p class="mt-1 text-2xl font-bold">{{ $designated }}</p>
                </div>
                <div class="rounded-xl border border-primary/40 bg-accent p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-accent-foreground/70">Already given</p>
                    <p class="mt-1 text-2xl font-bold text-accent-foreground">{{ $handedTo }}</p>
                </div>
            </div>

            <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-secondary">
                <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $pct }}%"></div>
            </div>
            <p class="mt-1.5 text-xs text-muted-foreground">
                {{ $pct }}% of the designated members have been handed theirs by the office.
            </p>
        @endif

        <p class="mt-5 border-t border-border pt-4 text-sm text-muted-foreground">
            The Municipal Agriculture Office records each hand-over. If a member tells you they have not
            received theirs, ask the office rather than recording anything here.
            <span class="mt-1 block">
                Ang tanggapan po ang nagtatala ng pamamahagi. Makipag-ugnayan po sa kanila kung may
                kasaping hindi pa nakatanggap.
            </span>
        </p>
    </x-ui.card>

    {{-- Who has already received something --}}
    <x-ui.card title="Members who received this"
               :description="$allocation->distributions->count() . ' recorded'" :padded="false">

        @if ($allocation->distributions->isEmpty())
            <x-ui.empty title="Nothing handed over yet"
                        message="Once the office records handing this to a member it appears here, and on that member's own Assistance page." />
        @else
            <x-ui.table>
                <x-slot:head>
                    {{-- Quantity went with the figures (Oct 2026). What is
                         left is who, for which report, when, by whom, and
                         whether the member agrees they got it. --}}
                    <tr>
                        <th>Member</th>
                        <th>For Report</th>
                        <th>Date Given</th>
                        <th>Recorded By</th>
                        <th>Photos</th>
                        <th>Member Confirmed?</th>
                    </tr>
                </x-slot:head>

                @foreach ($allocation->distributions->sortByDesc('distributed_at') as $given)
                    <tr>
                        <td class="font-medium">{{ $given->farmer?->full_name ?? 'Unknown' }}</td>

                        <td class="whitespace-nowrap text-muted-foreground">
                            {{ $given->damageReport?->full_reference ?? '-' }}
                        </td>

                        <td class="whitespace-nowrap text-muted-foreground">
                            {{ $given->distributed_at?->format('M d, Y') }}
                        </td>

                        <td class="text-muted-foreground">
                            {{ $given->distributedBy?->display_name ?? '-' }}
                        </td>

                        <td class="text-muted-foreground">{{ $given->photos->count() }}</td>

                        {{-- Two separate facts. The office recorded giving
                             it; the member says whether they got it. If
                             those ever disagree, the disagreement has to
                             stay visible rather than have one quietly
                             overwrite the other. --}}
                        <td>
                            @if ($given->receipt_status === 'confirmed_received')
                                <x-ui.badge variant="success" dot>Confirmed</x-ui.badge>
                                <span class="mt-1 block text-xs text-muted-foreground">
                                    {{ $given->receipt_confirmed_at?->format('M d, Y') }}
                                </span>
                            @elseif ($given->receipt_status === 'not_received')
                                <x-ui.badge variant="danger" dot>Says not received</x-ui.badge>
                                @if ($given->receipt_note)
                                    <span class="mt-1 block max-w-56 text-xs text-muted-foreground">
                                        {{ $given->receipt_note }}
                                    </span>
                                @endif
                            @else
                                <x-ui.badge variant="warning" dot>Waiting</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
@endsection
