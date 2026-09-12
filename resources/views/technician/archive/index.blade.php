@extends('layouts.app')

@section('title', 'Archive')
@section('heading', 'Archive')
@section('heading-fil', 'Imbakan')
@section('subheading', 'Reports that used to be on your desk and no longer are.')

@section('content')

{{--
    Not the Archivable trait used elsewhere in the system - nothing here was
    soft deleted, and there is nothing to restore. A report lands here for
    exactly two reasons: it was reassigned to a different technician after
    this one had already inspected it, or the MAO rejected it after this
    technician verified it. Read only, kept as a personal record.
--}}

<div class="space-y-4">

    <x-ui.card>
        <form method="GET" action="{{ route('technician.archive.index') }}"
              class="grid gap-3 sm:grid-cols-3 sm:items-end">

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="q">Search farmer</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="First or last name"
                       class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="reason">Reason</label>
                <select id="reason" name="reason"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">Both reasons</option>
                    <option value="reassigned" @selected(($filters['reason'] ?? '') === 'reassigned')>Reassigned away from you</option>
                    <option value="rejected" @selected(($filters['reason'] ?? '') === 'rejected')>Rejected by MAO</option>
                </select>
            </div>

            <div class="flex gap-2">
                <x-ui.button type="submit" class="flex-1 sm:flex-none">Apply</x-ui.button>
                <x-ui.button variant="outline" :href="route('technician.archive.index')">Clear</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card :padded="false">
        @if ($reports->isEmpty())
            <x-ui.empty title="Nothing archived"
                        icon="M3 7h18v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM3 7l1.2-2.4A1 1 0 015.1 4h13.8a1 1 0 01.9.6L21 7M10 12h4"
                        message="A report only appears here once it has left your queue after you inspected it - reassigned to somebody else, or later rejected by the office.">
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($reports as $report)
                    @php
                        $reassigned = $report->assigned_technician_id !== auth()->id();
                    @endphp
                    <li class="px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ $report->farmer?->full_name ?? 'Unknown farmer' }}
                                </p>
                                <p class="text-xs text-muted-foreground">{{ $report->reference }}</p>
                            </div>
                            <x-ui.status :value="$report->status" />
                        </div>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @if ($reassigned)
                                <x-ui.badge variant="outline">
                                    Reassigned to {{ $report->assignedTechnician?->display_name ?? 'nobody yet' }}
                                </x-ui.badge>
                            @endif
                            @if ($report->status === 'rejected')
                                <x-ui.badge variant="danger">Rejected by MAO</x-ui.badge>
                            @endif
                        </div>

                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Location not set' }}
                            &middot; You assessed
                            {{ $report->validation?->assessed_damage_percent !== null
                                ? round($report->validation->assessed_damage_percent) . '%'
                                : 'not recorded' }}
                        </p>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden sm:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Report</th>
                            <th>Farmer</th>
                            <th>Barangay</th>
                            <th>Reason</th>
                            <th>Your Assessment</th>
                            <th>Status</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($reports as $report)
                        @php
                            $reassigned = $report->assigned_technician_id !== auth()->id();
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap font-medium">
                                {{ $report->reference }}
                                <span class="block text-xs text-muted-foreground">
                                    {{ $report->updated_at?->format('M d, Y') }}
                                </span>
                            </td>

                            <td>
                                <span class="font-medium">{{ $report->farmer?->full_name ?? 'Unknown' }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ $report->farmer?->association?->name ?? 'No association' }}
                                </span>
                            </td>

                            <td class="text-muted-foreground">
                                {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? '-' }}
                            </td>

                            <td>
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($reassigned)
                                        <x-ui.badge variant="outline">
                                            Reassigned to {{ $report->assignedTechnician?->display_name ?? 'nobody yet' }}
                                        </x-ui.badge>
                                    @endif
                                    @if ($report->status === 'rejected')
                                        <x-ui.badge variant="danger">Rejected by MAO</x-ui.badge>
                                    @endif
                                </div>
                            </td>

                            <td class="whitespace-nowrap">
                                {{ $report->validation?->assessed_damage_percent !== null
                                    ? round($report->validation->assessed_damage_percent) . '%'
                                    : 'not recorded' }}
                            </td>

                            <td><x-ui.status :value="$report->status" /></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif

        @if ($reports->hasPages())
            <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
