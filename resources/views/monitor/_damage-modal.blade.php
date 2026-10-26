{{--
    ONE CROP DAMAGE REPORT, FOR THE WIDE DETAIL MODAL

    Shared by the office (mao/damage-reports) and the association
    (association/reports) so both roles read a damage report the same way.
    The data each role gets is decided by its controller (the association's
    only ever finds a report inside its own membership); this file looks up
    nothing. What the roles may DO differs, and that is the only place $role
    is used below:

        office        can assign or reassign a technician, and can correct a
                      report's declared disaster events once it is verified
        association   read only

    Expects
        $report               DamageReport with farmer.{user,association,barangay,mainCrops.crop},
                              crops.crop, disasters, photos, reportedBarangay,
                              assignedTechnician, validation planted via
                              PlantingMatch::annotate()
        $role                 'mao' or 'association'
        $technicians          (office) active technicians for the assign form
        $availableDisasters   (office) disasters offered when correcting events
        $canEditDisasters     (office) whether that correction is open

    THE THREE FACTS STAY APART

    Assignment (Not Assigned / Assigned), the planting match, and the
    inspection's validation result are three separate pills and are never
    merged. "Assigned" only means a technician has been given the report; it
    does not mean the inspection has started or finished.
--}}
@php
    $isMao      = $role === 'mao';
    $farmer     = $report->farmer;
    $technician = $report->assignedTechnician;
    $validation = $report->validation;
    $lines      = $report->planting_lines ?? collect();
    $disasterEdit = $isMao && ($canEditDisasters ?? false);

    $locationName = $report->reportedBarangay?->name ?? $farmer->barangay?->name ?? '-';
    $hasPin       = $report->reported_latitude !== null && $report->reported_longitude !== null;
    $money        = fn ($value) => $value !== null ? '₱' . number_format((float) $value, 2) : '-';
    $percentSeverity = fn ($percent) => \App\Models\Validation::severityLabel(
        \App\Models\Validation::severityKeyForPercent($percent !== null ? (float) $percent : null)
    ) ?? '-';
@endphp

