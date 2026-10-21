{{--
    ONE PLANTING RECORD, FOR THE DETAIL MODAL

    Rendered inside x-ui.detail-panel on association/planting/index when a
    row is opened with ?selected=<id>.

    Expects: $record, a CropPlantingRecord loaded with farmer.barangay,
             farmer.association, crops.crop and photos, and already
             restricted to this officer's own members - see
             Association\MonitoringController::selectedPlanting().

    WHY THIS EXISTS AT ALL

    The table above it now carries four columns (revision section 3.1), so
    the crop, the planting dates, the areas and the photographs had to have
    somewhere to go. This is that somewhere. Everything the list stopped
    showing is here, and nothing else: section 3.2 asks that View reveal the
    detail rather than repeat a summary the table already gave.

    READ ONLY, LIKE EVERY ASSOCIATION SCREEN

    An officer watches the membership; they do not file or amend a member's
    record. So there is no Edit, no Archive and no form here, only the
    member's name as a link to their own profile.
--}}

@php
    $farmer = $record->farmer;
@endphp

<div class="mb-4">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        Planting Record &middot; REC-{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }}
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
        {{ $farmer?->barangay?->name ?? 'No barangay' }}
        &middot; filed {{ $record->date_submitted?->format('F d, Y') ?? 'date not set' }}
    </p>
</div>

<div class="space-y-4 border-t border-border pt-4 text-sm">

    {{-- What was planted. The area total is given once at the top rather
         than left for the reader to add up, because it is the figure an
         officer is asked for. --}}
    <div>
        <div class="mb-2 flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Crops Planted
            </p>
            <p class="text-xs text-muted-foreground">
                Total:
                <span class="font-semibold text-foreground">
                    {{ number_format($record->crops->sum('area_hectares'), 2) }} ha
                </span>
            </p>
        </div>

        @forelse ($record->crops as $crop)
            <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                <p class="font-medium text-foreground">
                    {{ $crop->crop_specify ?: $crop->crop?->name ?? 'Unnamed crop' }}
                </p>
                <p class="text-muted-foreground">
                    Planted {{ $crop->date_planted?->format('M d, Y') ?? 'date not set' }}
                    &middot; {{ number_format((float) $crop->area_hectares, 2) }} ha
                </p>
            </div>
        @empty
            <p class="text-xs text-muted-foreground">No crops were recorded on this submission.</p>
        @endforelse
    </div>

    {{-- Photographs, if the member attached any. --}}
    <div class="border-t border-border pt-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Photos</p>

        @if ($record->photos->isNotEmpty())
            <div class="grid grid-cols-3 gap-2">
                @foreach ($record->photos as $photo)
                    <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                       class="block overflow-hidden rounded-lg border border-border">
                        <img src="{{ $photo->url }}" alt="Planting photo"
                             class="h-16 w-full object-cover transition hover:scale-105">
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-xs text-muted-foreground">No photos were attached to this record.</p>
        @endif
    </div>

    {{-- Section 21 and 22, where it is useful: this record is a qualifying
         activity, so it is what keeps the member Active. --}}
    @if ($farmer)
        <div class="border-t border-border pt-4">
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Member Status
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.status :value="$farmer->activity_status" />
                <span class="text-xs text-muted-foreground">
                    @if ($farmer->has_filed_planting_report)
                        Last planting report {{ $farmer->last_activity_date->format('M d, Y') }}
                    @else
                        No planting report yet
                    @endif
                </span>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                Recording a planting is what keeps a member active. A member with no planting
                record for {{ \App\Models\Farmer::INACTIVITY_MONTHS }} months becomes Inactive.
            </p>
        </div>
    @endif
</div>
