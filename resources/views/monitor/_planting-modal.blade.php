{{--
    ONE CROP PLANTED REPORT, FOR THE WIDE DETAIL MODAL

    Shared by the office (mao/crop-planting) and the association
    (association/planting) so both roles read a planting report the same way.
    What differs is only WHO the data was fetched for: the association's
    controller finds the record inside its own membership, and this file
    never looks anything up that the controller did not already hand it.

    Expects
        $record         CropPlantingRecord with farmer.{association,barangay},
                        crops.crop and photos loaded
        $relatedDamage  planting-crop-id => damage reports filed for that
                        planting (empty collection is fine)
        $role           'mao' or 'association'

    A planting report is one submission, and a submission can hold several
    crops, so each crop is listed under the one report ID.
--}}
@php
    $isMao   = $role === 'mao';
    $farmer  = $record->farmer;
    $reportId = 'REC-' . str_pad((string) $record->id, 4, '0', STR_PAD_LEFT);
    $location = trim(($farmer->address ? $farmer->address . ', ' : '') . ($farmer->barangay?->name ?? ''), ', ');
    $totalArea = (float) $record->crops->sum('area_hectares');
    $damageUrl = fn ($report) => $isMao
        ? route('mao.damage-reports.index', ['selected' => $report->id])
        : route('association.reports.index', ['selected' => $report->id]);
@endphp

<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Crop Planted Report &middot; {{ $reportId }}</p>
        <h3 class="mt-0.5 text-lg font-bold text-foreground">
            @if ($isMao)
                <a href="{{ route('mao.farmers.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
            @else
                <a href="{{ route('association.members.index', ['selected' => $farmer->id]) }}" class="hover:underline">{{ $farmer->full_name }}</a>
            @endif
        </h3>
        <p class="text-sm text-muted-foreground">
            {{ $farmer->association?->name ?? 'No association' }} &middot; {{ $farmer->barangay?->name ?? 'No barangay' }}
        </p>
    </div>
    @if ($record->archived_at)
        <span class="inline-flex rounded bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">Archived</span>
    @endif
</div>

<div class="space-y-4">

    {{-- LEFT: the report itself --}}
    <div class="space-y-4">
        <div class="border-t border-border pt-4 text-sm">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Planting Report</p>
            <dl class="space-y-1.5">
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Planting Report ID</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $reportId }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Date Submitted</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $record->date_submitted?->format('F d, Y') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farmer</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->full_name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farmers' Association</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->association?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farm Location</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $location ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Total Planted Area</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ number_format($totalArea, 2) }} ha</dd></div>
                {{-- A planting report has no state of its own; the status the list shows
                     for it is the farmer's Active/Inactive standing, so that is what is shown. --}}
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Status</dt>
                    <dd class="min-w-0 text-right font-medium text-foreground">
                        {{ $record->archived_at ? 'Archived ' . $record->archived_at->format('M d, Y') : ucfirst((string) ($farmer->activity_status ?: '-')) }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="border-t border-border pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crops Planted</p>

            <div class="space-y-2">
                @foreach ($record->crops as $recordCrop)
                    @php
                        $harvest = $recordCrop->crop?->estimatedHarvest($recordCrop->date_planted);
                        $damage  = $relatedDamage->get($recordCrop->id, collect());
                    @endphp
                    <div class="rounded-lg bg-muted/50 px-3 py-2.5 text-sm">
                        <p class="flex flex-wrap items-center gap-2 font-semibold text-foreground">
                            {{ $recordCrop->crop?->name ?? '-' }}@if ($recordCrop->crop_specify) ({{ $recordCrop->crop_specify }})@endif
                            @if ($recordCrop->crop?->is_hvcc)
                                <span class="rounded bg-lime-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-lime-800">HVCC</span>
                            @endif
                        </p>
                        <dl class="mt-2 space-y-1 text-xs">
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Planting date</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $recordCrop->date_planted?->format('M d, Y') ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Planted area</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ number_format((float) $recordCrop->area_hectares, 2) }} ha</dd></div>
                            <div class="flex justify-between gap-3">
                                <dt class="shrink-0 text-muted-foreground">Expected harvest (estimate)</dt>
                                <dd class="min-w-0 text-right font-medium text-foreground">
                                    @if ($harvest)
                                        {{ $harvest->format('M d, Y') }}
                                    @else
                                        <span class="font-normal text-muted-foreground">Not available</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <p class="mt-2 text-xs text-muted-foreground">
                            @if ($damage->isNotEmpty())
                                Damage reported for this planting:
                                @foreach ($damage as $report)
                                    <a href="{{ $damageUrl($report) }}"
                                       class="font-semibold text-green-800 hover:underline">{{ $report->full_reference }}</a>@if (! $loop->last), @endif
                                @endforeach
                            @else
                                No damage report has been filed for this planting.
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RIGHT: the photographs the farmer attached --}}
    <div class="border-t border-border pt-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Photos</p>
        @if ($record->photos->isNotEmpty())
            <div class="grid grid-cols-2 gap-2">
                @foreach ($record->photos as $photo)
                    <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                       class="block overflow-hidden rounded-lg border border-border">
                        <img src="{{ $photo->url }}" alt="Planting photo"
                             class="h-24 w-full object-cover transition hover:scale-105">
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-xs text-muted-foreground">No photos were attached to this report.</p>
        @endif
    </div>
</div>
