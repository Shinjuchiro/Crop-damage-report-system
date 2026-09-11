@extends('layouts.app')

@section('title', 'Crop Damage Monitoring')

@php
    $statusBadges = [
        'pending'            => 'bg-secondary text-foreground',
        'assigned'           => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'under_verification' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'verified'           => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'flagged'            => 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
        'approved'           => 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300',
        'rejected'           => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ];
@endphp

@section('content')

    {{-- Summary --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat label="Total Reports" :value="number_format($summary['total'])" />
        <x-ui.stat label="Pending" :value="number_format($summary['pending'])" hint="Not yet assigned" />
        <x-ui.stat label="Under Verification" :value="number_format($summary['under_verification'])" />
        <x-ui.stat label="Verified" :value="number_format($summary['verified'])" />
        <x-ui.stat label="Total Affected Area" :value="number_format($summary['affected_area'], 2)" suffix="ha" />
    </div>

<div class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6 space-y-5">

    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <select name="association_id"
                class="min-w-52 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Association</option>
            @foreach ($associations as $association)
                <option value="{{ $association->id }}" @selected(request('association_id') == $association->id)>
                    {{ $association->name }}
                </option>
            @endforeach
        </select>

        <select name="disaster_id"
                class="min-w-52 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Disasters</option>
            @foreach ($disasters as $disaster)
                <option value="{{ $disaster->id }}" @selected(request('disaster_id') == $disaster->id)>
                    {{ $disaster->name }}
                </option>
            @endforeach
        </select>

        <select name="status"
                class="min-w-44 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Status</option>
            @foreach ($statuses as $value)
                <option value="{{ $value }}" @selected(request('status') === $value)>
                    {{ ucwords(str_replace('_', ' ', $value)) }}
                </option>
            @endforeach
        </select>

        <div class="ml-auto flex items-center gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer..."
                   class="w-56 rounded-lg border-2 border-primary px-3 py-2.5 text-sm focus:outline-none">
            <button class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#0a2f15]">
                Search
            </button>
            @if (request()->hasAny(['search', 'association_id', 'disaster_id', 'status']))
                <a href="{{ route('mao.damage-reports.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                    <th class="px-3 py-3">Report ID</th>
                    <th class="px-3 py-3">Farmer</th>
                    <th class="px-3 py-3">Association</th>
                    <th class="px-3 py-3">Disaster Type</th>
                    <th class="px-3 py-3">Date Reported</th>
                    <th class="px-3 py-3">Damaged Area</th>
                    <th class="px-3 py-3 text-center">Status</th>
                    <th class="px-3 py-3 text-center">Validation</th>
                    <th class="px-3 py-3 text-center">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-sm">
                @forelse ($reports as $report)
                    <tr class="hover:bg-muted/60">
                        <td class="px-3 py-4 text-foreground">DR-{{ str_pad($report->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-3 py-4">
                            <p class="font-medium text-foreground">{{ $report->farmer?->full_name ?? 'Unknown' }}</p>
                            <p class="text-xs text-muted-foreground">{{ $report->farmer?->barangay?->name }}</p>
                        </td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $report->farmer?->association?->name ?? '-' }}</td>
                        <td class="px-3 py-4 text-muted-foreground">
                            @forelse ($report->disasters as $disaster)
                                <span class="block">
                                    {{ ucwords(str_replace('_', ' ', $disaster->type)) }}
                                    <span class="text-xs text-muted-foreground">{{ $disaster->name }}</span>
                                </span>
                            @empty
                                -
                            @endforelse
                        </td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $report->created_at?->format('M d, Y') }}</td>
                        <td class="px-3 py-4 tabular-nums text-muted-foreground">
                            {{ number_format((float) $report->crops_sum_damaged_area_hectares, 2) }} ha
                        </td>
                        <td class="px-3 py-4 text-center">
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$report->status] ?? $statusBadges['pending'] }}">
                                {{ ucwords(str_replace('_', ' ', $report->status)) }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-center">
                            @if ($report->validation?->validated_at)
                                <span class="inline-flex rounded bg-green-100 px-3 py-1 text-xs font-medium text-green-800">Validated</span>
                            @elseif ($report->validation)
                                <span class="inline-flex rounded bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">In progress</span>
                            @else
                                <span class="inline-flex rounded bg-secondary px-3 py-1 text-xs font-medium text-foreground">Pending</span>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-center">
                            <a href="{{ route('mao.damage-reports.show', $report) }}"
                               class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                View Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-14 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No damage reports yet</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Reports appear here as soon as farmers submit them.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 border-t border-border pt-5">{{ $reports->links() }}</div>
</div>
@endsection
