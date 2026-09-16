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
<x-ui.card title="Assistance Catalogue" description="The pools of cash and in-kind assistance the MAO can allocate to associations.">
    <x-slot:actions>
        <div class="flex items-center gap-3">
            <a href="{{ route('mao.archive.index', ['type' => 'assistance']) }}"
               class="text-sm text-muted-foreground hover:text-foreground">
                View archived
            </a>
            <x-ui.button :href="route('mao.assistance.create')">+ Add Assistance</x-ui.button>
        </div>
    </x-slot:actions>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

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
                    <th class="px-3 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-sm">
                @forelse ($assistances as $assistance)
                    <tr class="hover:bg-muted/60">
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
                        <td class="px-3 py-4">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('mao.assistance.edit', $assistance) }}"
                                   class="rounded bg-green-100 px-4 py-1.5 text-xs font-semibold text-green-900 hover:bg-green-200">
                                    Edit
                                </a>

                                @if ($assistance->status === 'active')
                                    <form method="POST" action="{{ route('mao.assistance.archive', $assistance) }}"
                                          data-confirm="Are you sure you want to archive {{ $assistance->name }}? Allocations already made are unaffected; it just cannot be allocated again until restored."
                                          data-confirm-title="Archive assistance"
                                          data-confirm-action="Confirm Archive">
                                        @csrf @method('PUT')
                                        <button type="submit"
                                                class="rounded border border-amber-200 px-4 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">
                                            Archive
                                        </button>
                                    </form>
                                @elseif ($assistance->status === 'inactive')
                                    <form method="POST" action="{{ route('mao.assistance.restore', $assistance) }}"
                                          data-confirm="Are you sure you want to restore {{ $assistance->name }} to the active catalogue?"
                                          data-confirm-title="Restore assistance"
                                          data-confirm-action="Confirm Restore">
                                        @csrf @method('PUT')
                                        <button type="submit"
                                                class="rounded border border-input px-4 py-1.5 text-xs font-semibold text-foreground hover:bg-muted/60">
                                            Restore
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('mao.assistance.destroy', $assistance) }}"
                                      data-confirm="Are you sure you want to continue? {{ $assistance->name }} will be removed from the active catalogue. Its allocation and distribution history is kept for audit purposes, and this deletion will be recorded."
                                      data-confirm-title="Delete assistance permanently"
                                      data-confirm-detail="This action cannot be undone from this screen."
                                      data-confirm-action="Delete Permanently"
                                      data-confirm-tone="danger">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="rounded border border-red-200 px-4 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                                        Delete
                                    </button>
                                </form>
                            </div>
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
@endsection
