@extends('layouts.app')

@section('title', 'Disaster Management')
@section('hideHeading', true)

@section('content')
<div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

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
                        <th class="px-5 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($disasters as $disaster)
                        <tr class="hover:bg-muted/60">
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
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('mao.disasters.edit', $disaster) }}"
                                       class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                        Edit
                                    </a>

                                    <form method="POST" action="{{ route('mao.disasters.archive', $disaster) }}"
                                          data-confirm="Are you sure you want to archive {{ $disaster->name }}? Reports and assistance already citing it are unaffected; farmers just cannot link new reports to it."
                                          data-confirm-title="Archive disaster event"
                                          data-confirm-action="Confirm Archive">
                                        @csrf @method('PUT')
                                        <button type="submit"
                                                class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
                                            Archive
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('mao.disasters.destroy', $disaster) }}"
                                          data-confirm="Are you sure you want to continue? {{ $disaster->name }} will be removed from active lists. Its data - and any reports that cite it - are kept for audit purposes, and this deletion will be recorded."
                                          data-confirm-title="Delete disaster event permanently"
                                          data-confirm-detail="This action cannot be undone from this screen."
                                          data-confirm-action="Delete Permanently"
                                          data-confirm-tone="danger">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                            Delete
                                        </button>
                                    </form>
                                </div>
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
@endsection
