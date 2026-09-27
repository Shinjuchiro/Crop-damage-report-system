{{--
    ONE DAMAGE REPORT, SUMMARISED FOR THE RIGHT-HAND PANEL

    Rendered inside x-ui.detail-panel on farmer/reports/index when a row is
    picked with ?selected=<id>. The full page is still there behind
    "Open full page": this is the summary a farmer checks, not a
    replacement for the record.

    Expects: $report, loaded with crops.crop, disasters, photos,
             assignedTechnician, validation.photos and reportedBarangay
             (see Farmer\DamageReportController::index()).

    Read only throughout. A farmer cannot edit a report once it is in, and
    they should not be able to: the office and a technician are acting on
    it. What this page owes them instead is a plain answer to "where has it
    got to", which is what the status line at the top is for.
--}}

@php
    $panelSeverity = $report->validation?->assessed_damage_percent;
    $panelEstimate = $report->crops->avg('estimated_damage_percent');
@endphp

<div class="mb-4">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        DR-{{ str_pad($report->id, 4, '0', STR_PAD_LEFT) }}
    </p>
    <h3 class="mt-0.5 text-lg font-bold text-foreground">
        {{ $report->disasters->pluck('name')->join(', ') ?: 'No disaster recorded' }}
    </h3>
    <p class="text-sm text-muted-foreground">
        Submitted {{ $report->created_at?->format('F d, Y') }}
    </p>

    <div class="mt-2 flex flex-wrap gap-2">
        <x-ui.status :value="$report->status" />
        @if ($report->validation?->severity)
            <x-ui.status :value="$report->validation->severity" />
        @endif
    </div>

    <x-ui.open-full-page :href="route('farmer.reports.show', $report)" />
</div>

<div class="space-y-4 border-t border-border pt-4 text-sm">

    {{-- Where it has got to. The reason a farmer opens this at all. --}}
    <div class="rounded-lg bg-muted/50 px-3 py-2.5">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</p>
        <p class="mt-1 font-medium text-foreground">
            @switch($report->status)
                @case('pending')
                    Waiting for the office to assign a technician.
                    <span class="block font-normal text-muted-foreground">Hinihintay ang technician.</span>
                    @break
                @case('assigned')
                    {{ $report->assignedTechnician?->display_name ?? 'A technician' }} will visit your farm.
                    <span class="block font-normal text-muted-foreground">May bibisitang technician.</span>
                    @break
                @case('under_verification')
                    The inspection is underway.
                    <span class="block font-normal text-muted-foreground">Isinasagawa na ang pagsusuri.</span>
                    @break
                @case('verified')
                    Inspected and verified. Waiting for the office's decision.
                    @break
                @case('approved')
                    Approved. Any assistance will appear under Assistance.
                    @break
                @case('rejected')
                    This report was not approved. Please contact the office.
                    @break
                @case('flagged')
                    The office has questions about this report.
                    @break
                @default
                    With the office.
            @endswitch
        </p>
    </div>

    {{-- Crops --}}
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Damaged crops
        </p>
        <ul class="space-y-2">
            @foreach ($report->crops as $crop)
                <li class="flex items-start justify-between gap-3">
                    <span class="min-w-0">
                        <span class="block font-medium text-foreground">
                            {{ $crop->crop_specify ?: $crop->crop?->name ?? 'Unnamed crop' }}
                        </span>
                        <span class="block text-xs text-muted-foreground">
                            Planted {{ $crop->date_planted?->format('M d, Y') ?? 'date not given' }}
                        </span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block font-semibold text-foreground">
                            {{ number_format($crop->damaged_area_hectares, 2) }} ha
                        </span>
                        <span class="block text-xs text-muted-foreground">
                            {{ round($crop->estimated_damage_percent) }}% damaged
                        </span>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- The two percentages, once a technician has been. Section 45: the
         farmer's own estimate is never overwritten, so both stay visible. --}}
    @if ($panelSeverity !== null)
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                After the inspection
            </p>
            <dl class="space-y-1.5">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">You estimated</dt>
                    <dd class="font-medium text-foreground">
                        {{ $panelEstimate !== null ? round($panelEstimate) . '%' : '-' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">Technician assessed</dt>
                    <dd class="font-bold text-foreground">{{ round($panelSeverity) }}%</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">Inspected on</dt>
                    <dd class="font-medium text-foreground">
                        {{ $report->validation?->validated_at?->format('M d, Y') ?? 'Not submitted yet' }}
                    </dd>
                </div>
            </dl>
        </div>
    @endif

    {{-- Your photos --}}
    @if ($report->photos->isNotEmpty())
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Your photos ({{ $report->photos->count() }})
            </p>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($report->photos->take(6) as $photo)
                    <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" rel="noopener"
                       class="block overflow-hidden rounded-lg border border-border">
                        <img src="{{ asset('storage/' . $photo->file_path) }}"
                             alt="Damage photo {{ $loop->iteration }}" loading="lazy"
                             class="h-20 w-full object-cover transition hover:opacity-90">
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Where --}}
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Location</p>
        <dl class="space-y-1.5">
            <div class="flex justify-between gap-3">
                <dt class="text-muted-foreground">Barangay</dt>
                <dd class="font-medium text-foreground">
                    {{ $report->reportedBarangay?->name ?? 'Not set' }}
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-muted-foreground">Coordinates</dt>
                <dd class="font-medium text-foreground">
                    @if ($report->reported_latitude && $report->reported_longitude)
                        {{ number_format((float) $report->reported_latitude, 5) }},
                        {{ number_format((float) $report->reported_longitude, 5) }}
                    @else
                        Not captured
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    @if ($report->description)
        <div>
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                What you wrote
            </p>
            <p class="whitespace-pre-line text-foreground">{{ $report->description }}</p>
        </div>
    @endif
</div>
