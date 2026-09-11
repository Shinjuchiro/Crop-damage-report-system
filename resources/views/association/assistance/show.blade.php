@extends('layouts.app')

@section('title', 'Allocation')
@section('heading', $allocation->assistance?->name ?? $allocation->in_kind_description ?? 'Assistance allocation')
@section('subheading', 'Allocated to ' . $association->name . ' on ' . ($allocation->allocated_at?->format('F d, Y') ?? 'an unrecorded date'))

@section('header-actions')
    <x-ui.button variant="outline" :href="route('association.assistance.index')">Back to assistance</x-ui.button>
@endsection

@section('content')

@php
    $given  = (float) $allocation->distributions->sum('quantity');
    $total  = (float) $allocation->allocated_quantity;
    $hasQty = $allocation->allocated_quantity !== null;
    $pct    = ($hasQty && $total > 0) ? min(100, round($given / $total * 100)) : 0;
    $closed = in_array($allocation->status, ['completed', 'cancelled'], true);
@endphp

<div class="space-y-4">

    @if ($errors->any())
        <x-ui.alert variant="destructive">{{ $errors->first() }}</x-ui.alert>
    @endif

    {{-- What this allocation is --}}
    <x-ui.card>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.status :value="$allocation->status" />
            @if ($allocation->assistance?->type)
                <x-ui.badge variant="primary">{{ ucfirst($allocation->assistance->type) }}</x-ui.badge>
            @endif
        </div>

        @php
            $details = [
                'Assistance'  => $allocation->assistance?->name ?? 'Not linked',
                'For disaster'=> $allocation->disaster?->name ?? 'Not tied to one',
                'For crop'    => $allocation->crop?->name ?? 'Any crop',
                'Allocated by'=> $allocation->allocatedBy?->display_name ?? 'Municipal Agriculture Office',
                'Allocated on'=> $allocation->allocated_at?->format('F d, Y') ?? '-',
                'Members given' => (string) $allocation->distributions->count(),
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

        @if ($allocation->in_kind_description)
            <div class="mt-4 rounded-lg bg-muted px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">What it is</p>
                <p class="mt-1 text-sm">{{ $allocation->in_kind_description }}</p>
            </div>
        @endif

        @if ($allocation->remarks)
            <div class="mt-3 rounded-lg bg-muted px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Office remarks</p>
                <p class="mt-1 whitespace-pre-line text-sm">{{ $allocation->remarks }}</p>
            </div>
        @endif
    </x-ui.card>

    {{-- The balance, and the button --}}
    <x-ui.card title="How much is left" description="Magkano pa ang natitira">
        @if ($hasQty)
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-border bg-muted p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Allocated</p>
                    <p class="mt-1 text-2xl font-bold">{{ number_format($total, 2) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-muted p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Given out</p>
                    <p class="mt-1 text-2xl font-bold">{{ number_format($given, 2) }}</p>
                </div>
                <div class="rounded-xl border border-primary/40 bg-accent p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-accent-foreground/70">Remaining</p>
                    <p class="mt-1 text-2xl font-bold text-accent-foreground">
                        {{ number_format($remaining ?? 0, 2) }}
                    </p>
                </div>
            </div>

            <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-secondary">
                <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $pct }}%"></div>
            </div>
            <p class="mt-1.5 text-xs text-muted-foreground">{{ $pct }}% distributed</p>
        @else
            <p class="text-sm text-muted-foreground">
                This allocation has no quantity recorded, which is how cash assistance is stored in the
                system. You can still record who received it and how much.
            </p>
        @endif

        @unless ($closed)
            <div class="mt-5 border-t border-border pt-4">
                <x-ui.button size="lg" class="w-full sm:w-auto"
                             :href="route('association.assistance.distribute', $allocation)">
                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Record a distribution
                </x-ui.button>
            </div>
        @else
            <p class="mt-5 border-t border-border pt-4 text-sm text-muted-foreground">
                This allocation is {{ $allocation->status }}, so nothing further can be recorded against it.
            </p>
        @endunless
    </x-ui.card>

    {{-- Who has already received something --}}
    <x-ui.card title="Members who received this"
               :description="$allocation->distributions->count() . ' recorded'" :padded="false">

        @if ($allocation->distributions->isEmpty())
            <x-ui.empty title="Nothing given out yet"
                        message="Once you record handing this to a member it appears here, and on that member's own Assistance page." />
        @else
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <th>Member</th>
                        <th>Quantity</th>
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

                        <td class="whitespace-nowrap">{{ number_format((float) $given->quantity, 2) }}</td>

                        <td class="whitespace-nowrap text-muted-foreground">
                            {{ $given->damageReport?->reference ?? '-' }}
                        </td>

                        <td class="whitespace-nowrap text-muted-foreground">
                            {{ $given->distributed_at?->format('M d, Y') }}
                        </td>

                        <td class="text-muted-foreground">
                            {{ $given->distributedBy?->display_name ?? '-' }}
                        </td>

                        <td class="text-muted-foreground">{{ $given->photos->count() }}</td>

                        {{-- Two separate facts. We recorded giving it; the
                             member says whether they got it. If those ever
                             disagree, the office needs to see the disagreement
                             rather than have one quietly overwrite the other. --}}
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
