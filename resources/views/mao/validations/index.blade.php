@extends('layouts.app')

@section('title', 'Validation Monitoring')

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
<div x-data="{ assigning: null }">

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Summary --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Unassigned" :value="number_format($summary['unassigned'])"
                     hint="Waiting for a technician"
                     :href="route('mao.validations.index', ['technician_id' => 'unassigned'])" />
        <x-ui.stat label="Assigned" :value="number_format($summary['assigned'])" hint="Inspection not started" />
        <x-ui.stat label="Under Verification" :value="number_format($summary['under_verification'])"
                     hint="Technician is on site" />
        <x-ui.stat label="Verified" :value="number_format($summary['verified'])" hint="Inspection submitted" />
    </div>

    <div class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6 space-y-5">

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {{-- This only filters the list below. Assignment itself is always a
                 row action, so the label says what the button actually does
                 rather than promising something it cannot deliver. --}}
            @if ($summary['unassigned'] > 0)
                @if (request('technician_id') === 'unassigned')
                    <a href="{{ route('mao.validations.index') }}"
                       class="shrink-0 rounded-lg border border-input px-5 py-2.5 text-center text-sm font-semibold text-foreground hover:bg-muted/60">
                        Show all reports
                    </a>
                @else
                    <a href="{{ route('mao.validations.index', ['technician_id' => 'unassigned']) }}"
                       class="shrink-0 rounded-lg bg-primary px-5 py-2.5 text-center text-sm font-semibold text-white hover:brightness-110">
                        Show unassigned only ({{ $summary['unassigned'] }})
                    </a>
                @endif
            @endif
        </div>

        @if ($technicians->isEmpty())
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60 px-4 py-3 text-sm text-amber-800">
                There are no active technician accounts yet. Create one under
                <a href="{{ route('mao.users.index') }}" class="font-medium underline">Users Management</a>
                before assigning reports.
            </div>
        @endif

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

            <select name="technician_id"
                    class="min-w-52 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
                <option value="">All Technicians</option>
                <option value="unassigned" @selected(request('technician_id') === 'unassigned')>Unassigned only</option>
                @foreach ($technicians as $technician)
                    <option value="{{ $technician->id }}" @selected(request('technician_id') == $technician->id)>
                        {{ $technician->full_name ?: $technician->username }}
                    </option>
                @endforeach
            </select>

            <div class="ml-auto flex items-center gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer..."
                       class="w-56 rounded-lg border-2 border-primary px-3 py-2.5 text-sm focus:outline-none">
                <button class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#0a2f15]">
                    Search
                </button>
                @if (request()->hasAny(['search', 'association_id', 'technician_id']))
                    <a href="{{ route('mao.validations.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
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
                        <th class="px-3 py-3">Assigned Technician</th>
                        <th class="px-3 py-3 text-center">Validation</th>
                        <th class="px-3 py-3 text-center">Action</th>
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
                                {{ $report->disasters->map(fn ($d) => ucwords(str_replace('_', ' ', $d->type)))->join(', ') ?: '-' }}
                            </td>
                            <td class="px-3 py-4 text-muted-foreground">{{ $report->created_at?->format('M d, Y') }}</td>
                            <td class="px-3 py-4">
                                @if ($report->assignedTechnician)
                                    <span class="text-foreground">
                                        {{ $report->assignedTechnician->full_name ?: $report->assignedTechnician->username }}
                                    </span>
                                @else
                                    <span class="text-muted-foreground">Not assigned</span>
                                @endif
                            </td>
                            <td class="px-3 py-4 text-center">
                                <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$report->status] ?? $statusBadges['pending'] }}">
                                    {{ ucwords(str_replace('_', ' ', $report->status)) }}
                                </span>
                            </td>
                            <td class="px-3 py-4">
                                <div class="flex items-center justify-center gap-3">
                                    <a href="{{ route('mao.damage-reports.show', $report) }}"
                                       class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                        View Details
                                    </a>

                                    @if (! in_array($report->status, ['under_verification', 'verified', 'approved'], true) && $technicians->isNotEmpty())
                                        <button type="button"
                                                @click="assigning = {
                                                    id: {{ $report->id }},
                                                    code: @js('DR-' . str_pad($report->id, 4, '0', STR_PAD_LEFT)),
                                                    farmer: @js($report->farmer?->full_name ?? 'Unknown'),
                                                    current: {{ $report->assigned_technician_id ?: 'null' }}
                                                }"
                                                class="rounded bg-green-100 px-4 py-1.5 text-xs font-semibold text-green-900 hover:bg-green-200">
                                            {{ $report->assignedTechnician ? 'Reassign' : 'Assign' }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-14 text-center">
                                <p class="text-sm font-medium text-muted-foreground">Nothing to validate yet</p>
                                <p class="mx-auto mt-1 max-w-md text-xs text-muted-foreground">
                                    A technician is assigned to a damage report, one report at a time, using the
                                    button on its row. Reports appear here only after a farmer submits one.
                                    Crop planting records are not inspected, so they never show up on this page.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 border-t border-border pt-5">{{ $reports->links() }}</div>
    </div>

    {{-- Assign technician --}}
    <div x-show="assigning" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Assign a technician</h3>
            <p class="mb-4 text-sm text-muted-foreground">
                Report <strong x-text="assigning?.code"></strong> for
                <strong x-text="assigning?.farmer"></strong>.
                The technician will see it on their dashboard and conduct the field inspection.
            </p>

            <form method="POST" :action="`{{ url('mao/validations') }}/${assigning?.id}/assign`">
                @csrf @method('PUT')

                <label class="mb-1.5 block text-sm font-medium text-foreground">Technician</label>
                <select name="assigned_technician_id" required
                        class="mb-5 w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Select technician</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}">
                            {{ $technician->full_name ?: $technician->username }}
                        </option>
                    @endforeach
                </select>

                <div class="flex gap-3">
                    <button type="button" @click="assigning = null"
                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                        Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
