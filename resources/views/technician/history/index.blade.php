@extends('layouts.app')

@section('title', 'Inspection History')
@section('heading', 'Reports & Analytics')
@section('heading-fil', 'Mga Ulat at Pagsusuri')
@section('subheading', 'Every inspection you have submitted, newest first.')

@section('content')

@include('technician.partials.analytics-tabs')

{{--
    Driven off the validations table rather than the report status.

    That matters: once the office approves or rejects a report the status
    moves on, but the inspection still happened and the technician who did
    it should still be able to find their own work. Reading from validations
    means nothing ever disappears from this page.

    Read only, deliberately. A submitted inspection is a record, and section
    52 says it stops being editable the moment it is submitted.
--}}

<div class="space-y-4">

    <x-ui.card :padded="false">
        <div class="border-b border-border px-4 pt-4 sm:px-5 sm:pt-5">
            <x-ui.filter-bar :fields="['q', 'severity']">
                <x-ui.input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Farmer name" class="sm:w-52" />

                <x-ui.select name="severity" placeholder="All severities" onchange="this.form.submit()"
                             :options="collect($severities)->mapWithKeys(
                                 fn ($band, $key) => [$key => $band['label'] . ' (' . $band['range'] . ')']
                             )"
                             :selected="$filters['severity'] ?? null" class="sm:w-52" />
            </x-ui.filter-bar>
        </div>

        @if ($inspections->isEmpty())
            <x-ui.empty title="No submitted inspections yet"
                        icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2"
                        message="Once you complete and submit a field inspection it is recorded here permanently, even after the office approves the report.">
                <x-ui.button variant="outline" :href="route('technician.validation.index')">Go to Validation</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ----------

                 The row itself is not a link. It used to be: the whole row
                 opened the inspection, with nothing on screen to say so, so
                 a technician who had not already discovered it had no way
                 of knowing the row did anything at all. The button below
                 each row is now the only way in, and it says where it goes,
                 the same way the MAO lists do. --}}
            <ul class="space-y-3 p-4 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                @foreach ($inspections as $inspection)
                    <li class="rounded-xl border border-border bg-card p-3.5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">{{ $inspection->damageReport?->full_reference }}</p>
                                <p class="truncate text-sm">
                                    {{ $inspection->damageReport?->farmer?->full_name ?? 'Unknown farmer' }}
                                </p>
                            </div>
                            <x-ui.status :value="$inspection->severity" />
                        </div>

                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ round($inspection->assessed_damage_percent) }}% assessed
                            &middot; {{ $inspection->photos_count }} photo{{ $inspection->photos_count === 1 ? '' : 's' }}
                            &middot; {{ $inspection->validated_at?->format('M d, Y') }}
                        </p>

                        {{-- 44px tall, full width: a thumb sized target that
                             names itself. --}}
                        <a href="{{ route('technician.history.show', $inspection) }}"
                           class="mt-3 flex h-11 items-center gap-2 rounded-lg bg-accent px-3
                                  text-sm font-semibold text-accent-foreground transition active:bg-muted">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 12h13M13 6l6 6-6 6"/>
                            </svg>
                            View Details
                            <span class="sr-only">
                                for {{ $inspection->damageReport?->full_reference }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP: one folded row per farmer ----------
                 Same shape as the MAO's Crop Management Report. Opening a
                 farmer shows every inspection of theirs. --}}
            @php
                $rows   = $inspections->getCollection();
                $groups = $rows->groupBy(fn ($row) => (int) $row->damageReport?->farmer_id);
                $openId = $groups->count() === 1 ? $groups->keys()->first() : null;
            @endphp

            <div class="hidden p-4 sm:p-5 lg:block">
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left">
                        <thead class="bg-muted/70">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <th class="px-4 py-3">Farmer ID</th>
                                <th class="px-4 py-3">Farmer</th>
                                <th class="px-4 py-3">Association</th>
                                <th class="px-4 py-3 text-center">Number of Reports</th>
                                <th class="w-12 px-4 py-3"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm" x-data="{ open: {{ $openId ?? 'null' }} }">
                            @foreach ($groups as $farmerId => $group)
                                @php $farmer = $group->first()->damageReport?->farmer; @endphp
                                <x-record.farmer-block :farmer="$farmer"
                                                       :count="$group->count()"
                                                       :group-id="$farmerId"
                                                       :headers="[['Report ID'], ['Barangay'], ['Submitted'], ['Severity'], ['Action', 'text-right']]">
                                    @foreach ($group as $inspection)
                                        @php $report = $inspection->damageReport; @endphp
                                        <tr class="hover:bg-muted/60">
                                            <td class="whitespace-nowrap px-4 py-4 font-medium text-foreground">{{ $report?->full_reference ?? '-' }}</td>
                                            <td class="px-4 py-4 text-muted-foreground">
                                                {{ $report?->reportedBarangay?->name ?? $report?->farmer?->barangay?->name ?? '-' }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-4 text-muted-foreground">
                                                {{ $inspection->validated_at?->format('M d, Y g:i A') }}
                                            </td>
                                            <td class="px-4 py-4"><x-ui.status :value="$inspection->severity" /></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                                <x-ui.button size="sm" variant="view"
                                                             :href="route('technician.history.show', $inspection)">
                                                    View Details
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </x-record.farmer-block>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($inspections->isNotEmpty())
            <x-slot:footer>
                @php [$from, $to, $total] = \App\Support\FarmerGroups::range($inspections); @endphp
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">
                        Showing {{ number_format($from) }} to {{ number_format($to) }}
                        of {{ number_format($total) }} {{ \Illuminate\Support\Str::plural('farmer', $total) }}
                    </p>
                    @if ($inspections->hasPages())
                        <div>{{ $inspections->links() }}</div>
                    @endif
                </div>
            </x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
