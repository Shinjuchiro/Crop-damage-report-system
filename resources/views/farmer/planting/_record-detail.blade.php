{{--
    ONE PLANTING RECORD, FOR THE RIGHT-HAND PANEL

    Rendered inside x-ui.detail-panel on farmer/planting/index when a row is
    picked with ?selected=<id>. It holds the whole record (there is no "Open
    full page" any more); the edit form is reachable from the button below.

    Expects: $record, loaded with crops.crop
             (see Farmer\PlantingController::index()).

    Section 22: recording a planting is the qualifying activity that keeps a
    farmer active, which is why the submitted date is given as much weight
    here as the planting dates themselves.
--}}

<div class="mb-4">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        CP-{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }}
    </p>
    <h3 class="mt-0.5 text-lg font-bold text-foreground">
        {{ number_format($record->crops->sum('area_hectares'), 2) }} ha planted
    </h3>
    <p class="text-sm text-muted-foreground">
        {{ $record->crops->count() }}
        {{ $record->crops->count() === 1 ? 'crop' : 'crops' }}
        &middot; filed {{ $record->date_submitted?->format('F d, Y') ?? 'date not set' }}
    </p>
</div>

<div class="space-y-4 border-t border-border pt-4 text-sm">

    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            What you planted
        </p>
        <ul class="space-y-2">
            @foreach ($record->crops as $crop)
                <li class="flex items-start justify-between gap-3">
                    <span class="min-w-0">
                        <span class="block font-medium text-foreground">
                            {{ $crop->crop_specify ?: $crop->crop?->name ?? 'Unnamed crop' }}
                        </span>
                        <span class="block text-xs text-muted-foreground">
                            Planted {{ $crop->date_planted?->format('M d, Y') ?? 'date not set' }}
                        </span>
                    </span>
                    <span class="shrink-0 font-semibold text-foreground">
                        {{ number_format($crop->area_hectares, 2) }} ha
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="rounded-lg bg-muted/50 px-3 py-2.5">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total area</p>
        <p class="mt-0.5 text-lg font-bold text-foreground">
            {{ number_format($record->crops->sum('area_hectares'), 2) }} ha
        </p>
    </div>

    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Photos</p>
        @if ($record->photos->isNotEmpty())
            <div class="grid grid-cols-3 gap-2">
                @foreach ($record->photos as $photo)
                    <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                       class="block overflow-hidden rounded-lg border border-border">
                        <img src="{{ $photo->url }}" alt="Planting photo" class="h-20 w-full object-cover">
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-xs text-muted-foreground">No photos were attached.</p>
        @endif
    </div>

    @php $panelFarmer = $record->farmer; @endphp
    @if ($panelFarmer)
        <div class="border-t border-border pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farmer</p>
            <dl class="space-y-1.5">
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Name</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $panelFarmer->full_name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Association</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $panelFarmer->association?->name ?? 'Not set' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Barangay</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $panelFarmer->barangay?->name ?? 'Not set' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farm size</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $panelFarmer->farm_size_hectares ? number_format($panelFarmer->farm_size_hectares, 2) . ' ha' : 'Not set' }}</dd></div>
            </dl>
        </div>
    @endif

    <div class="flex flex-wrap gap-2 border-t border-border pt-4">
        <x-ui.button size="sm" variant="outline" :href="route('farmer.planting.edit', $record)">
            Edit this record
        </x-ui.button>
    </div>
</div>
