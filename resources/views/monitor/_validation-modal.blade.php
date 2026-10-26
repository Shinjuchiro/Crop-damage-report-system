{{--
    ONE TECHNICIAN VALIDATION, FOR THE WIDE DETAIL MODAL

    Shared by the office (mao/validations) and the association
    (association/validations). It connects three things on one screen:

        the farmer's Crop Damage Report
      + the Crop Planted report it was compared with, if there is one
      + the technician's submitted validation assessment

    The data each role gets is decided by its controller (the association's
    only ever finds a report inside its own membership); this file looks up
    nothing. $role matters in two places only: the office can approve or
    reject a submitted assessment and sees the technician's exact pin; the
    association reads the same assessment and can do neither.

    Expects
        $report   DamageReport with farmer.{association,barangay,...}, crops.crop,
                  disasters, photos, reportedBarangay, assignedTechnician,
                  validation.{technician,photos}, reviewedBy, annotated by
                  PlantingMatch::annotate()
        $role     'mao' or 'association'

    WHAT THE RESULT MEANS

    A missing or non-matching Crop Planted report never makes a damage
    report false, invalid or ineligible. It is a reason for the technician to
    look closely, and the technician's own findings are the check. Approving
    an assessment is not approving assistance: allocation is a separate
    decision made under Assistance Allocation.
--}}
@php
    $isMao      = $role === 'mao';
    $farmer     = $report->farmer;
    $validation = $report->validation;
    $technician = $validation?->technician ?? $report->assignedTechnician;
    $lines      = $report->planting_lines ?? collect();
    $submitted  = (bool) $validation?->validated_at;
    $reviewState = $report->review_state;
    $canDecide  = $isMao && $submitted && $reviewState === 'pending_review';
    $estimate   = $report->crops->avg('estimated_damage_percent');
    $locationName = $report->reportedBarangay?->name ?? $farmer->barangay?->name ?? '-';
    $farmLocation = trim(($farmer->address ? $farmer->address . ', ' : '') . ($farmer->barangay?->name ?? ''), ', ');
    $match      = $report->planting_match;
    $recordCount = (int) ($report->planting_records ?? 0);
@endphp

