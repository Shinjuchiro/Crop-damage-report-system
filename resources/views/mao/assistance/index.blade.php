@extends('layouts.app')

@section('title', 'Assistance Catalogue')
@section('hideHeading', true)

@php
    $statusBadges = [
        'active'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'inactive' => 'bg-secondary text-foreground',
        'closed'   => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ];
@endphp

@section('content')
<div x-data="{ archiving: null }">

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @php
        $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
        $backUrl = request()->fullUrlWithoutQuery(['selected']);
    @endphp

    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Assistance Catalogue" description="The pools of cash and in-kind assistance the MAO can allocate to associations.">
                <x-slot:actions>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('mao.archive.index', ['type' => 'assistance']) }}"
                           class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                            View archived
                        </a>
                        <x-ui.button :href="route('mao.assistance.create')">+ Add Assistance</x-ui.button>
                    </div>
                </x-slot:actions>

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="type" placeholder="All Types" onchange="this.form.submit()"
                                 :options="collect($types)->mapWithKeys(fn ($v) => [$v => $v === 'cash' ? 'Cash' : 'In-Kind'])"
                                 :selected="request('type')" class="sm:w-44" />

                    <x-ui.select name="status" placeholder="All Status" onchange="this.form.submit()"
                                 :options="collect($statuses)->mapWithKeys(fn ($v) => [$v => ucfirst($v)])"
                                 :selected="request('status')" class="sm:w-44" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search assistance"
                                class="sm:min-w-[14rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'type', 'status']))
                        <a href="{{ route('mao.assistance.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                                <th class="px-3 py-3">Assistance</th>
                                <th class="px-3 py-3">Type</th>
                                <th class="px-3 py-3">For</th>
                                <th class="px-3 py-3 text-right">Available</th>
                                <th class="px-3 py-3 text-right">Allocated</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse ($assistances as $assistance)
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $assistance->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4">
                                        <p class="font-medium text-foreground">{{ $assistance->name }}</p>
                                        @if ($assistance->description)
                                            <p class="max-w-sm truncate text-xs text-muted-foreground">{{ $assistance->description }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $assistance->type === 'cash' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' }}">
                                            {{ $assistance->type === 'cash' ? 'Cash' : 'In-Kind' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 text-muted-foreground">
                                        {{ $assistance->disaster?->name ?? 'Any disaster' }}
                                        <span class="block text-xs text-muted-foreground">{{ $assistance->crop?->name ?? 'Any crop' }}</span>
                                    </td>
                                    <td class="px-3 py-4 text-right tabular-nums text-foreground">
                                        {{ $assistance->available_quantity_or_amount !== null
                                            ? number_format($assistance->available_quantity_or_amount, 2)
                                            : 'Not tracked' }}
                                    </td>
                                    <td class="px-3 py-4 text-right tabular-nums text-foreground">
                                        {{ number_format((float) $assistance->allocations_sum_allocated_quantity, 2) }}
                                        <span class="block text-xs text-muted-foreground">{{ $assistance->allocations_count }} allocations</span>
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$assistance->status] ?? $statusBadges['inactive'] }}">
                                            {{ ucfirst($assistance->status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 text-right">
                                        <x-ui.button :href="$viewUrl($assistance->id)" variant="view" size="sm">View</x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-14 text-center">
                                        <p class="text-sm font-medium text-muted-foreground">No assistance set up yet</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Add rice seeds, fertiliser or financial assistance so you can allocate them.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 border-t border-border pt-5">{{ $assistances->links() }}</div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select an item from the list to view its details.">
            @if ($selected)
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assistance</p>
                        <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $selected->name }}</h3>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $selected->type === 'cash' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' }}">
                                {{ $selected->type === 'cash' ? 'Cash' : 'In-Kind' }}
                            </span>
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$selected->status] ?? $statusBadges['inactive'] }}">
                                {{ ucfirst($selected->status) }}
                            </span>
                        </div>
                    </div>
                </div>

                @if ($selected->description)
                    <div class="border-t border-border pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Description</p>
                        <p class="mt-1 text-sm text-foreground">{{ $selected->description }}</p>
                    </div>
                @endif

                <div class="border-t border-border pt-4">
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">For</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ $selected->disaster?->name ?? 'Any disaster' }}
                                &middot; {{ $selected->crop?->name ?? 'Any crop' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Available</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ $selected->available_quantity_or_amount !== null
                                    ? number_format($selected->available_quantity_or_amount, 2)
                                    : 'Not tracked' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Allocated so far</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ number_format((float) $selected->allocations_sum_allocated_quantity, 2) }}
                                <span class="text-xs text-muted-foreground">({{ $selected->allocations_count }} allocations)</span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-5 flex gap-2 border-t border-border pt-4">
                    <a href="{{ route('mao.assistance.edit', $selected) }}"
                       class="flex-1 rounded-lg border border-input px-3 py-2 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                        Edit
                    </a>

                    @if ($selected->status === 'active')
                        <button type="button"
                                @click="archiving = { id: {{ $selected->id }}, name: @js($selected->name) }"
                                class="flex-1 rounded-lg border border-amber-200 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50">
                            Archive
                        </button>
                    @elseif ($selected->status === 'inactive')
                        <form method="POST" action="{{ route('mao.assistance.restore', $selected) }}" class="flex-1"
                              data-confirm="Are you sure you want to restore {{ $selected->name }} to the active catalogue?"
                              data-confirm-title="Restore assistance"
                              data-confirm-action="Confirm Restore">
                            @csrf @method('PUT')
                            <button type="submit"
                                    class="w-full rounded-lg border border-input px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                Restore
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </x-ui.detail-panel>
    </div>

    {{-- Archive confirmation --}}
    <div x-show="archiving" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Archive this assistance item?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                <strong x-text="archiving?.name"></strong> will be taken off the active catalogue. Allocations
                already made are unaffected; it just cannot be allocated again until restored.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/assistance') }}/${archiving?.id}/archive`" class="flex-1">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        Confirm Archive
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
