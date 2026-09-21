@extends('layouts.app')

@section('title', 'Farmers Directory')
@section('hideHeading', true)

@php
    $accountBadges = [
        'active'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'pending'  => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'inactive' => 'bg-secondary text-foreground',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ];

    $reportBadges = [
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
<div>

    {{-- Summary --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        <x-ui.stat label="Total Farmers" :value="number_format($summary['total'])" />
        <x-ui.stat label="Verified" :value="number_format($summary['verified'])" />
        <x-ui.stat label="Pending" :value="number_format($summary['pending'])"
                     :href="route('mao.membership-applications.index', ['status' => 'pending'])" />
        <x-ui.stat label="Active" :value="number_format($summary['active'])" hint="With recent activity" />
        <x-ui.stat label="Inactive" :value="number_format($summary['inactive'])" />
    </div>

    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a row's View is followed)
         the detail panel full-screen with its own Back link. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Farmers Directory" description="Every approved farmer in the system and everything attached to them.">
                <x-slot:actions>
                    <x-ui.button :href="route('mao.associations.index')" size="sm">
                        Manage Associations
                    </x-ui.button>
                </x-slot:actions>

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                 :options="$associations->pluck('name', 'id')" :selected="request('association_id')" class="sm:w-48" />

                    <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                                 :options="$barangays->pluck('name', 'id')" :selected="request('barangay_id')" class="sm:w-48" />

                    <x-ui.select name="activity_status" placeholder="Any Status" onchange="this.form.submit()"
                                 :options="['active' => 'Active', 'inactive' => 'Inactive']"
                                 :selected="request('activity_status')" class="sm:w-40" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer"
                                class="sm:min-w-[14rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'association_id', 'barangay_id', 'activity_status']))
                        <a href="{{ route('mao.farmers.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                                <th class="px-3 py-3">Farmer ID</th>
                                <th class="px-3 py-3">Name</th>
                                <th class="px-3 py-3">Association</th>
                                <th class="px-3 py-3">Farm Size</th>
                                <th class="px-3 py-3">Location</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse ($farmers as $farmer)
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $farmer->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4 text-foreground">
                                        FARM-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="px-3 py-4">
                                        <p class="font-bold text-foreground">{{ $farmer->full_name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $farmer->user?->username }}</p>
                                    </td>
                                    <td class="px-3 py-4 text-muted-foreground">{{ $farmer->association?->name ?? '-' }}</td>
                                    <td class="px-3 py-4 text-muted-foreground">
                                        {{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : '-' }}
                                    </td>
                                    <td class="px-3 py-4 text-muted-foreground">{{ $farmer->barangay?->name ?? '-' }}</td>
                                    <td class="px-3 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $farmer->activity_status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                            {{ $farmer->activity_status === 'active' ? 'Active' : 'In-Active' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <x-ui.button :href="$viewUrl($farmer->id)" variant="view" size="sm">View</x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-14 text-center">
                                        <p class="text-sm font-medium text-muted-foreground">No farmers match these filters</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Approved farmer registrations appear here.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 border-t border-border pt-5">{{ $farmers->links() }}</div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a farmer from the list to view their full profile.">
            @if ($selected)
                @php $farmer = $selected; $accountStatus = $farmer->user?->status ?? 'pending'; @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        FARM-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
                    </p>
                    <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $farmer->full_name }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{ $farmer->barangay?->name ?? 'No barangay' }} &middot; {{ $farmer->association?->name ?? 'No association' }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $accountBadges[$accountStatus] ?? $accountBadges['pending'] }}">
                            Account: {{ ucfirst($accountStatus) }}
                        </span>
                        <span class="inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $farmer->activity_status === 'active' ? $accountBadges['active'] : $accountBadges['inactive'] }}">
                            Activity: {{ ucfirst($farmer->activity_status) }}
                        </span>
                    </div>
                    @if ($accountStatus === 'pending')
                        <a href="{{ route('mao.membership-applications.show', $farmer) }}"
                           class="mt-3 inline-block rounded-lg bg-green-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-900">
                            Review Registration
                        </a>
                    @endif
                </div>

                <div class="space-y-4 border-t border-border pt-4 text-sm">
                    {{-- Personal information --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Personal Information</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Date of Birth</dt><dd class="text-right font-bold text-foreground">{{ $farmer->date_of_birth?->format('M d, Y') ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Sex</dt><dd class="text-right font-bold capitalize text-foreground">{{ $farmer->sex }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Contact</dt><dd class="text-right font-bold text-foreground">{{ $farmer->user?->phone_number ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Email</dt><dd class="max-w-[60%] truncate text-right font-bold text-foreground">{{ $farmer->user?->email ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Address</dt><dd class="text-right font-bold text-foreground">{{ $farmer->address }}</dd></div>
                        </dl>
                    </div>

                    {{-- Farm ownership + info --}}
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm Ownership &amp; Information</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Farmer Type</dt><dd class="text-right font-bold text-foreground">{{ $farmer->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner' }}</dd></div>
                            @if ($farmer->ownership_type === 'tenant')
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Land Owner</dt><dd class="text-right font-bold text-foreground">{{ $farmer->landowner_name ?: '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Owner Contact</dt><dd class="text-right font-bold text-foreground">{{ $farmer->landowner_contact ?: '-' }}</dd></div>
                            @else
                                <div class="flex justify-between gap-3">
                                    <dt class="text-muted-foreground">Barangay Certificate</dt>
                                    <dd class="text-right font-bold text-foreground">
                                        @if ($farmer->barangay_certificate_path)
                                            <a href="{{ asset('storage/' . $farmer->barangay_certificate_path) }}" target="_blank" class="text-green-800 hover:underline">View</a>
                                        @else
                                            Not provided
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Farm Size</dt><dd class="text-right font-bold text-foreground">{{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : 'Not provided' }}</dd></div>
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

                    {{-- Planting records --}}
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crop Planting Records</p>
                        @forelse ($farmer->plantingRecords->sortByDesc('date_submitted') as $record)
                            <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                                <p class="font-medium text-foreground">{{ $record->date_submitted?->format('M d, Y') }}</p>
                                <p class="text-muted-foreground">
                                    @forelse ($record->crops as $recordCrop)
                                        {{ $recordCrop->crop?->name }} ({{ $recordCrop->area_hectares }} ha)@if (! $loop->last), @endif
                                    @empty
                                        -
                                    @endforelse
                                </p>
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground">No planting records submitted yet.</p>
                        @endforelse
                    </div>

                    {{-- Damage reports --}}
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crop Damage Reports</p>
                        @forelse ($farmer->damageReports->sortByDesc('created_at') as $report)
                            <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-medium text-foreground">{{ $report->reference }}</p>
                                    <span class="inline-flex rounded px-2 py-0.5 font-medium {{ $reportBadges[$report->status] ?? $reportBadges['pending'] }}">
                                        {{ ucwords(str_replace('_', ' ', $report->status)) }}
                                    </span>
                                </div>
                                <p class="text-muted-foreground">
                                    {{ $report->disasters->pluck('name')->join(', ') ?: '-' }} &middot;
                                    {{ $report->crops->map(fn ($c) => $c->crop?->name)->filter()->join(', ') ?: '-' }}
                                </p>
                                @if ($report->validation?->validated_at)
                                    <p class="text-muted-foreground">
                                        {{ ucfirst($report->validation->severity ?? '-') }}, {{ $report->validation->assessed_damage_percent }}% assessed
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground">No damage reports filed yet.</p>
                        @endforelse
                    </div>

                    {{-- Assistance received --}}
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Assistance Received</p>
                        @forelse ($farmer->assistanceDistributions->sortByDesc('distributed_at') as $distribution)
                            <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                                <p class="font-medium text-foreground">{{ $distribution->allocation?->display_name ?? 'Assistance' }}</p>
                                <p class="text-muted-foreground">
                                    {{ $distribution->quantity !== null ? number_format($distribution->quantity, 2) : '-' }}
                                    &middot; {{ $distribution->distributed_at?->format('M d, Y') ?? 'Not yet distributed' }}
                                    &middot; {{ ucwords(str_replace('_', ' ', $distribution->receipt_status)) }}
                                </p>
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground">Nothing distributed to this farmer yet.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