<div x-data="{ decision: null }">

    {{-- HEADER --}}
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Validation &middot; {{ $report->full_reference }}</p>
            <h3 class="mt-0.5 text-lg font-bold text-foreground">
                @if ($isMao)
                    <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                @else
                    <a href="{{ route('association.members.show', $farmer) }}" class="hover:underline">{{ $farmer->full_name }}</a>
                @endif
            </h3>
            <p class="text-sm text-muted-foreground">{{ $farmer->association?->name ?? '-' }} &middot; {{ $farmer->barangay?->name ?? '-' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-monitor.badge kind="match" :value="$match" />
            @if ($submitted)
                <x-monitor.badge kind="validation" :value="$reviewState" />
            @else
                <span class="inline-flex whitespace-nowrap rounded bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">Inspection not submitted</span>
            @endif
        </div>
    </div>

    <x-monitor.farmer-farm :farmer="$farmer" :contact="$isMao" />

    <div class="mt-4 grid gap-4 lg:grid-cols-2">

        {{-- A. WHAT THE FARMER REPORTED --}}
        <div class="space-y-4 rounded-xl border border-border p-4 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crop Damage Report</p>

            <dl class="space-y-1.5">
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Damage Report ID</dt><dd class="text-right font-medium text-foreground">{{ $report->full_reference }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Date Submitted</dt><dd class="text-right font-medium text-foreground">{{ $report->created_at?->format('F d, Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Disaster Type</dt><dd class="text-right font-medium text-foreground">{{ $report->damage_cause_label }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Declared Event(s)</dt><dd class="text-right font-medium text-foreground">{{ $report->disasters->pluck('name')->implode(', ') ?: 'None attached' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Reported Damaged Area</dt><dd class="text-right font-medium text-foreground">{{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farmer Estimated Damage</dt><dd class="text-right font-medium text-foreground">{{ $estimate !== null ? round($estimate) . '%' : '-' }}</dd></div>
            </dl>

            <div class="border-t border-border pt-3">
                <p class="mb-2 text-xs text-muted-foreground">Reported crops</p>
                @forelse ($report->crops as $crop)
                    <p class="text-xs text-foreground">
                        <span class="font-semibold">{{ $crop->crop?->name ?? '-' }}@if ($crop->crop_specify) ({{ $crop->crop_specify }})@endif</span>
                        &middot; planted {{ $crop->date_planted?->format('M d, Y') ?? '-' }}
                        &middot; {{ number_format((float) $crop->damaged_area_hectares, 2) }} ha damaged
                    </p>
                @empty
                    <p class="text-xs text-muted-foreground">No crops recorded.</p>
                @endforelse
            </div>

            @if ($report->description)
                <p class="rounded-lg bg-muted px-3 py-2 text-xs"><span class="font-medium text-foreground">Farmer's description:</span> {{ $report->description }}</p>
            @endif

            <div class="border-t border-border pt-3">
                <p class="mb-2 text-xs text-muted-foreground">Farmer-submitted photos</p>
                @if ($report->photos->isNotEmpty())
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($report->photos as $photo)
                            <a href="{{ $photo->url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-border">
                                <img src="{{ $photo->url }}" alt="Damage photo" class="h-20 w-full object-cover transition hover:scale-105">
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-muted-foreground">The farmer did not attach any photos.</p>
                @endif
            </div>
        </div>

        {{-- C. WHAT THE TECHNICIAN FOUND --}}
        <div class="space-y-4 rounded-xl border border-border p-4 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Technician Validation Assessment</p>

            @if ($submitted)
                <dl class="space-y-1.5">
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Technician</dt><dd class="text-right font-bold text-foreground">{{ $technician?->full_name ?: ($technician?->username ?? '-') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Inspection Date</dt><dd class="text-right font-medium text-foreground">{{ $validation->validated_at->format('M d, Y g:i A') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Crop(s) Inspected</dt><dd class="text-right font-medium text-foreground">{{ $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->filter()->implode(', ') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Verified Damage Severity</dt><dd class="text-right font-medium text-foreground">{{ \App\Models\Validation::severityLabel($validation->severity) ?? 'Not assessed' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Assessed Damage</dt><dd class="text-right font-medium text-foreground">{{ $validation->assessed_damage_percent !== null ? (float) $validation->assessed_damage_percent . '%' : '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Planting Record Comparison</dt><dd class="text-right"><x-monitor.badge kind="match" :value="$match" /></dd></div>
                    <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Assessment Status</dt><dd class="text-right"><x-monitor.badge kind="validation" :value="$reviewState" /></dd></div>
                    <div><dt class="mb-1 text-muted-foreground">Observed Crop Condition and Technician's Remarks</dt><dd class="font-medium text-foreground">{{ $validation->notes ?: 'No remarks recorded.' }}</dd></div>
                </dl>

                @if ($validation->photos->isNotEmpty())
                    <div class="border-t border-border pt-3">
                        <p class="mb-2 text-xs text-muted-foreground">Inspection photos</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($validation->photos as $photo)
                                <a href="{{ $photo->url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-border">
                                    <img src="{{ $photo->url }}" alt="Inspection photo" class="h-20 w-full object-cover transition hover:scale-105">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- The exact pin is the office's; the association is shown the barangay only. --}}
                @if ($isMao && $validation->latitude && $validation->longitude)
                    <div class="border-t border-border pt-3">
                        <p class="mb-2 text-xs text-muted-foreground">Verified farm location</p>
                        <p class="mb-2 text-xs text-foreground">
                            {{ $report->farm_location_description ?: $locationName }}
                            &middot; {{ $validation->latitude }}, {{ $validation->longitude }}
                        </p>
                        <div id="verifiedMap-{{ $report->id }}" class="h-44 w-full rounded-lg border border-border"
                             data-lat="{{ $validation->latitude }}" data-lng="{{ $validation->longitude }}"
                             data-label="{{ $farmer->full_name }} &middot; {{ $farmer->barangay?->name }}"></div>
                    </div>
                @endif
            @elseif ($validation)
                <p class="text-xs text-muted-foreground">The technician has started the inspection but has not submitted it yet.</p>
            @elseif ($report->assignedTechnician)
                <p class="text-xs text-muted-foreground">
                    Assigned to {{ $report->assignedTechnician->full_name ?: $report->assignedTechnician->username }}.
                    The inspection has not started yet. Assigned does not mean inspected.
                </p>
            @else
                <p class="text-xs text-muted-foreground">No technician has been assigned and no inspection has been submitted yet.</p>
            @endif
        </div>
    </div>

    {{-- B. CROP PLANTED REPORT vs. REPORTED CROP DAMAGE --}}
    <div class="mt-4 rounded-xl border border-border p-4">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crop Planted Report vs. Reported Crop Damage</p>
            <x-monitor.badge kind="match" :value="$match" />
        </div>

        <p class="mb-3 rounded-lg bg-muted px-3 py-2 text-xs text-foreground">
            @if ($match === 'matched')
                Every reported crop has a Crop Planted report from this farmer with the same crop and planting date.
            @elseif ($match === 'mismatch')
                {{ $farmer->full_name }} has {{ $recordCount }} Crop Planted {{ \Illuminate\Support\Str::plural('report', $recordCount) }},
                but none matches the reported crop and planting date.
            @else
                {{ $farmer->full_name }} has no Crop Planted reports to compare against.
            @endif
            <span class="text-muted-foreground">
                This only means no record on file matches the reported details. It does not mean the crop was not
                planted, and it does not make the report false or ineligible.
            </span>
        </p>

        @forelse ($lines as $row)
            @php $line = $row['line']; $planted = $row['planted']; @endphp
            <div class="mb-2 overflow-hidden rounded-lg border border-border">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted px-3 py-1.5">
                    <p class="text-xs font-semibold text-foreground">{{ $line->crop?->name ?? '-' }}@if ($line->crop_specify) ({{ $line->crop_specify }})@endif</p>
                    <x-monitor.badge kind="match" :value="$row['state']" />
                </div>
                <div class="grid divide-y divide-border text-xs sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                    <div class="px-3 py-2">
                        <p class="mb-1 font-medium text-muted-foreground">Crop Planted report</p>
                        @if ($planted)
                            <p class="text-foreground">Farmer: {{ $farmer->full_name }}</p>
                            <p class="text-foreground">Crop: {{ $planted->crop_specify ?: ($planted->crop?->name ?? '-') }}</p>
                            <p class="text-foreground">Planted: {{ $planted->date_planted?->format('M d, Y') }}</p>
                            <p class="text-foreground">Farm: {{ $farmLocation ?: '-' }}</p>
                            <p class="text-foreground">Area planted: {{ number_format((float) $row['planted_area'], 2) }} ha</p>
                            <p class="text-muted-foreground">Report REC-{{ str_pad((string) $planted->crop_planting_record_id, 4, '0', STR_PAD_LEFT) }},
                                recorded {{ $planted->plantingRecord?->date_submitted?->format('M d, Y') }}</p>
                        @elseif ($row['state'] === 'mismatch')
                            @foreach ($row['reasons'] as $reason)
                                <p class="text-muted-foreground">{{ $reason }}</p>
                            @endforeach
                        @else
                            <p class="text-muted-foreground">This farmer has no Crop Planted reports.</p>
                        @endif
                    </div>
                    <div class="px-3 py-2">
                        <p class="mb-1 font-medium text-muted-foreground">Reported crop damage</p>
                        <p class="text-foreground">Farmer: {{ $farmer->full_name }}</p>
                        <p class="text-foreground">Crop: {{ $line->crop_specify ?: ($line->crop?->name ?? '-') }}</p>
                        <p class="text-foreground">Planted: {{ $line->date_planted?->format('M d, Y') ?? '-' }}</p>
                        <p class="text-foreground">Farm: {{ $report->farm_location_description ?: $locationName }}</p>
                        <p class="text-foreground">Damaged area: {{ number_format((float) $line->damaged_area_hectares, 2) }} ha</p>
                    </div>
                </div>
                @if ($row['area_flag'])
                    <p class="border-t border-border bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800">
                        Inconsistent area: the reported damaged area is larger than the planted area. Flagged for inspection; the report is not rejected.
                    </p>
                @endif
            </div>
        @empty
            <p class="text-xs text-muted-foreground">No crops recorded on this report.</p>
        @endforelse

        <p class="mt-2 text-[11px] text-muted-foreground">
            The damaged area is allowed to be smaller than the planted area, because damage can affect only part of a planting.
            Planting reports are not tied to a particular plot, so only the farm location is shown, not matched.
        </p>
    </div>

    {{-- THE OFFICE'S REVIEW --}}
    <div class="mt-4 rounded-xl border border-border p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">MAO Review of the Assessment</p>

        <div class="flex flex-wrap items-center gap-3">
            @if ($submitted)
                <x-monitor.badge kind="validation" :value="$reviewState" class="px-3! py-1.5! text-sm!" />
            @else
                <span class="text-xs text-muted-foreground">Nothing to review until the technician submits the inspection.</span>
            @endif

            @if ($submitted && $reviewState === 'approved')
                <p class="text-xs text-muted-foreground">
                    Approved by {{ $report->reviewedBy?->full_name ?: ($report->reviewedBy?->username ?? $report->approvedBy?->full_name ?? 'the office') }}
                    @if ($report->reviewed_at ?? $report->approved_at) on {{ ($report->reviewed_at ?? $report->approved_at)->format('M d, Y') }}@endif.
                </p>
            @elseif ($submitted && $reviewState === 'rejected')
                <p class="text-xs text-muted-foreground">
                    Rejected{{ $report->reviewedBy ? ' by ' . ($report->reviewedBy->full_name ?: $report->reviewedBy->username) : '' }}
                    @if ($report->reviewed_at) on {{ $report->reviewed_at->format('M d, Y') }}@endif.
                </p>
            @elseif ($submitted)
                <p class="text-xs text-muted-foreground">The technician has submitted the assessment. The office has not made a review decision yet.</p>
            @endif
        </div>

        @if ($report->review_remarks)
            <p class="mt-2 rounded-lg bg-muted px-3 py-2 text-xs"><span class="font-medium text-foreground">Review remarks:</span> {{ $report->review_remarks }}</p>
        @endif

        <p class="mt-2 text-[11px] text-muted-foreground">
            Approving an assessment is not approving assistance. Whether a farmer receives assistance is a separate decision made under Assistance Allocation.
        </p>

        @if ($canDecide)
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <button type="button" @click="decision = 'approved'"
                        class="w-full rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:brightness-110">
                    Approve Assessment
                </button>
                <button type="button" @click="decision = 'rejected'"
                        class="w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                    Reject Assessment
                </button>
            </div>

            <div x-show="decision" x-cloak class="fixed inset-0 z-[95] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                    <h3 class="mb-2 text-lg font-semibold text-foreground">
                        Confirm <span x-text="decision === 'approved' ? 'approval' : 'rejection'"></span>
                    </h3>
                    <p class="mb-4 text-sm text-muted-foreground">
                        You are about to <span class="font-semibold" x-text="decision === 'approved' ? 'approve' : 'reject'"></span>
                        the technician's assessment of report <strong>{{ $report->full_reference }}</strong> for
                        <strong>{{ $farmer->full_name }}</strong>. This does not allocate assistance.
                    </p>
                    <form method="POST" action="{{ route('mao.damage-reports.decide', $report) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="decision" :value="decision">
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            Remarks <span x-text="decision === 'rejected' ? '(required)' : '(optional)'"></span>
                        </label>
                        <textarea name="remarks" rows="3" :required="decision === 'rejected'"
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
</div>

@if ($isMao && $validation && $validation->latitude && $validation->longitude)
    @push('head')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    @endpush
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const el = document.getElementById('verifiedMap-{{ $report->id }}');
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
