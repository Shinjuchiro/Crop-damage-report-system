@extends('layouts.app')

@section('title', 'Crop Management')
@section('hideHeading', true)

@section('content')
<div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

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
                        <th class="px-5 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($crops as $crop)
                        @php
                            $used = $crop->main_crops_count
                                  + $crop->planting_record_crops_count
                                  + $crop->damage_report_crops_count;
                        @endphp
                        <tr class="hover:bg-muted/60">
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
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('mao.crops.edit', $crop) }}"
                                       class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                        Edit
                                    </a>

                                    <form method="POST" action="{{ route('mao.crops.archive', $crop) }}"
                                          data-confirm="Are you sure you want to archive {{ $crop->name }}? Existing records that already use it are unaffected; it just stops appearing as a choice on new forms."
                                          data-confirm-title="Archive crop"
                                          data-confirm-action="Confirm Archive">
                                        @csrf @method('PUT')
                                        <button type="submit"
                                                class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
                                            Archive
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('mao.crops.destroy', $crop) }}"
                                          data-confirm="Are you sure you want to continue? {{ $crop->name }} will be removed from active lists. Its data - and any records that cite it - are kept for audit purposes, and this deletion will be recorded."
                                          data-confirm-title="Delete crop permanently"
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
@endsection