<div @if ($disasterEdit)
        x-data="{
            editingDisasters: false,
            confirmingDisasters: false,
            disasterIds: @js($report->disasters->pluck('id')->map(fn ($id) => (string) $id)->values()->all()),
            disasterLabels: @js(collect($availableDisasters)->pluck('name', 'id')->all()),
        }"
     @endif>

    {{-- HEADER --}}
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Damage Report &middot; {{ $report->full_reference }}</p>
            <h3 class="mt-0.5 text-lg font-bold text-foreground">
                @if ($isMao)
                    <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                @else
                    <a href="{{ route('association.members.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                @endif
            </h3>
            <p class="text-sm text-muted-foreground">{{ $farmer->association?->name ?? '-' }} &middot; {{ $farmer->barangay?->name ?? '-' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-monitor.badge kind="assignment" :value="$report->is_assigned ? 'assigned' : 'unassigned'" />
            <x-monitor.badge kind="match" :value="$report->planting_match" />
        </div>
    </div>

    <x-monitor.farmer-farm :farmer="$farmer" :contact="$isMao" />

    <div class="mt-4 space-y-4">

        {{-- LEFT: the report the farmer filed --}}
        <div class="space-y-4 border-t border-border pt-4 text-sm">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crop Damage Report</p>
                <dl class="space-y-1.5">
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Damage Report ID</dt><dd class="text-right font-medium text-foreground">{{ $report->full_reference }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Date Submitted</dt><dd class="text-right font-medium text-foreground">{{ $report->created_at?->format('F d, Y') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Total Damaged Area</dt><dd class="text-right font-medium text-foreground">{{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha</dd></div>
                </dl>
            </div>

            {{-- Disaster type + declared events --}}
            <div class="border-t border-border pt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Disaster Type</p>
                <p class="mb-2 rounded-lg bg-muted px-3 py-2 text-xs font-medium text-foreground">{{ $report->damage_cause_label }}</p>

                <div class="mb-1 flex items-center justify-between">
                    <p class="text-xs text-muted-foreground">Declared event(s)</p>
                    @if ($disasterEdit)
                        <button type="button" x-show="! editingDisasters" @click="editingDisasters = true"
                                class="text-xs font-semibold text-green-800 hover:underline">
                            Correct
                        </button>
                    @endif
                </div>

                @if ($disasterEdit)
                    <template x-if="! editingDisasters">
                        <div>
                            @forelse ($report->disasters as $disaster)
                                <p class="text-xs text-foreground">{{ $disaster->name }} <span class="text-muted-foreground">({{ ucwords(str_replace('_', ' ', $disaster->type)) }})</span></p>
                            @empty
                                <p class="text-xs text-muted-foreground">No declared event attached.</p>
                            @endforelse
                        </div>
                    </template>

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

                    <div x-show="confirmingDisasters" x-cloak class="fixed inset-0 z-[95] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
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
                            <form method="POST" action="{{ route('mao.damage-reports.disasters.update', $report) }}">
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
                @else
                    @forelse ($report->disasters as $disaster)
                        <p class="text-xs text-foreground">{{ $disaster->name }} <span class="text-muted-foreground">({{ ucwords(str_replace('_', ' ', $disaster->type)) }})</span></p>
                    @empty
                        <p class="text-xs text-muted-foreground">No declared event attached.</p>
                    @endforelse
                @endif
            </div>

            {{-- Damaged crops, with the planting match for each line --}}
            <div class="border-t border-border pt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Damaged Crops</p>

                @forelse ($lines as $row)
                    @php $line = $row['line']; @endphp
                    <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2.5 text-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-foreground">
                                {{ $line->crop?->name ?? '-' }}@if ($line->crop_specify) ({{ $line->crop_specify }})@endif
                                &middot; {{ number_format((float) $line->damaged_area_hectares, 2) }} ha
                            </p>
                            <x-monitor.badge kind="match" :value="$row['state']" />
                        </div>

                        <dl class="mt-2 space-y-1">
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Planting date (given by the farmer)</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $line->date_planted?->format('M d, Y') ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Estimated damage</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $line->estimated_damage_percent !== null ? (float) $line->estimated_damage_percent . '%' : '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Severity (from the farmer's estimate)</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $percentSeverity($line->estimated_damage_percent) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Production cost</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $money($line->production_cost) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farmgate price per kg</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $money($line->farmgate_price_per_kg) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Damage cost</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $money($line->total_damage_cost) }}</dd></div>
                        </dl>

                        <p class="mt-2 text-muted-foreground">
                            @if ($row['state'] === 'matched')
                                A Crop Planted report matches this crop and planting date
                                ({{ number_format((float) $row['planted_area'], 2) }} ha planted).
                            @elseif ($row['state'] === 'mismatch')
                                {{ implode(' ', $row['reasons']) }}
                            @else
                                This farmer has no Crop Planted reports to compare.
                            @endif
                            @if ($row['state'] !== 'matched')
                                The report is still valid; the technician checks it in the field.
                            @endif
                        </p>

                        @if ($row['area_flag'])
                            <p class="mt-1 rounded bg-amber-50 px-2 py-1 font-medium text-amber-800">
                                Inconsistent area: {{ number_format((float) $line->damaged_area_hectares, 2) }} ha reported damaged against
                                {{ number_format((float) $row['planted_area'], 2) }} ha planted. Flagged for the technician to measure; the report is not rejected.
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-muted-foreground">No crops recorded.</p>
                @endforelse

                @if ($report->description)
                    <p class="mt-2 text-xs text-muted-foreground"><span class="font-medium text-foreground">Farmer's description:</span> {{ $report->description }}</p>
                @endif
            </div>
        </div>

        {{-- RIGHT: evidence, location, technician --}}
        <div class="space-y-4 text-sm">
            <div class="border-t border-border pt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Submitted Photos</p>
                @if ($report->photos->isNotEmpty())
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($report->photos as $photo)
                            <a href="{{ $photo->url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-border">
                                <img src="{{ $photo->url }}" alt="Damage photo" class="h-28 w-full object-cover transition hover:scale-105">
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-muted-foreground">The farmer did not attach any photos.</p>
                @endif
            </div>

            <div class="border-t border-border pt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm / Damage Location</p>
                <p class="text-xs text-foreground">{{ $report->farm_location_description ?: 'No location description provided.' }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ $locationName }}</p>
                @if ($hasPin)
                    <p class="mt-1 text-xs text-muted-foreground">Coordinates: {{ $report->reported_latitude }}, {{ $report->reported_longitude }}</p>
                    <div id="reportedMap-{{ $report->id }}" class="mt-2 h-48 w-full rounded-lg border border-border"
                         data-lat="{{ $report->reported_latitude }}" data-lng="{{ $report->reported_longitude }}"
                         data-label="{{ $farmer->full_name }} &middot; {{ $locationName }}"></div>
                @else
                    <p class="mt-1 text-xs text-muted-foreground">No coordinates were given.</p>
                @endif
            </div>

            <div class="border-t border-border pt-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Technician Assignment</p>
                    <x-monitor.badge kind="assignment" :value="$report->is_assigned ? 'assigned' : 'unassigned'" />
                </div>

                @if ($technician)
                    <p class="text-sm font-semibold text-foreground">{{ $technician->full_name ?: $technician->username }}</p>
                    <p class="text-xs text-muted-foreground">
                        Assigned {{ $report->assigned_at?->format('F d, Y') ?? '' }}.
                        Assigned only means the report was given to a technician; the inspection is shown in
                        @if ($isMao)
                            <a href="{{ route('mao.validations.index', ['search' => $farmer->last_name]) }}" class="font-medium text-green-800 hover:underline">Validation Monitoring</a>
                        @else
                            <a href="{{ route('association.validations.index', ['q' => $farmer->last_name]) }}" class="font-medium text-green-800 hover:underline">Validation Monitoring</a>
                        @endif
                        once the technician submits it.
                    </p>
                @else
                    <p class="text-xs text-muted-foreground">No technician has been assigned yet. The report is awaiting assignment.</p>
                @endif

                @if ($isMao)
                    @if ($report->can_be_assigned && collect($technicians)->isNotEmpty())
                        <button type="button"
                                @click="assigning = {
                                    id: {{ $report->id }},
                                    code: @js($report->full_reference),
                                    farmer: @js($farmer->full_name ?? 'Unknown'),
                                    current: {{ $report->assigned_technician_id ?: 'null' }}
                                }"
                                class="mt-3 w-full rounded-lg bg-green-800 px-3 py-2 text-sm font-semibold text-white hover:bg-green-900">
                            {{ $technician ? 'Reassign Technician' : 'Assign Technician' }}
                        </button>
                    @elseif ($report->is_assigned && ! $report->can_be_assigned)
                        <p class="mt-2 text-xs text-muted-foreground">An inspection has started or been submitted, so the technician can no longer be changed.</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

@if ($hasPin)
    @push('head')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    @endpush
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const el = document.getElementById('reportedMap-{{ $report->id }}');
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
