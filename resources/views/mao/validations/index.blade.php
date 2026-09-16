@extends('layouts.app')

@section('title', 'Validation Monitoring')
@section('hideHeading', true)

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

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
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

    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a row's View Details is
         followed) the detail panel full-screen with its own Back link. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Validation Monitoring">
                <x-slot:actions>
                    {{-- This only filters the list below. Assignment itself is
                         always a row action. --}}
                    @if ($summary['unassigned'] > 0)
                        @if (request('technician_id') === 'unassigned')
                            <a href="{{ route('mao.validations.index') }}"
                               class="text-sm text-muted-foreground hover:text-foreground">
                                Show all reports
                            </a>
                        @else
                            <a href="{{ route('mao.validations.index', ['technician_id' => 'unassigned']) }}"
                               class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                                Show unassigned only ({{ $summary['unassigned'] }})
                            </a>
                        @endif
                    @endif
                </x-slot:actions>

                @if ($technicians->isEmpty())
                    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60 px-4 py-3 text-sm text-amber-800">
                        There are no active technician accounts yet. Create one under
                        <a href="{{ route('mao.users.index') }}" class="font-medium underline">Users Management</a>
                        before assigning reports.
                    </div>
                @endif

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                 :options="$associations->pluck('name', 'id')" :selected="request('association_id')" class="sm:w-52" />

                    <x-ui.select name="technician_id" placeholder="All Technicians" onchange="this.form.submit()"
                                 :options="collect(['unassigned' => 'Unassigned only'])->union($technicians->mapWithKeys(fn ($t) => [$t->id => $t->full_name ?: $t->username]))"
                                 :selected="request('technician_id')" class="sm:w-52" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer"
                                class="sm:min-w-[12rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'association_id', 'technician_id']))
                        <a href="{{ route('mao.validations.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
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
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $report->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4 text-foreground">{{ $report->reference }}</td>
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
                                        <div class="flex items-center justify-center gap-2">
                                            <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>

                                            @if (! in_array($report->status, ['under_verification', 'verified', 'approved'], true) && $technicians->isNotEmpty())
                                                <button type="button"
                                                        @click="assigning = {
                                                            id: {{ $report->id }},
                                                            code: @js($report->reference),
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
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a report from the list to view its full details.">
            @if ($selected)
                @php $report = $selected; $farmer = $report->farmer; $validation = $report->validation; @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $report->reference }}</p>
                    <h3 class="mt-0.5 text-lg font-semibold text-foreground">
                        <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                    </h3>
                    <p class="text-sm text-muted-foreground">{{ $farmer->association?->name ?? '-' }} &middot; {{ $farmer->barangay?->name ?? '-' }}</p>
                    <span class="mt-2 inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $statusBadges[$report->status] ?? $statusBadges['pending'] }}">
                        {{ ucwords(str_replace('_', ' ', $report->status)) }}
                    </span>
                </div>

                <div class="space-y-4 border-t border-border pt-4 text-sm">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cause of Damage</p>
                        <p class="mb-2 rounded-lg bg-muted px-3 py-2 text-xs font-medium text-foreground">{{ $report->damage_cause_label }}</p>
                        @forelse ($report->disasters as $disaster)
                            <p class="text-xs text-foreground">{{ $disaster->name }} <span class="text-muted-foreground">({{ ucwords(str_replace('_', ' ', $disaster->type)) }})</span></p>
                        @empty
                            <p class="text-xs text-muted-foreground">No declared event attached.</p>
                        @endforelse
                    </div>

                    <div class="border-t border-border pt-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Damaged Crops</p>
                            <p class="text-xs text-muted-foreground">Total: <span class="font-semibold text-foreground">{{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha</span></p>
                        </div>
                        @forelse ($report->crops as $reportCrop)
                            <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                                <p class="font-medium text-foreground">{{ $reportCrop->crop?->name ?? '-' }} &middot; {{ $reportCrop->damaged_area_hectares }} ha</p>
                                <p class="text-muted-foreground">Est. {{ $reportCrop->estimated_damage_percent }}% damage</p>
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground">No crops recorded.</p>
                        @endforelse
                    </div>

                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farmer's Damage Photos</p>
                        @if ($report->photos->isNotEmpty())
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($report->photos as $photo)
                                    <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" class="block overflow-hidden rounded-lg border border-border">
                                        <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Damage photo" class="h-16 w-full object-cover transition hover:scale-105">
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-muted-foreground">The farmer did not attach any photos.</p>
                        @endif
                    </div>

                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Technician Inspection</p>
                        @if ($validation)
                            <dl class="space-y-1.5">
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Technician</dt><dd class="font-medium text-foreground">{{ $validation->technician?->full_name ?: $validation->technician?->username ?? '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Severity</dt><dd class="font-medium capitalize text-foreground">{{ $validation->severity ?? 'Not yet assessed' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Assessed Damage</dt><dd class="font-medium text-foreground">{{ $validation->assessed_damage_percent !== null ? $validation->assessed_damage_percent . '%' : '-' }}</dd></div>
                                <div><dt class="text-muted-foreground">Notes</dt><dd class="font-medium text-foreground">{{ $validation->notes ?: 'No notes recorded.' }}</dd></div>
                            </dl>
                        @else
                            <p class="text-xs text-muted-foreground">No inspection has been started for this report yet.</p>
                        @endif
                    </div>

                    <div class="border-t border-border pt-4">
                        <a href="{{ route('mao.damage-reports.index', ['selected' => $report->id]) }}"
                           class="text-xs font-semibold text-green-800 hover:underline">
                            Open in Damage Report Monitoring &rarr;
                        </a>
                    </div>
                </div>

                @if (! in_array($report->status, ['under_verification', 'verified', 'approved'], true) && $technicians->isNotEmpty())
                    <div class="mt-5 border-t border-border pt-4">
                        <button type="button"
                                @click="assigning = {
                                    id: {{ $report->id }},
                                    code: @js($report->reference),
                                    farmer: @js($farmer->full_name ?? 'Unknown'),
                                    current: {{ $report->assigned_technician_id ?: 'null' }}
                                }"
                                class="w-full rounded-lg bg-green-800 px-3 py-2 text-sm font-semibold text-white hover:bg-green-900">
                            {{ $report->assignedTechnician ? 'Reassign Technician' : 'Assign Technician' }}
                        </button>
                    </div>
                @endif
            @endif
        </x-ui.detail-panel>
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
