@extends('layouts.app')

@section('title', 'Damage Report Details')
@section('heading', 'Damage Report DR-' . str_pad($damageReport->id, 4, '0', STR_PAD_LEFT))
@section('subheading', 'Everything the farmer submitted and everything the technician verified, in one place.')

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

    $farmer     = $damageReport->farmer;
    $validation = $damageReport->validation;
    $canDecide  = in_array($damageReport->status, ['verified', 'flagged'], true);
@endphp

@section('header-actions')
    <a href="{{ route('mao.damage-reports.index') }}"
       class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
        Back to Monitoring
    </a>
@endsection

@section('content')
<div x-data="{ decision: null }" class="space-y-6">

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Status strip --}}
    <div class="flex flex-wrap items-center gap-4 rounded-xl border border-border bg-card px-6 py-4 shadow-sm">
        <span class="inline-flex rounded px-3 py-1.5 text-sm font-semibold {{ $statusBadges[$damageReport->status] ?? $statusBadges['pending'] }}">
            {{ ucwords(str_replace('_', ' ', $damageReport->status)) }}
        </span>

        <p class="text-sm text-muted-foreground">
            Reported <span class="font-medium text-foreground">{{ $damageReport->created_at?->format('M d, Y') }}</span>
        </p>

        @if ($damageReport->assignedTechnician)
            <p class="text-sm text-muted-foreground">
                Technician
                <span class="font-medium text-foreground">
                    {{ $damageReport->assignedTechnician->full_name ?: $damageReport->assignedTechnician->username }}
                </span>
            </p>
        @endif

        @if ($damageReport->approved_at)
            <p class="text-sm text-muted-foreground">
                Approved <span class="font-medium text-foreground">{{ $damageReport->approved_at->format('M d, Y') }}</span>
                by {{ $damageReport->approvedBy?->full_name ?: $damageReport->approvedBy?->username }}
            </p>
        @endif
    </div>

    {{-- Farmer + farm --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farmer Information</h3>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Name</dt>
                    <dd class="font-medium text-foreground">
                        <a href="{{ route('mao.farmers.show', $farmer) }}" class="text-green-800 hover:underline">
                            {{ $farmer->full_name }}
                        </a>
                    </dd>
                </div>
                <div><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $farmer->association?->name ?? '-' }}</dd></div>
                <div><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $farmer->barangay?->name ?? '-' }}</dd></div>
                <div><dt class="text-muted-foreground">Contact Number</dt><dd class="font-medium text-foreground">{{ $farmer->user?->phone_number ?? '-' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farm</h3>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Ownership</dt>
                    <dd class="font-medium text-foreground">{{ $farmer->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Farm Size</dt>
                    <dd class="font-medium text-foreground">{{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : '-' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted-foreground">Main Crops</dt>
                    <dd class="font-medium text-foreground">
                        {{ $farmer->mainCrops->map(fn ($mc) => $mc->crop?->name)->filter()->join(', ') ?: '-' }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted-foreground">Farm Location as described by the farmer</dt>
                    <dd class="font-medium text-foreground">{{ $damageReport->farm_location_description ?: 'Not provided' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Cause of damage, and the declared event when there is one --}}
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary">Cause of Damage</h3>

        {{-- Always recorded on the report itself, so this is never blank. --}}
        <div class="mb-5 rounded-lg bg-muted px-4 py-3">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Reported cause
            </p>
            <p class="mt-0.5 text-base font-semibold text-foreground">
                {{ $damageReport->damage_cause_label }}
            </p>
        </div>

        <h4 class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Declared event cited
        </h4>

        @if ($damageReport->disasters->isNotEmpty())
            <div class="flex flex-wrap gap-3">
                @foreach ($damageReport->disasters as $disaster)
                    <div class="rounded-lg border border-border bg-muted px-4 py-3">
                        <p class="text-sm font-medium text-foreground">{{ $disaster->name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ ucwords(str_replace('_', ' ', $disaster->type)) }}
                            &middot; {{ $disaster->date_start?->format('M d, Y') }}
                            @if ($disaster->date_end) to {{ $disaster->date_end->format('M d, Y') }} @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted-foreground">
                No declared event was attached. That is expected when the cause was pests, plant
                disease or heat, since the office does not declare an event for those.
            </p>
        @endif
    </div>

    {{-- Damaged crops --}}
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-6 py-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-green-800">Damaged Crops</h3>
            <p class="text-sm text-muted-foreground">
                Total damaged area:
                <span class="font-semibold text-foreground">
                    {{ number_format($damageReport->crops->sum('damaged_area_hectares'), 2) }} ha
                </span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-6 py-3 font-medium">Crop</th>
                        <th class="px-6 py-3 text-right font-medium">Damaged Area</th>
                        <th class="px-6 py-3 font-medium">Date Planted</th>
                        <th class="px-6 py-3 text-right font-medium">Farmer's Estimate</th>
                        <th class="px-6 py-3 text-right font-medium">Production Cost</th>
                        <th class="px-6 py-3 text-right font-medium">Farmgate Price</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($damageReport->crops as $reportCrop)
                        <tr>
                            <td class="px-6 py-3 font-medium text-foreground">
                                {{ $reportCrop->crop?->name ?? '-' }}
                                @if ($reportCrop->crop_specify)
                                    <span class="block text-xs font-normal text-muted-foreground">{{ $reportCrop->crop_specify }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right tabular-nums text-foreground">{{ $reportCrop->damaged_area_hectares }} ha</td>
                            <td class="px-6 py-3 text-muted-foreground">{{ $reportCrop->date_planted?->format('M d, Y') }}</td>
                            <td class="px-6 py-3 text-right tabular-nums text-foreground">{{ $reportCrop->estimated_damage_percent }}%</td>
                            <td class="px-6 py-3 text-right tabular-nums text-foreground">
                                {{ $reportCrop->production_cost !== null ? '₱' . number_format($reportCrop->production_cost, 2) : '-' }}
                            </td>
                            <td class="px-6 py-3 text-right tabular-nums text-foreground">
                                {{ $reportCrop->farmgate_price_per_kg !== null ? '₱' . number_format($reportCrop->farmgate_price_per_kg, 2) . '/kg' : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-muted-foreground">No crops recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($damageReport->description)
            <div class="border-t border-border px-6 py-4">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Farmer's description</p>
                <p class="mt-1 text-sm text-foreground">{{ $damageReport->description }}</p>
            </div>
        @endif
    </div>

    {{-- Farmer photos --}}
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farmer's Damage Photos</h3>

        @if ($damageReport->photos->isNotEmpty())
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($damageReport->photos as $photo)
                    <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank"
                       class="block overflow-hidden rounded-lg border border-border">
                        <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Damage photo"
                             class="h-36 w-full object-cover transition hover:scale-105">
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted-foreground">The farmer did not attach any photos.</p>
        @endif
    </div>

    {{-- Technician inspection --}}
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Technician Inspection</h3>

        @if ($validation)
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-muted-foreground">Technician</dt>
                    <dd class="font-medium text-foreground">
                        {{ $validation->technician?->full_name ?: $validation->technician?->username ?? '-' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Inspection Started</dt>
                    <dd class="font-medium text-foreground">{{ $validation->inspection_started_at?->format('M d, Y g:i A') }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Severity</dt>
                    <dd class="font-medium capitalize text-foreground">{{ $validation->severity ?? 'Not yet assessed' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Assessed Damage</dt>
                    <dd class="font-medium text-foreground">
                        {{ $validation->assessed_damage_percent !== null ? $validation->assessed_damage_percent . '%' : '-' }}
                    </dd>
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <dt class="text-muted-foreground">Inspection Notes</dt>
                    <dd class="font-medium text-foreground">{{ $validation->notes ?: 'No notes recorded.' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Verified Location</dt>
                    <dd class="font-medium text-foreground">
                        @if ($validation->latitude && $validation->longitude)
                            {{ $validation->latitude }}, {{ $validation->longitude }}
                        @else
                            Not pinned yet
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Submitted</dt>
                    <dd class="font-medium text-foreground">
                        {{ $validation->validated_at?->format('M d, Y g:i A') ?? 'Inspection still in progress' }}
                    </dd>
                </div>
            </dl>

            @if ($validation->photos->isNotEmpty())
                <div class="mt-6">
                    <p class="mb-3 text-xs uppercase tracking-wide text-muted-foreground">Inspection photos</p>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($validation->photos as $photo)
                            <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank"
                               class="block overflow-hidden rounded-lg border border-border">
                                <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Inspection photo"
                                     class="h-36 w-full object-cover transition hover:scale-105">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <p class="text-sm text-muted-foreground">
                No inspection has been started for this report yet.
            </p>
        @endif
    </div>

    {{-- Verified location on the map --}}
    @if ($validation && $validation->latitude && $validation->longitude)
        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-green-800">Verified Farm Location</h3>
            <p class="mb-4 text-xs text-muted-foreground">
                Pinned by the technician during field inspection.
            </p>
            <div id="verifiedMap" class="h-80 w-full rounded-lg border border-border"
                 data-lat="{{ $validation->latitude }}"
                 data-lng="{{ $validation->longitude }}"
                 data-label="{{ $farmer->full_name }} &middot; {{ $farmer->barangay?->name }}"></div>
        </div>
    @endif

    {{-- MAO decision --}}
    @if ($canDecide)
        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-green-800">MAO Decision</h3>
            <p class="mb-4 text-sm text-muted-foreground">
                The technician has verified this report. Approve it so it can be counted for assistance,
                flag it for a second look, or reject it.
            </p>

            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="button" @click="decision = 'approved'"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                    Approve Report
                </button>
                <button type="button" @click="decision = 'flagged'"
                        class="rounded-lg border border-orange-300 px-5 py-2.5 text-sm font-semibold text-orange-700 hover:bg-orange-50">
                    Flag for Review
                </button>
                <button type="button" @click="decision = 'rejected'"
                        class="rounded-lg border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                    Reject Report
                </button>
            </div>
        </div>

        {{-- Confirmation --}}
        <div x-show="decision" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-semibold text-foreground">
                    Confirm <span x-text="decision"></span>
                </h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    You are about to mark report
                    <strong>DR-{{ str_pad($damageReport->id, 4, '0', STR_PAD_LEFT) }}</strong>
                    for <strong>{{ $farmer->full_name }}</strong> as
                    <span class="font-semibold" x-text="decision"></span>.
                    Please review the inspection above before continuing.
                </p>

                <form method="POST" action="{{ route('mao.damage-reports.decide', $damageReport) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="decision" :value="decision">

                    <label class="mb-1.5 block text-sm font-medium text-foreground">Remarks (optional)</label>
                    <textarea name="remarks" rows="3"
                              class="mb-4 w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring"></textarea>

                    <div class="flex gap-3">
                        <button type="button" @click="decision = null"
                                class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

@if ($validation && $validation->latitude && $validation->longitude)
    @push('head')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    @endpush

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const el = document.getElementById('verifiedMap');
                if (! el || typeof L === 'undefined') return;

                const lat = parseFloat(el.dataset.lat);
                const lng = parseFloat(el.dataset.lng);

                const map = L.map(el).setView([lat, lng], 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                L.marker([lat, lng]).addTo(map).bindPopup(el.dataset.label).openPopup();
            });
        </script>
    @endpush
@endif
