@extends('layouts.app')

@section('title', 'Crop Management')
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

    {{-- List + detail, same pattern as every other MAO list page: side by
         side from lg up, one half at a time on a phone. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Crop Management" description="The crop types farmers can select in their profile, planting records and damage reports.">
                <x-slot:actions>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('mao.archive.index', ['type' => 'crops']) }}"
                           class="text-sm text-muted-foreground hover:text-foreground">
                            View archived
                        </a>
                        <x-ui.button :href="route('mao.crops.create')">+ Add Crop</x-ui.button>
                    </div>
                </x-slot:actions>

                {{-- Filter. No visible Search button - Enter in the field submits. --}}
                <form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search crop name"
                                class="sm:max-w-sm" />
                    @if (request('search'))
                        <a href="{{ route('mao.crops.index') }}"
                           class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                <div class="overflow-hidden rounded-xl border border-border">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Crop</th>
                                    <th class="px-5 py-3 font-medium">Type</th>
                                    <th class="px-5 py-3 text-center font-medium">Farmer profiles</th>
                                    <th class="px-5 py-3 text-center font-medium">Planting records</th>
                                    <th class="px-5 py-3 text-center font-medium">Damage reports</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @forelse ($crops as $crop)
                                    <tr class="hover:bg-muted/60 {{ $selected?->id === $crop->id ? 'bg-muted/60' : '' }}">
                                        <td class="px-5 py-3 font-medium text-foreground">{{ $crop->name }}</td>
                                        <td class="px-5 py-3">
                                            @if ($crop->is_hvcc)
                                                <span class="inline-flex rounded-full bg-lime-50 px-2.5 py-1 text-xs font-medium text-lime-800 ring-1 ring-inset ring-lime-200">
                                                    HVCC
                                                </span>
                                            @else
                                                <span class="text-xs text-muted-foreground">Standard</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $crop->main_crops_count }}</td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $crop->planting_record_crops_count }}</td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $crop->damage_report_crops_count }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <x-ui.button :href="$viewUrl($crop->id)" variant="view" size="sm">View</x-ui.button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-12 text-center">
                                            <p class="text-sm font-medium text-muted-foreground">No crops found</p>
                                            <p class="mt-1 text-xs text-muted-foreground">Add the crops grown in Tanza so farmers can select them.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">{{ $crops->links() }}</div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a crop from the list to view its details.">
            @if ($selected)
                @php
                    $used = $selected->main_crops_count
                          + $selected->planting_record_crops_count
                          + $selected->damage_report_crops_count;
                @endphp

                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Crop</p>
                        <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $selected->name }}</h3>
                        @if ($selected->is_hvcc)
                            <span class="mt-1 inline-flex rounded-full bg-lime-50 px-2.5 py-1 text-xs font-medium text-lime-800 ring-1 ring-inset ring-lime-200">
                                HVCC
                            </span>
                        @else
                            <span class="mt-1 block text-xs text-muted-foreground">Standard crop</span>
                        @endif
                    </div>
                </div>

                <div class="border-t border-border pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Currently used in</p>
                    <dl class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-muted/50 px-2 py-3">
                            <dd class="text-lg font-bold text-foreground">{{ $selected->main_crops_count }}</dd>
                            <dt class="mt-0.5 text-xs text-muted-foreground">Farmer profiles</dt>
                        </div>
                        <div class="rounded-lg bg-muted/50 px-2 py-3">
                            <dd class="text-lg font-bold text-foreground">{{ $selected->planting_record_crops_count }}</dd>
                            <dt class="mt-0.5 text-xs text-muted-foreground">Planting records</dt>
                        </div>
                        <div class="rounded-lg bg-muted/50 px-2 py-3">
                            <dd class="text-lg font-bold text-foreground">{{ $selected->damage_report_crops_count }}</dd>
                            <dt class="mt-0.5 text-xs text-muted-foreground">Damage reports</dt>
                        </div>
                    </dl>
                    @if ($used > 0)
                        <p class="mt-3 text-xs text-muted-foreground">
                            Archiving this crop will not affect the {{ $used }} existing
                            {{ \Illuminate\Support\Str::plural('record', $used) }}
                            above - it just stops appearing as a choice on new forms.
                        </p>
                    @endif
                </div>

                <div class="mt-5 flex gap-2 border-t border-border pt-4">
                    <a href="{{ route('mao.crops.edit', $selected) }}"
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
            <h3 class="mb-2 text-lg font-semibold text-foreground">Archive this crop?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                <strong x-text="archiving?.name"></strong> will stop appearing as a choice on new forms. Existing
                records that already use it are unaffected, and it can be restored at any time from the Archive page.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/crops') }}/${archiving?.id}/archive`" class="flex-1">
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
