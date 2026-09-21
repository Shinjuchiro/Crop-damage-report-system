@extends('layouts.app')

@section('title', 'Farmer Profile')
@section('heading', $farmer->full_name)
@section('subheading', 'Complete record of this farmer and everything attached to them.')

@php
    $accountBadges = [
        'active'   => 'bg-accent text-green-800 ring-green-200',
        'pending'  => 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200 ring-amber-200',
        'inactive' => 'bg-secondary text-foreground ring-slate-200',
        'rejected' => 'bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-200 ring-red-200',
    ];

    $reportBadges = [
        'pending'            => 'bg-secondary text-foreground ring-slate-200',
        'assigned'           => 'bg-sky-50 text-sky-800 dark:bg-sky-950/60 dark:text-sky-200 ring-sky-200',
        'under_verification' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200 ring-amber-200',
        'verified'           => 'bg-accent text-green-800 ring-green-200',
        'flagged'            => 'bg-orange-50 text-orange-800 ring-orange-200',
        'approved'           => 'bg-lime-50 text-lime-800 ring-lime-200',
        'rejected'           => 'bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-200 ring-red-200',
    ];

    $accountStatus = $farmer->user?->status ?? 'pending';
@endphp

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-green-800 text-lg font-bold text-white">
                {{ strtoupper(substr($farmer->first_name, 0, 1) . substr($farmer->last_name, 0, 1)) }}
            </div>
            <div>
                <p class="text-lg font-bold text-foreground">{{ $farmer->full_name }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ $farmer->barangay?->name ?? 'No barangay' }}
                    &middot; {{ $farmer->association?->name ?? 'No association' }}
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $accountBadges[$accountStatus] ?? $accountBadges['pending'] }}">
                        Account: {{ ucfirst($accountStatus) }}
                    </span>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $farmer->activity_status === 'active' ? 'bg-lime-50 text-lime-800 ring-lime-200' : 'bg-secondary text-muted-foreground ring-slate-200' }}">
                        Activity: {{ ucfirst($farmer->activity_status) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:items-end">
            <p class="text-xs text-muted-foreground">
                Last activity:
                <span class="font-medium text-foreground">
                    {{ $farmer->last_activity_date?->format('M d, Y') ?? 'Never' }}
                </span>
            </p>
            @if ($accountStatus === 'pending')
                <a href="{{ route('mao.membership-applications.show', $farmer) }}"
                   class="rounded-lg bg-green-800 px-4 py-2 text-sm font-semibold text-white hover:bg-green-900">
                    Review Registration
                </a>
            @endif
            <a href="{{ route('mao.farmers.index') }}"
               class="rounded-lg border border-input px-4 py-2 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                Back to Farmers
            </a>
        </div>
    </div>

    {{-- Personal information --}}
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Personal Information</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Full Name</dt><dd class="text-right font-bold text-foreground">{{ $farmer->full_name }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Date of Birth</dt><dd class="text-right font-bold text-foreground">{{ $farmer->date_of_birth?->format('M d, Y') ?? '-' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Sex</dt><dd class="text-right font-bold capitalize text-foreground">{{ $farmer->sex }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Contact Number</dt><dd class="text-right font-bold text-foreground">{{ $farmer->user?->phone_number ?? '-' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Username</dt><dd class="text-right font-bold text-foreground">{{ $farmer->user?->username ?? '-' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Email</dt><dd class="text-right font-bold break-all text-foreground">{{ $farmer->user?->email ?? '-' }}</dd></div>
            <div class="flex justify-between gap-3 sm:col-span-2 lg:col-span-3">
                <dt class="shrink-0 text-muted-foreground">Complete Address</dt>
                <dd class="text-right font-bold text-foreground">{{ $farmer->address }}</dd>
            </div>
        </dl>
        {{-- The password is never displayed --}}
    </div>

    {{-- Farm ownership and information --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farm Ownership</h3>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3">
                    <dt class="shrink-0 text-muted-foreground">Farmer Type</dt>
                    <dd class="text-right font-bold text-foreground">
                        {{ $farmer->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner' }}
                    </dd>
                </div>

                @if ($farmer->ownership_type === 'tenant')
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Land Owner Name</dt><dd class="text-right font-bold text-foreground">{{ $farmer->landowner_name ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Land Owner Contact</dt><dd class="text-right font-bold text-foreground">{{ $farmer->landowner_contact ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Land Owner Location</dt><dd class="text-right font-bold text-foreground">{{ $farmer->landowner_location ?: '-' }}</dd></div>
                @else
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-muted-foreground">Barangay Certificate</dt>
                        <dd class="text-right">
                            @if ($farmer->barangay_certificate_path)
                                <a href="{{ asset('storage/' . $farmer->barangay_certificate_path) }}" target="_blank"
                                   class="font-bold text-green-800 hover:underline">View Barangay Certificate</a>
                            @else
                                <span class="text-muted-foreground">Not provided</span>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farm Information</h3>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3">
                    <dt class="shrink-0 text-muted-foreground">Farm Size</dt>
                    <dd class="text-right font-bold text-foreground">
                        {{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : 'Not provided' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="shrink-0 text-muted-foreground">Main Crops</dt>
                    <dd class="text-right font-bold text-foreground">
                        @forelse ($farmer->mainCrops as $mainCrop)
                            {{ $mainCrop->crop?->name }}{{ $mainCrop->crop_specify ? " ({$mainCrop->crop_specify})" : '' }}@if (! $loop->last), @endif
                        @empty
                            -
                        @endforelse
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Planting records --}}
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-green-800">Crop Planting Records</h3>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Submitting a planting record is what keeps a farmer counted as active.
            </p>
        </div>

        @if ($farmer->plantingRecords->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-6 py-3 font-medium">Date Submitted</th>
                            <th class="px-6 py-3 font-medium">Crops</th>
                            <th class="px-6 py-3 text-right font-medium">Total Area</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($farmer->plantingRecords->sortByDesc('date_submitted') as $record)
                            <tr class="hover:bg-muted/60">
                                <td class="px-6 py-3 text-foreground">{{ $record->date_submitted?->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-muted-foreground">
                                    @forelse ($record->crops as $recordCrop)
                                        {{ $recordCrop->crop?->name }}
                                        ({{ $recordCrop->area_hectares }} ha, planted
                                        {{ $recordCrop->date_planted?->format('M d, Y') }})@if (! $loop->last); @endif
                                    @empty
                                        -
                                    @endforelse
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-foreground">
                                    {{ number_format($record->crops->sum('area_hectares'), 2) }} ha
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-sm font-medium text-muted-foreground">No planting records</p>
                <p class="mt-1 text-xs text-muted-foreground">This farmer has not submitted a crop planting activity yet.</p>
            </div>
        @endif
    </div>

    {{-- Damage reports --}}
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-green-800">Crop Damage Reports</h3>
        </div>

        @if ($farmer->damageReports->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-6 py-3 font-medium">Report</th>
                            <th class="px-6 py-3 font-medium">Disaster</th>
                            <th class="px-6 py-3 font-medium">Crops</th>
                            <th class="px-6 py-3 text-right font-medium">Damaged Area</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">Technician Assessment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($farmer->damageReports->sortByDesc('created_at') as $report)
                            <tr class="hover:bg-muted/60">
                                <td class="px-6 py-3">
                                    <p class="font-medium text-foreground">DR-{{ str_pad($report->id, 4, '0', STR_PAD_LEFT) }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $report->created_at?->format('M d, Y') }}</p>
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">
                                    {{ $report->disasters->pluck('name')->join(', ') ?: '-' }}
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">
                                    {{ $report->crops->map(fn ($c) => $c->crop?->name)->filter()->join(', ') ?: '-' }}
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-foreground">
                                    {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha
                                </td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $reportBadges[$report->status] ?? $reportBadges['pending'] }}">
                                        {{ ucwords(str_replace('_', ' ', $report->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">
                                    @if ($report->validation?->validated_at)
                                        {{ ucfirst($report->validation->severity ?? '-') }},
                                        {{ $report->validation->assessed_damage_percent }}%
                                        <span class="block text-xs text-muted-foreground">
                                            by {{ $report->validation->technician?->username ?? 'technician' }}
                                        </span>
                                    @else
                                        <span class="text-xs text-muted-foreground">Not yet verified</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-sm font-medium text-muted-foreground">No damage reports</p>
                <p class="mt-1 text-xs text-muted-foreground">This farmer has not reported any crop damage.</p>
            </div>
        @endif
    </div>

    {{-- Assistance received --}}
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-green-800">Assistance Received</h3>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Distributed by the farmer's association from an allocation made by the MAO.
            </p>
        </div>

        @if ($farmer->assistanceDistributions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-6 py-3 font-medium">Assistance</th>
                            <th class="px-6 py-3 text-right font-medium">Quantity</th>
                            <th class="px-6 py-3 font-medium">Distributed</th>
                            <th class="px-6 py-3 font-medium">Distribution Status</th>
                            <th class="px-6 py-3 font-medium">Farmer Confirmation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($farmer->assistanceDistributions->sortByDesc('distributed_at') as $distribution)
                            <tr class="hover:bg-muted/60">
                                <td class="px-6 py-3 font-medium text-foreground">
                                    {{ $distribution->allocation?->display_name ?? 'Assistance' }}
                                    @if ($distribution->in_kind_description)
                                        <span class="block text-xs font-normal text-muted-foreground">{{ $distribution->in_kind_description }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-foreground">
                                    {{ $distribution->quantity !== null ? number_format($distribution->quantity, 2) : '-' }}
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">{{ $distribution->distributed_at?->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-muted-foreground">
                                    {{ ucwords(str_replace('_', ' ', $distribution->distribution_status)) }}
                                </td>
                                <td class="px-6 py-3">
                                    @php $receipt = $distribution->receipt_status; @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset
                                        {{ $receipt === 'confirmed_received'
                                            ? 'bg-accent text-green-800 ring-green-200'
                                            : ($receipt === 'not_received'
                                                ? 'bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-200 ring-red-200'
                                                : 'bg-secondary text-muted-foreground ring-slate-200') }}">
                                        {{ ucwords(str_replace('_', ' ', $receipt)) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-sm font-medium text-muted-foreground">No assistance recorded</p>
                <p class="mt-1 text-xs text-muted-foreground">Nothing has been distributed to this farmer yet.</p>
            </div>
        @endif
    </div>
</div>
@endsection
