@extends('layouts.app')

@section('title', 'Assistance')
@section('heading', 'Assistance')
@section('heading-fil', 'Tulong')
@section('subheading', 'What the Municipal Agriculture Office has allocated to ' . $association->name . '.')

@section('content')

{{--
    Proposal sections 64 and 65.

    Assistance moves MAO -> Association -> Farmer. This page is the middle of
    that chain: everything the office has sent here, and how much of each one
    has actually reached a member.

    Open allocations sort to the top, because an allocation with something
    left on it is aid that has arrived and not been handed out.
--}}

<div class="space-y-4">

    <x-ui.alert variant="info">
        The office allocates assistance to your association, and your association records giving it to
        members. Nothing reaches a farmer's account until you record it here.
        <span class="mt-1 block">
            Ang naitalang pamamahagi lamang ang lalabas sa account ng bawat kasapi.
        </span>
    </x-ui.alert>

    {{-- Status filter --}}
    <x-ui.card>
        <form method="GET" action="{{ route('association.assistance.index') }}"
              class="flex flex-wrap items-end justify-end gap-3">
            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="status">Status</label>
                <select id="status" name="status"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm sm:w-48">
                    <option value="">All</option>
                    @foreach (['allocated' => 'Allocated', 'distributed' => 'Partly distributed',
                               'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <x-ui.button type="submit">Apply</x-ui.button>
        </form>
    </x-ui.card>

    @if ($allocations->isEmpty())
        <x-ui.card>
            <x-ui.empty title="Nothing allocated to you yet"
                        icon="M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21"
                        message="When the Municipal Agriculture Office allocates cash or in-kind assistance to your association, it appears here and you record handing it to members.">
                <span class="text-xs text-muted-foreground">Wala pa pong naitalagang tulong sa inyong asosasyon.</span>
            </x-ui.empty>
        </x-ui.card>
    @else

        <x-ui.card :padded="false">

            {{-- ---------- PHONE ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($allocations as $allocation)
                    @php
                        $given  = (float) ($allocation->distributed_so_far ?? 0);
                        $total  = (float) $allocation->allocated_quantity;
                        $hasQty = $allocation->allocated_quantity !== null;
                        $left   = $hasQty ? max($total - $given, 0) : null;
                        $pct    = ($hasQty && $total > 0) ? min(100, round($given / $total * 100)) : 0;
                        $closed = in_array($allocation->status, ['completed', 'cancelled'], true);
                    @endphp

                    <li class="px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ $allocation->assistance?->name ?? $allocation->in_kind_description ?? 'Assistance' }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ ucfirst($allocation->assistance?->type ?? 'in kind') }}
                                    @if ($allocation->disaster)
                                        &middot; for {{ $allocation->disaster->name }}
                                    @endif
                                </p>
                            </div>
                            <x-ui.status :value="$allocation->status" />
                        </div>

                        @if ($hasQty)
                            <p class="mt-2 text-sm">
                                <span class="font-semibold">{{ number_format($left, 2) }}</span>
                                <span class="text-muted-foreground">
                                    left of {{ number_format($total, 2) }}
                                </span>
                            </p>
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-secondary">
                                <div class="h-full rounded-full bg-primary" style="width: {{ $pct }}%"></div>
                            </div>
                        @else
                            <p class="mt-2 text-xs text-muted-foreground">Not tracked (cash assistance)</p>
                        @endif

                        <div class="mt-3 flex gap-2">
                            <x-ui.button size="sm" variant="outline" class="flex-1"
                                         :href="route('association.assistance.show', $allocation)">
                                View
                            </x-ui.button>

                            @unless ($closed)
                                <x-ui.button size="sm" class="flex-1"
                                             :href="route('association.assistance.distribute', $allocation)">
                                    Distribute
                                </x-ui.button>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden sm:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Assistance</th>
                            <th>For</th>
                            <th>Progress</th>
                            <th>Allocated</th>
                            <th>Members Given</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($allocations as $allocation)
                        @php
                            $given  = (float) ($allocation->distributed_so_far ?? 0);
                            $total  = (float) $allocation->allocated_quantity;
                            $hasQty = $allocation->allocated_quantity !== null;
                            $left   = $hasQty ? max($total - $given, 0) : null;
                            $pct    = ($hasQty && $total > 0) ? min(100, round($given / $total * 100)) : 0;
                            $closed = in_array($allocation->status, ['completed', 'cancelled'], true);
                        @endphp

                        <tr>
                            <td>
                                <span class="font-medium">
                                    {{ $allocation->assistance?->name ?? $allocation->in_kind_description ?? 'Assistance' }}
                                </span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ ucfirst($allocation->assistance?->type ?? 'in kind') }}
                                </span>
                            </td>

                            <td class="text-muted-foreground">
                                {{ $allocation->disaster?->name ?? 'Any disaster' }}
                                @if ($allocation->crop)
                                    <span class="block text-xs text-muted-foreground">{{ $allocation->crop->name }}</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap">
                                @if ($hasQty)
                                    <span class="font-semibold">{{ number_format($left, 2) }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        left of {{ number_format($total, 2) }}
                                    </span>
                                    <div class="mt-1 h-1.5 w-32 overflow-hidden rounded-full bg-secondary">
                                        <div class="h-full rounded-full bg-primary" style="width: {{ $pct }}%"></div>
                                    </div>
                                @else
                                    <span class="text-muted-foreground">Not tracked</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap text-muted-foreground">
                                {{ $allocation->allocated_at?->format('M d, Y') ?? '-' }}
                            </td>

                            <td class="text-muted-foreground">{{ $allocation->distributions_count }}</td>

                            <td><x-ui.status :value="$allocation->status" /></td>

                            <td class="whitespace-nowrap text-right">
                                <div class="flex justify-end gap-2">
                                    <x-ui.button size="sm" variant="outline"
                                                 :href="route('association.assistance.show', $allocation)">
                                        View
                                    </x-ui.button>

                                    @unless ($closed)
                                        <x-ui.button size="sm"
                                                     :href="route('association.assistance.distribute', $allocation)">
                                            Distribute
                                        </x-ui.button>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>

            @if ($allocations->hasPages())
                <x-slot:footer>{{ $allocations->links() }}</x-slot:footer>
            @endif
        </x-ui.card>
    @endif
</div>
@endsection
