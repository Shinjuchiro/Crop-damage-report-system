{{--
    ONE DAMAGE REPORT, SUMMARISED FOR THE RIGHT-HAND PANEL

    Rendered inside x-ui.detail-panel on farmer/reports/index when a row is
    picked with ?selected=<id>. It holds the WHOLE report (Oct 2026): there
    is no "Open full page" any more, so everything the page showed is here.

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
        Submitted {{ ($report->submitted_at ?? $report->created_at)?->format('F d, Y') }}
        @if ($report->date_damaged)
            &middot; Crop damaged {{ $report->date_damaged->format('F d, Y') }}
        @endif
    </p>

    <div class="mt-2 flex flex-wrap gap-2">
        <x-ui.status :value="$report->status" />
        @if ($report->validation?->severity)
            <x-ui.status :value="$report->validation->severity" />
        @endif
    </div>

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

    {{-- Progress --}}
    @php
        $panelSteps = [
            ['Submitted', 'Naisumite na', true],
            ['Technician assigned', 'May naka-atas na technician', $report->assigned_technician_id !== null],
            ['Field inspection', 'Pagsusuri sa sakahan', in_array($report->status, ['under_verification', 'verified', 'approved'], true)],
            ['Verified', 'Nasuri na', in_array($report->status, ['verified', 'approved'], true)],
            ['Office decision', 'Desisyon ng tanggapan', in_array($report->status, ['approved', 'rejected'], true)],
        ];
    @endphp
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Progress</p>
        <ol class="space-y-2">
            @foreach ($panelSteps as [$label, $filipino, $done])
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full
                                 {{ $done ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                        @if ($done)
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                        @else
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span class="block {{ $done ? 'font-medium text-foreground' : 'text-muted-foreground' }}">{{ $label }}</span>
                        <span class="block text-xs text-muted-foreground">{{ $filipino }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Cause --}}
    <div class="border-t border-border pt-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">What caused the damage</p>
        <dl class="space-y-1.5">
            <div class="flex justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Cause</dt>
                <dd class="min-w-0 text-right font-medium text-foreground">{{ $report->damage_cause_label }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Declared event</dt>
                <dd class="min-w-0 text-right font-medium text-foreground">
                    @if ($report->disasters->isEmpty())
                        Not linked to a declared event
                    @else
                        {{ $report->disasters->pluck('name')->join(', ') }}
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    {{-- Crops --}}
    <div class="border-t border-border pt-4">
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
                        <span class="block text-xs text-muted-foreground">
                            Cost &#8369;{{ number_format((float) $crop->production_cost, 2) }}
                            &middot; &#8369;{{ number_format((float) $crop->farmgate_price_per_kg, 2) }}/kg
                        </span>
                    </span>
                </li>
            @endforeach
        </ul>
        <p class="mt-3 flex justify-between gap-3 rounded-lg bg-muted/50 px-3 py-2">
            <span class="text-muted-foreground">
                Total damaged area
                {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha &middot; Estimated damage cost
            </span>
            <span class="shrink-0 font-semibold text-foreground">
                &#8369;{{ number_format($report->crops->sum('total_damage_cost'), 2) }}
            </span>
        </p>
    </div>

    {{-- The inspection, once there is one. Section 45: the farmer's own
         estimate is never overwritten, so both figures stay visible. --}}
    @if ($report->validation)
        <div class="border-t border-border pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Technician inspection</p>
            <dl class="space-y-1.5">
                <div class="flex justify-between gap-3">
                    <dt class="shrink-0 text-muted-foreground">Inspected by</dt>
                    <dd class="min-w-0 text-right font-medium text-foreground">{{ $report->validation->technician?->display_name ?? 'A technician' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="shrink-0 text-muted-foreground">Inspected on</dt>
                    <dd class="min-w-0 text-right font-medium text-foreground">
                        {{ $report->validation->validated_at?->format('M d, Y') ?? 'In progress' }}
                    </dd>
                </div>
                @if ($panelSeverity !== null)
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-muted-foreground">You estimated</dt>
                        <dd class="min-w-0 text-right font-medium text-foreground">
                            {{ $panelEstimate !== null ? round($panelEstimate) . '%' : '-' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-muted-foreground">Technician assessed</dt>
                        <dd class="min-w-0 text-right font-bold text-foreground">{{ round($panelSeverity) }}%</dd>
                    </div>
                @endif
                @if ($report->validation->severity)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="shrink-0 text-muted-foreground">Severity</dt>
                        <dd class="min-w-0 text-right"><x-ui.status :value="$report->validation->severity" /></dd>
                    </div>
                @endif
            </dl>

            @if ($report->validation->notes)
                <div class="mt-3 rounded-lg bg-muted px-3 py-2.5">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Inspection notes</p>
                    <p class="mt-1 whitespace-pre-line leading-relaxed text-foreground">{{ $report->validation->notes }}</p>
                </div>
            @endif

            @if ($report->validation->photos->isNotEmpty())
                <p class="mb-2 mt-3 text-xs text-muted-foreground">Inspection photos</p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($report->validation->photos as $photo)
                        <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                           class="block overflow-hidden rounded-lg border border-border">
                            <img src="{{ $photo->url }}" alt="Inspection photo" loading="lazy"
                                 class="h-20 w-full object-cover transition hover:opacity-90">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Your photos --}}
    <div class="border-t border-border pt-4">
        @if ($report->photos->isEmpty())
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Your photos</p>
            <p class="text-muted-foreground">No photos were attached to this report.</p>
        @else
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Your photos ({{ $report->photos->count() }})
            </p>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($report->photos as $photo)
                    <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                       class="block overflow-hidden rounded-lg border border-border">
                        <img src="{{ $photo->url }}"
                             alt="Damage photo {{ $loop->iteration }}" loading="lazy"
                             class="h-20 w-full object-cover transition hover:opacity-90">
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Where --}}
    <div class="border-t border-border pt-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Location</p>
        <dl class="space-y-1.5">
            <div class="flex justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Barangay</dt>
                <dd class="min-w-0 text-right font-medium text-foreground">
                    {{ $report->reportedBarangay?->name ?? 'Not set' }}
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Coordinates</dt>
                <dd class="min-w-0 text-right font-medium text-foreground">
                    @if ($report->reported_latitude && $report->reported_longitude)
                        {{ number_format((float) $report->reported_latitude, 5) }},
                        {{ number_format((float) $report->reported_longitude, 5) }}
                    @else
                        Not captured
                    @endif
                </dd>
            </div>
            @if ($report->farm_location_description)
                <div class="flex justify-between gap-3">
                    <dt class="shrink-0 text-muted-foreground">Description</dt>
                    <dd class="min-w-0 text-right font-medium text-foreground">{{ $report->farm_location_description }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if ($report->description)
        <div class="border-t border-border pt-4">
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                What you wrote
            </p>
            <p class="whitespace-pre-line text-foreground">{{ $report->description }}</p>
        </div>
    @endif
</div>
