@extends('layouts.app')

@section('title', 'Farmers\' Associations')
@section('hideHeading', true)

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
            <x-ui.card title="Farmers' Associations" description="Every farmer belongs to an association, and all assistance is allocated through them.">
                <x-slot:actions>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('mao.archive.index', ['type' => 'associations']) }}"
                           class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                            View archived
                        </a>
                        <x-ui.button :href="route('mao.associations.create')">+ Add Association</x-ui.button>
                    </div>
                </x-slot:actions>

                {{-- Filter. No visible Search button - Enter in the field submits. --}}
                <form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or location"
                                class="sm:max-w-sm" />
                    @if (request('search'))
                        <a href="{{ route('mao.associations.index') }}"
                           class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                <div class="overflow-hidden rounded-xl border border-border">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Association</th>
                                    <th class="px-5 py-3 font-medium">Office Barangay</th>
                                    <th class="px-5 py-3 text-center font-medium">Registered</th>
                                    <th class="px-5 py-3 text-center font-medium">Officers</th>
                                    <th class="px-5 py-3 text-center font-medium">Allocations</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @forelse ($associations as $association)
                                    <tr class="hover:bg-muted/60 {{ $selected?->id === $association->id ? 'bg-muted/60' : '' }}">
                                        <td class="px-5 py-3">
                                            <p class="font-bold text-foreground">{{ $association->name }}</p>
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
                                        <td class="px-5 py-3 text-right">
                                            <x-ui.button :href="$viewUrl($association->id)" variant="view" size="sm">View</x-ui.button>
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
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select an association from the list to view its details.">
            @if ($selected)
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Association</p>
                        <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $selected->name }}</h3>
                        <p class="text-sm text-muted-foreground">
                            {{ $selected->barangay?->name ?? ($selected->location ?: 'Not on the map') }}
                        </p>
                    </div>
                </div>

                @if ($selected->description)
                    <div class="border-t border-border pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Description</p>
                        <p class="mt-1 text-sm text-foreground">{{ $selected->description }}</p>
                    </div>
                @endif

                <div class="border-t border-border pt-4">
                    <dl class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-muted/50 px-2 py-3">
                            <dd class="text-lg font-bold text-foreground">{{ $selected->farmers_count }}</dd>
                            <dt class="mt-0.5 text-xs text-muted-foreground">Registered</dt>
                        </div>
                        <div class="rounded-lg bg-muted/50 px-2 py-3">
                            <dd class="text-lg font-bold text-foreground">{{ $selected->officers_count }}</dd>
                            <dt class="mt-0.5 text-xs text-muted-foreground">Officers</dt>
                        </div>
                        <div class="rounded-lg bg-muted/50 px-2 py-3">
                            <dd class="text-lg font-bold text-foreground">{{ $selected->assistance_allocations_count }}</dd>
                            <dt class="mt-0.5 text-xs text-muted-foreground">Allocations</dt>
                        </div>
                    </dl>
                </div>

                <div class="mt-5 flex flex-wrap gap-2 border-t border-border pt-4">
                    <a href="{{ route('mao.farmers.index', ['association_id' => $selected->id]) }}"
                       class="flex-1 rounded-lg border border-input px-3 py-2 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                        Members
                    </a>
                    <a href="{{ route('mao.associations.edit', $selected) }}"
                       class="flex-1 rounded-lg border border-input px-3 py-2 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                        Edit
                    </a>
                    <button type="button"
                            @click="archiving = { id: {{ $selected->id }}, name: @js($selected->name) }"
                            class="flex-1 rounded-lg border border-amber-200 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50">
                        Archive
                    </button>
                </div>
            @endif
        </x-ui.detail-panel>
    </div>

    {{-- Archive confirmation --}}
    <div x-show="archiving" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Archive this association?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                <strong x-text="archiving?.name"></strong>'s members, officers and assistance history are unaffected;
                it just stops appearing as a choice for new registrations and allocations. Associations with active
                farmer members cannot be archived.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/associations') }}/${archiving?.id}/archive`" class="flex-1">
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
