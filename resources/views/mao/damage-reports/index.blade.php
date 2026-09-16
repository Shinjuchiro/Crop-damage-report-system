@extends('layouts.app')

@section('title', 'Crop Damage Monitoring')
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
<div x-data="{ archiving: null }">

    {{-- Summary --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat label="Total Reports" :value="number_format($summary['total'])" />
        <x-ui.stat label="Pending" :value="number_format($summary['pending'])" hint="Not yet assigned" />
        <x-ui.stat label="Under Verification" :value="number_format($summary['under_verification'])" />
        <x-ui.stat label="Verified" :value="number_format($summary['verified'])" />
        <x-ui.stat label="Total Affected Area" :value="number_format($summary['affected_area'], 2)" suffix="ha" />
    </div>

    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a row's View is followed)
         the detail panel full-screen with its own Back link. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Crop Damage Monitoring">
                <x-slot:actions>
                    <a href="{{ route('mao.archive.index', ['type' => 'damage_reports']) }}"
                       class="text-sm text-muted-foreground hover:text-foreground">
                        View archived
                    </a>
                </x-slot:actions>

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                 :options="$associations->pluck('name', 'id')" :selected="request('association_id')" class="sm:w-52" />

                    <x-ui.select name="disaster_id" placeholder="All Disasters" onchange="this.form.submit()"
                                 :options="$disasters->pluck('name', 'id')" :selected="request('disaster_id')" class="sm:w-52" />

                    <x-ui.select name="status" placeholder="All Statuses" onchange="this.form.submit()"
                                 :options="collect($statuses)->mapWithKeys(fn ($v) => [$v => ucwords(str_replace('_', ' ', $v))])"
                                 :selected="request('status')" class="sm:w-44" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer"
                                class="sm:min-w-[12rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'association_id', 'disaster_id', 'status']))
                        <a href="{{ route('mao.damage-reports.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
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
                                <th class="px-3 py-3">Damaged Area</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-center">Validation</th>
                                <th class="px-3 py-3 text-right">Action</th>
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
                                    <td class="px-3 py-4">
                                        <div class="flex justify-end gap-2">
                                            <x-ui.button :href="$viewUrl($report->id)" variant="view" size="sm">View</x-ui.button>
                                            <button type="button"
                                                    @click="archiving = { id: {{ $report->id }}, label: @js($report->reference) }"
                                                    class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
                                                Archive
                                            </button>
                                        </div>
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
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a report from the list to view its full details.">
            @if ($selected)
                @php
                    $damageReport = $selected;
                    $farmer = $damageReport->farmer;
                    $validation = $damageReport->validation;
                    $canDecide = in_array($damageReport->status, ['verified', 'flagged'], true);
                @endphp

                <div x-data="{
                        decision: null,
                        editingDisasters: false,
                        confirmingDisasters: false,
                        disasterIds: {!! json_encode($damageReport->disasters->pluck('id')->map(fn ($id) => (string) $id)->values()) !!},
                        disasterLabels: {!! json_encode($availableDisasters->pluck('name', 'id')) !!},
                     }">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $damageReport->reference }}</p>
                            <h3 class="mt-0.5 text-lg font-semibold text-foreground">
                                <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                            </h3>
                            <p class="text-sm text-muted-foreground">{{ $farmer->association?->name ?? '-' }} &middot; {{ $farmer->barangay?->name ?? '-' }}</p>
                        </div>
                    </div>

                    <span class="inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $statusBadges[$damageReport->status] ?? $statusBadges['pending'] }}">
                        {{ ucwords(str_replace('_', ' ', $damageReport->status)) }}
                    </span>

                    <div class="mt-4 space-y-4 border-t border-border pt-4 text-sm">
                        {{-- Cause + declared event --}}
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cause of Damage</p>
                            <p class="mb-2 rounded-lg bg-muted px-3 py-2 text-xs font-medium text-foreground">{{ $damageReport->damage_cause_label }}</p>

                            <div class="mb-1 flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">Declared event(s)</p>
                                @if ($canEditDisasters)
                                    <button type="button" x-show="! editingDisasters" @click="editingDisasters = true"
                                            class="text-xs font-semibold text-green-800 hover:underline">
                                        Correct
                                    </button>
                                @endif
                            </div>

                            <template x-if="! editingDisasters">
                                <div>
                                    @forelse ($damageReport->disasters as $disaster)
                                        <p class="text-xs text-foreground">{{ $disaster->name }} <span class="text-muted-foreground">({{ ucwords(str_replace('_', ' ', $disaster->type)) }})</span></p>
                                    @empty
                                        <p class="text-xs text-muted-foreground">No declared event attached.</p>
                                    @endforelse
                                </div>
                            </template>

                            @if ($canEditDisasters)
                                <div x-show="editingDisasters" x-cloak class="mt-2 space-y-1.5">
                                    @foreach ($availableDisasters as $disaster)
                                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-border px-2.5 py-2 text-xs">
                                            <input type="checkbox" value="{{ $disaster->id }}" x-model="disasterIds" class="mt-0.5 h-3.5 w-3.5 shrink-0 accent-[var(--primary)]">
                                            <span>{{ $disaster->name }}</span>
                                        </label>
                                    @endforeach
                                    <div class="flex gap-2 pt-1">
                                        <button type="button" @click="editingDisasters = false"
                                                class="flex-1 rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                            Cancel
                                        </button>
                                        <button type="button" @click="confirmingDisasters = true"
                                                class="flex-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110">
                                            Review
                                        </button>
                                    </div>
                                </div>

                                <div x-show="confirmingDisasters" x-cloak class="fixed inset-0 z-[95] flex items-center justify-center bg-black/40 p-4">
                                    <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                                        <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Disaster Event Changes</h3>
                                        <p class="mb-4 text-sm text-muted-foreground">This report will now be linked to:</p>
                                        <ul class="mb-4 space-y-1 text-sm text-foreground" x-show="disasterIds.length">
                                            <template x-for="id in disasterIds" :key="id">
                                                <li class="rounded bg-muted px-3 py-1.5" x-text="disasterLabels[id]"></li>
                                            </template>
                                        </ul>
                                        <p class="mb-4 text-sm text-muted-foreground" x-show="! disasterIds.length">
                                            No disaster event - only appropriate if the cause was pests, plant disease, or heat.
                                        </p>
                                        <form method="POST" action="{{ route('mao.damage-reports.disasters.update', $damageReport) }}">
                                            @csrf @method('PUT')
                                            <template x-for="id in disasterIds" :key="id">
                                                <input type="hidden" name="disasters[]" :value="id">
                                            </template>
                                            <div class="flex gap-3">
                                                <button type="button" @click="confirmingDisasters = false"
                                                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                                    Back to Edit
                                                </button>
                                                <button type="submit"
                                                        class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                                                    Confirm Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Damaged crops --}}
                        <div class="border-t border-border pt-4">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Damaged Crops</p>
                                <p class="text-xs text-muted-foreground">Total: <span class="font-semibold text-foreground">{{ number_format($damageReport->crops->sum('damaged_area_hectares'), 2) }} ha</span></p>
                            </div>
                            @forelse ($damageReport->crops as $reportCrop)
                                <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                                    <p class="font-medium text-foreground">{{ $reportCrop->crop?->name ?? '-' }} &middot; {{ $reportCrop->damaged_area_hectares }} ha</p>
                                    <p class="text-muted-foreground">
                                        Est. {{ $reportCrop->estimated_damage_percent }}% &middot;
                                        {{ $reportCrop->production_cost !== null ? '₱' . number_format($reportCrop->production_cost, 2) : '-' }} cost &middot;
                                        {{ $reportCrop->farmgate_price_per_kg !== null ? '₱' . number_format($reportCrop->farmgate_price_per_kg, 2) . '/kg' : '-' }}
                                    </p>
                                </div>
                            @empty
                                <p class="text-xs text-muted-foreground">No crops recorded.</p>
                            @endforelse
                            @if ($damageReport->description)
                                <p class="mt-2 text-xs text-muted-foreground"><span class="font-medium text-foreground">Farmer's description:</span> {{ $damageReport->description }}</p>
                            @endif
                        </div>

                        {{-- Farmer photos --}}
                        <div class="border-t border-border pt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farmer's Damage Photos</p>
                            @if ($damageReport->photos->isNotEmpty())
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ($damageReport->photos as $photo)
                                        <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" class="block overflow-hidden rounded-lg border border-border">
                                            <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Damage photo" class="h-16 w-full object-cover transition hover:scale-105">
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-muted-foreground">The farmer did not attach any photos.</p>
                            @endif
                        </div>

                        {{-- Technician inspection --}}
                        <div class="border-t border-border pt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Technician Inspection</p>
                            @if ($validation)
                                <dl class="space-y-1.5">
                                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Technician</dt><dd class="font-medium text-foreground">{{ $validation->technician?->full_name ?: $validation->technician?->username ?? '-' }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Severity</dt><dd class="font-medium capitalize text-foreground">{{ $validation->severity ?? 'Not yet assessed' }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Assessed Damage</dt><dd class="font-medium text-foreground">{{ $validation->assessed_damage_percent !== null ? $validation->assessed_damage_percent . '%' : '-' }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Submitted</dt><dd class="font-medium text-foreground">{{ $validation->validated_at?->format('M d, Y') ?? 'In progress' }}</dd></div>
                                    <div><dt class="text-muted-foreground">Notes</dt><dd class="font-medium text-foreground">{{ $validation->notes ?: 'No notes recorded.' }}</dd></div>
                                </dl>

                                @if ($validation->photos->isNotEmpty())
                                    <p class="mb-2 mt-3 text-xs text-muted-foreground">Inspection photos</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach ($validation->photos as $photo)
                                            <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" class="block overflow-hidden rounded-lg border border-border">
                                                <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Inspection photo" class="h-16 w-full object-cover transition hover:scale-105">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <p class="text-xs text-muted-foreground">No inspection has been started for this report yet.</p>
                            @endif
                        </div>

                        {{-- Verified location --}}
                        @if ($validation && $validation->latitude && $validation->longitude)
                            <div class="border-t border-border pt-4">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Verified Farm Location</p>
                                <div id="verifiedMap-{{ $damageReport->id }}" class="h-48 w-full rounded-lg border border-border"
                                     data-lat="{{ $validation->latitude }}" data-lng="{{ $validation->longitude }}"
                                     data-label="{{ $farmer->full_name }} &middot; {{ $farmer->barangay?->name }}"></div>
                            </div>
                        @endif
                    </div>

                    {{-- MAO decision --}}
                    @if ($canDecide)
                        <div class="mt-5 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row">
                            <button type="button" @click="decision = 'approved'"
                                    class="w-full rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:brightness-110">
                                Approve
                            </button>
                            <button type="button" @click="decision = 'flagged'"
                                    class="w-full rounded-lg border border-orange-300 px-3 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-50">
                                Flag
                            </button>
                            <button type="button" @click="decision = 'rejected'"
                                    class="w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Reject
                            </button>
                        </div>

                        <div x-show="decision" x-cloak class="fixed inset-0 z-[95] flex items-center justify-center bg-black/40 p-4">
                            <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                                <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm <span x-text="decision"></span></h3>
                                <p class="mb-4 text-sm text-muted-foreground">
                                    You are about to mark report <strong>{{ $damageReport->reference }}</strong> for
                                    <strong>{{ $farmer->full_name }}</strong> as <span class="font-semibold" x-text="decision"></span>.
                                    Please review the inspection above before continuing.
                                </p>
                                <form method="POST" action="{{ route('mao.damage-reports.decide', $damageReport) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="decision" :value="decision">
                                    <label class="mb-1.5 block text-sm font-medium text-foreground">Remarks (optional)</label>
                                    <textarea name="remarks" rows="3" class="mb-4 w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring"></textarea>
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

                {{-- Archive / Restore --}}
                <div class="mt-5 border-t border-border pt-4">
                    @if ($damageReport->is_archived)
                        <form method="POST" action="{{ route('mao.damage-reports.restore', $damageReport) }}"
                              data-confirm="Restore {{ $damageReport->reference }} to the active monitoring list?"
                              data-confirm-title="Restore damage report" data-confirm-action="Confirm Restore">
                            @csrf @method('PUT')
                            <button type="submit" class="w-full rounded-lg border border-input px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/60">Restore</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('mao.damage-reports.archive', $damageReport) }}"
                              data-confirm="{{ $damageReport->reference }} will be removed from active monitoring. Its status, inspection and assistance history are unaffected, and it can be restored at any time from the Archive page."
                              data-confirm-title="Archive this damage report?" data-confirm-action="Confirm Archive">
                            @csrf @method('PUT')
                            <button type="submit" class="w-full rounded-lg border border-amber-200 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50">Archive</button>
                        </form>
                    @endif
                </div>

                @if ($validation && $validation->latitude && $validation->longitude)
                    @push('head')
                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
                    @endpush
                    @push('scripts')
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const el = document.getElementById('verifiedMap-{{ $damageReport->id }}');
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
            @endif
        </x-ui.detail-panel>
    </div>

    {{-- Archive confirmation (used by the list rows' Archive button) --}}
    <div x-show="archiving" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Archive this damage report?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                <strong x-text="archiving?.label"></strong> will be removed from active monitoring. Its status,
                inspection and assistance history are unaffected, and it can be restored at any time from the
                Archive page.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/damage-reports') }}/${archiving?.id}/archive`" class="flex-1">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                        Confirm Archive
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
