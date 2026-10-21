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

    {{-- THIS PAGE NO LONGER FEEDS ALLOCATIONS (Oct 2026)

         An allocation does not name what is being given any more: it says
         assistance, and nothing else. So nothing on this page is read by
         the Allocate form, and an officer who adds an item here will not
         see it offered there.

         The page is kept rather than deleted because it is a harmless
         record of what the office hands out, and because deleting a screen
         is the kind of change that is easy to make and awkward to undo. The
         banner is here so nobody spends ten minutes wondering why their new
         item is not in a dropdown. --}}
    <x-ui.alert variant="info" class="mb-4">
        This is a reference list only. Allocations no longer name an assistance item, so adding
        something here does not change what an officer can allocate.
    </x-ui.alert>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

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
            <x-ui.card title="Assistance Catalogue" description="A reference list of the kinds of assistance the office hands out.">
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
                    <x-ui.select name="status" placeholder="All Status" onchange="this.form.submit()"
                                 :options="collect($statuses)->mapWithKeys(fn ($v) => [$v => ucfirst($v)])"
                                 :selected="request('status')" class="sm:w-44" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search assistance"
                                class="sm:min-w-[14rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'status']))
                        <a href="{{ route('mao.assistance.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                {{-- ---------- PHONE: one card per catalogue item ---------- --}}
                <ul class="space-y-3 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                    @forelse ($assistances as $assistance)
                        <li>
                            <x-ui.record-card :href="$viewUrl($assistance->id)"
                                              :eyebrow="'AST-' . str_pad($assistance->id, 3, '0', STR_PAD_LEFT)"
                                              :title="$assistance->name"
                                              :status="$assistance->status"
                                              action="Open item" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">Nothing in the catalogue yet</p>
                        </li>
                    @endforelse
                </ul>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left">
                        <thead>
                            {{-- FOUR COLUMNS (revision section 3.1)

                                 Type, Available and Allocated all went in
                                 the same pass. The first two no longer
                                 exist; Allocated was a sum of quantities
                                 and has nothing to add up. What is left
                                 fits the rule the other lists follow, so
                                 "For" moved into the panel with it. --}}
                            <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                                <th class="px-3 py-3">Assistance ID</th>
                                <th class="px-3 py-3">Assistance</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse ($assistances as $assistance)
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $assistance->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4 text-foreground">
                                        AST-{{ str_pad($assistance->id, 3, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="px-3 py-4 font-bold text-foreground">{{ $assistance->name }}</td>
                                    <td class="px-3 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$assistance->status] ?? $statusBadges['inactive'] }}">
                                            {{ ucfirst($assistance->status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <x-ui.button :href="$viewUrl($assistance->id)" variant="view" size="sm">View</x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-14 text-center">
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
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$selected->status] ?? $statusBadges['inactive'] }}">
                                {{ ucfirst($selected->status) }}
                            </span>
                        </div>

                        {{-- No "Open full page" here on purpose. The MAO
                             assistance routes are a Route::resource with
                             ->except(['show']), so mao.assistance.show does
                             not exist and linking to it would throw. The
                             catalogue item has no page of its own; this
                             panel is the whole record. --}}
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
                        {{-- Available and Allocated both held figures and
                             are gone with them. What is left is the one
                             fact about this item that is not a figure: how
                             many times the office has allocated it. --}}
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Allocations</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ $selected->allocations_count }}
                                {{ \Illuminate\Support\Str::plural('allocation', $selected->allocations_count) }}
                                @if ($selected->allocations_count === 0)
                                    <span class="text-xs text-muted-foreground">(not allocated yet)</span>
                                @endif
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
