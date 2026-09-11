@extends('layouts.app')

@section('title', 'Farmers\' Associations')
@section('heading', 'Farmers\' Associations')
@section('subheading', 'Every farmer belongs to an association, and all assistance is allocated through them.')

@section('content')
<div x-data="{ deleting: null }">

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex w-full max-w-sm gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or location"
                   class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
            <button class="rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                Search
            </button>
            @if (request('search'))
                <a href="{{ route('mao.associations.index') }}"
                   class="rounded-lg px-3 py-2 text-sm text-muted-foreground hover:text-foreground">Clear</a>
            @endif
        </form>

        <div class="flex shrink-0 items-center gap-3">
            <a href="{{ route('mao.archive.index', ['type' => 'associations']) }}"
               class="text-sm text-muted-foreground hover:text-foreground">
                View archived
            </a>
            <a href="{{ route('mao.associations.create') }}"
               class="rounded-lg bg-green-800 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-green-900">
                + Add Association
            </a>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-3 font-medium">Association</th>
                        <th class="px-5 py-3 font-medium">Office Barangay</th>
                        <th class="px-5 py-3 text-center font-medium">Registered</th>
                        <th class="px-5 py-3 text-center font-medium">Officers</th>
                        <th class="px-5 py-3 text-center font-medium">Allocations</th>
                        <th class="px-5 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($associations as $association)
                        @php
                            $used = $association->farmers_count
                                  + $association->officers_count
                                  + $association->assistance_allocations_count;
                        @endphp
                        <tr class="hover:bg-muted/60">
                            <td class="px-5 py-3">
                                <p class="font-medium text-foreground">{{ $association->name }}</p>
                                @if ($association->description)
                                    <p class="mt-0.5 max-w-md truncate text-xs text-muted-foreground">{{ $association->description }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $association->barangay?->name ?? ($association->location ?: '-') }}
                                @unless ($association->barangay)
                                    <span class="block text-xs text-amber-600">Not on the map</span>
                                @endunless
                            </td>
                            <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $association->farmers_count }}</td>
                            <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $association->officers_count }}</td>
                            <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $association->assistance_allocations_count }}</td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('mao.farmers.index', ['association_id' => $association->id]) }}"
                                       class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                        Members
                                    </a>
                                    <a href="{{ route('mao.associations.edit', $association) }}"
                                       class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                        Edit
                                    </a>

                                    <form method="POST" action="{{ route('mao.associations.archive', $association) }}"
                                          data-confirm="Are you sure you want to archive {{ $association->name }}? Its members, officers and assistance history are unaffected; it just stops appearing as a choice for new registrations and allocations. Associations with active farmer members cannot be archived."
                                          data-confirm-title="Archive association"
                                          data-confirm-action="Confirm Archive">
                                        @csrf @method('PUT')
                                        <button type="submit"
                                                class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
                                            Archive
                                        </button>
                                    </form>

                                    @if ($used === 0)
                                        <button type="button"
                                                @click="deleting = { id: {{ $association->id }}, name: @js($association->name) }"
                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                            Delete
                                        </button>
                                    @else
                                        <span class="rounded-lg border border-border px-3 py-1.5 text-xs text-muted-foreground"
                                              title="This association already has records attached.">In use</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <p class="text-sm font-medium text-muted-foreground">No associations yet</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Farmers cannot register until at least one association exists.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $associations->links() }}</div>

    {{-- Delete confirmation --}}
    <div x-show="deleting" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Delete this association?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                You are about to permanently remove <strong x-text="deleting?.name"></strong>.
                This cannot be undone.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="deleting = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/associations') }}/${deleting?.id}`" class="flex-1">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="w-full rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
