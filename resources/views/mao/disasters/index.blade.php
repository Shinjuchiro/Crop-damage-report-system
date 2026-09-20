@extends('layouts.app')

@section('title', 'Disaster Management')
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
            <x-ui.card title="Disaster Management" description="The disaster events farmers can cite when reporting crop damage.">
                <x-slot:actions>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('mao.archive.index', ['type' => 'disasters']) }}"
                           class="text-sm text-muted-foreground hover:text-foreground">
                            View archived
                        </a>
                        <x-ui.button :href="route('mao.disasters.create')">+ Add Disaster Event</x-ui.button>
                    </div>
                </x-slot:actions>

                {{-- Filters. No visible button - Enter in the text field, or
                     choosing a type, submits the form. --}}
                <form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="type" placeholder="All types" onchange="this.form.submit()"
                                 :options="collect($types)->mapWithKeys(fn ($t) => [$t => ucwords(str_replace('_', ' ', $t))])"
                                 :selected="request('type')" class="sm:w-48" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search event name"
                                class="sm:min-w-[14rem] sm:flex-1" />

                    @if (request('search') || request('type'))
                        <a href="{{ route('mao.disasters.index') }}"
                           class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                <div class="overflow-hidden rounded-xl border border-border">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Event</th>
                                    <th class="px-5 py-3 font-medium">Type</th>
                                    <th class="px-5 py-3 font-medium">Period</th>
                                    <th class="px-5 py-3 text-center font-medium">Damage reports</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @forelse ($disasters as $disaster)
                                    <tr class="hover:bg-muted/60 {{ $selected?->id === $disaster->id ? 'bg-muted/60' : '' }}">
                                        <td class="px-5 py-3 font-medium text-foreground">{{ $disaster->name }}</td>
                                        <td class="px-5 py-3">
                                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200">
                                                {{ ucwords(str_replace('_', ' ', $disaster->type)) }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-muted-foreground">
                                            {{ $disaster->date_start?->format('M d, Y') }}
                                            @if ($disaster->date_end)
                                                &ndash; {{ $disaster->date_end->format('M d, Y') }}
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">
                                            {{ $disaster->damage_reports_count }}
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <x-ui.button :href="$viewUrl($disaster->id)" variant="view" size="sm">View</x-ui.button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-12 text-center">
                                            <p class="text-sm font-medium text-muted-foreground">No disaster events recorded</p>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Add a typhoon, flood or other event so farmers can attach it to their damage reports.
                                            </p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">{{ $disasters->links() }}</div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select an event from the list to view its details.">
            @if ($selected)
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Disaster Event</p>
                        <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $selected->name }}</h3>
                        <span class="mt-1 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200">
                            {{ ucwords(str_replace('_', ' ', $selected->type)) }}
                        </span>
                    </div>
                </div>

                <div class="border-t border-border pt-4">
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Period</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ $selected->date_start?->format('M d, Y') }}
                                @if ($selected->date_end)
                                    &ndash; {{ $selected->date_end->format('M d, Y') }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Damage reports citing this event</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ $selected->damage_reports_count }}</dd>
                        </div>
                    </dl>
                    @if ($selected->damage_reports_count > 0)
                        <p class="mt-3 text-xs text-muted-foreground">
                            Archiving this event will not affect those reports - farmers just cannot link new reports to it.
                        </p>
                    @endif
                </div>

                <div class="mt-5 flex gap-2 border-t border-border pt-4">
                    <a href="{{ route('mao.disasters.edit', $selected) }}"
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
    <div x-show="archiving" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Archive this disaster event?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                <strong x-text="archiving?.name"></strong> will be closed out. Reports and assistance already citing
                it are unaffected; farmers just cannot link new reports to it, and it can be restored at any time
                from the Archive page.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/disasters') }}/${archiving?.id}/archive`" class="flex-1">
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
