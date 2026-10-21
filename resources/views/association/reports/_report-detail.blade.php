{{--
    ONE DAMAGE REPORT, FOR THE DETAIL MODAL

    Rendered inside x-ui.detail-panel on association/reports/index when a row
    is opened with ?selected=<id>.

    Expects: $report, a DamageReport loaded with farmer.barangay,
             farmer.association, reportedBarangay, crops.crop, disasters,
             photos, validation.technician, validation.photos and
             assignedTechnician - already restricted to this officer's own
             members, see Association\MonitoringController::selectedReport().

    THE TWO FIGURES STAY APART

    The member's own estimate and the technician's assessed percentage are
    shown side by side and neither is ever presented as the other (sections
    9.4 and 45). The gap between them is the thing an officer is actually
    asked about.

    WHAT IS NOT HERE

    Coordinates. An officer needs to know an inspection happened and what it
    found, not the pin to seven decimal places; the barangay is enough at
    this level and section 12.4 asks that the exact location not travel
    further than it must. Also nothing editable: a report belongs to the
    member who filed it, an inspection to the technician who did it, and the
    decision to the office.
--}}

@php
    $farmer     = $report->farmer;
    $validation = $report->validation;
    $estimate   = $report->crops->max('estimated_damage_percent');
@endphp

<div class="mb-4">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        Damage Report &middot; {{ $report->reference }}
    </p>

    <h3 class="mt-0.5 text-lg font-bold text-foreground">
        @if ($farmer)
            <a href="{{ route('association.members.show', $farmer) }}" class="hover:underline">
                {{ $farmer->full_name }}
            </a>
        @else
            Unknown member
        @endif
    </h3>

    <p class="text-sm text-muted-foreground">
        {{ $report->reportedBarangay?->name ?? $farmer?->barangay?->name ?? 'No barangay' }}
        &middot; filed {{ $report->created_at?->format('F d, Y') ?? 'date not set' }}
    </p>

    <div class="mt-2">
        <x-ui.status :value="$report->status" />
    </div>
</div>

<div class="space-y-4 border-t border-border pt-4 text-sm">

    {{-- The disaster, as the member reported it. --}}
    <div>
        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cause</p>
        <p class="text-foreground">{{ $report->damage_cause_label }}</p>
        @if ($report->disasters->isNotEmpty())
            <p class="text-xs text-muted-foreground">
                {{ $report->disasters->map(fn ($d) => trim(ucwords(str_replace('_', ' ', $d->type)) . ' - ' . $d->name, ' -'))->join('; ') }}
            </p>
        @endif
    </div>

    {{-- Crops, with the member's own estimate per crop. --}}
    <div class="border-t border-border pt-4">
        <div class="mb-2 flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Damaged Crops
            </p>
            <p class="text-xs text-muted-foreground">
                Total:
                <span class="font-semibold text-foreground">
                    {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha
                </span>
            </p>
        </div>

        @forelse ($report->crops as $crop)
            <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                <p class="font-medium text-foreground">
                    {{ $crop->crop_specify ?: $crop->crop?->name ?? 'Unnamed crop' }}
                </p>
                <p class="text-muted-foreground">
                    {{ number_format((float) $crop->damaged_area_hectares, 2) }} ha damaged
                    &middot; member's estimate {{ (float) $crop->estimated_damage_percent }}%
                    &middot; planted {{ $crop->date_planted?->format('M d, Y') ?? 'date not set' }}
                </p>
            </div>
        @empty
            <p class="text-xs text-muted-foreground">No crops were recorded on this report.</p>
        @endforelse
    </div>

    {{-- The member's description, when they wrote one. --}}
    @if ($report->description)
        <div class="border-t border-border pt-4">
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Member's Remarks
            </p>
            <p class="whitespace-pre-line text-foreground">{{ $report->description }}</p>
        </div>
    @endif

    {{-- Photographs the member submitted as evidence (section 35). --}}
    <div class="border-t border-border pt-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Member's Photos
        </p>

        @if ($report->photos->isNotEmpty())
            <div class="grid grid-cols-3 gap-2">
                @foreach ($report->photos as $photo)
                    <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                       class="block overflow-hidden rounded-lg border border-border">
                        <img src="{{ $photo->url }}" alt="Damage photo"
                             class="h-16 w-full object-cover transition hover:scale-105">
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-xs text-muted-foreground">No photos were attached to this report.</p>
        @endif
    </div>

    {{-- The inspection. Listed even when it has not happened, with what it
         is waiting for: an empty section would read as "nothing to
         inspect", which is a different thing. --}}
    <div class="border-t border-border pt-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Field Inspection
        </p>

        @if ($validation?->validated_at)
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-border bg-muted/40 px-3 py-2">
                    <p class="text-xs text-muted-foreground">Member estimated</p>
                    <p class="text-base font-bold text-foreground">
                        {{ $estimate !== null ? (float) $estimate . '%' : 'Not given' }}
                    </p>
                </div>
                <div class="rounded-lg border border-border bg-muted/40 px-3 py-2">
                    <p class="text-xs text-muted-foreground">Technician assessed</p>
                    <p class="text-base font-bold text-foreground">
                        {{ $validation->assessed_damage_percent !== null
                            ? (float) $validation->assessed_damage_percent . '%'
                            : 'Not given' }}
                    </p>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                @if ($validation->severity)
                    <x-ui.status :value="$validation->severity" />
                @endif
                <span class="text-xs text-muted-foreground">
                    Inspected by
                    {{ $validation->technician?->display_name
                        ?? $validation->technician?->username
                        ?? 'a technician' }}
                    on {{ $validation->validated_at->format('M d, Y') }}
                </span>
            </div>

            @if ($validation->notes)
                <p class="mt-2 whitespace-pre-line text-xs text-muted-foreground">
                    {{ $validation->notes }}
                </p>
            @endif

            @if ($validation->photos->isNotEmpty())
                <div class="mt-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Inspection Photos
                    </p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($validation->photos as $photo)
                            <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                               class="block overflow-hidden rounded-lg border border-border">
                                <img src="{{ $photo->url }}" alt="Inspection photo"
                                     class="h-16 w-full object-cover transition hover:scale-105">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @elseif ($validation)
            <p class="text-xs text-muted-foreground">
                A technician has started the inspection but has not submitted it yet.
            </p>
        @elseif ($report->assignedTechnician)
            <p class="text-xs text-muted-foreground">
                Assigned to {{ $report->assignedTechnician->display_name ?? $report->assignedTechnician->username }}.
                The inspection has not started yet.
            </p>
        @else
            <p class="text-xs text-muted-foreground">
                The office has not assigned a technician to this report yet.
            </p>
        @endif
    </div>

    {{-- Section 10.5: an inspection is not an assistance decision, and an
         officer should not read one as the other. --}}
    <p class="border-t border-border pt-4 text-xs text-muted-foreground">
        A completed inspection is not a decision on assistance. Allocation is the
        Municipal Agriculture Office's to make, and appears under Assistance once decided.
    </p>
</div>
