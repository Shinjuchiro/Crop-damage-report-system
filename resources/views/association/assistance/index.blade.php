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
              class="flex flex-wrap items-end gap-3">
            <div class="min-w-48 flex-1 space-y-1.5">
                <label class="block text-sm font-medium" for="status">Status</label>
                <select id="status" name="status" onchange="this.form.submit()"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All</option>
                    @foreach (['allocated' => 'Allocated', 'distributed' => 'Partly distributed',
                               'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <x-ui.button variant="outline" :href="route('association.assistance.index')">Clear</x-ui.button>
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

        {{-- Cards, not a table. Each one carries a progress bar and its own
             action button, which a table row handles badly on a phone. --}}
        <div class="stagger grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach ($allocations as $allocation)
                @php
                    $given  = (float) ($allocation->distributed_so_far ?? 0);
                    $total  = (float) $allocation->allocated_quantity;
                    $hasQty = $allocation->allocated_quantity !== null;
                    $left   = $hasQty ? max($total - $given, 0) : null;
                    $pct    = ($hasQty && $total > 0) ? min(100, round($given / $total * 100)) : 0;
                    $closed = in_array($allocation->status, ['completed', 'cancelled'], true);
                @endphp

                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold">
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
                        <div class="mt-4">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span>
                                    <span class="text-xl font-bold">{{ number_format($left, 2) }}</span>
                                    <span class="text-muted-foreground">left</span>
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ number_format($given, 2) }} of {{ number_format($total, 2) }} given
                                </span>
                            </div>

                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-secondary">
                                <div class="h-full rounded-full bg-primary transition-all"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-muted-foreground">
                            No quantity recorded on this allocation. This is how cash assistance is stored.
                        </p>
                    @endif

                    <dl class="mt-4 space-y-1.5 text-xs text-muted-foreground">
                        <div>
                            <dt class="inline font-medium">Allocated:</dt>
                            <dd class="inline">{{ $allocation->allocated_at?->format('M d, Y') ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="inline font-medium">Members given:</dt>
                            <dd class="inline">{{ $allocation->distributions_count }}</dd>
                        </div>
                        @if ($allocation->crop)
                            <div>
                                <dt class="inline font-medium">For crop:</dt>
                                <dd class="inline">{{ $allocation->crop->name }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-4 flex gap-2">
                        <x-ui.button variant="outline" class="flex-1"
                                     :href="route('association.assistance.show', $allocation)">
                            View
                        </x-ui.button>

                        @unless ($closed)
                            <x-ui.button class="flex-1"
                                         :href="route('association.assistance.distribute', $allocation)">
                                Distribute
                            </x-ui.button>
                        @endunless
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        @if ($allocations->hasPages())
            <div>{{ $allocations->links() }}</div>
        @endif
    @endif
</div>
@endsection
