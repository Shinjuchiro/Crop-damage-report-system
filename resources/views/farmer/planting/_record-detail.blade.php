{{--
    ONE PLANTING RECORD, FOR THE RIGHT-HAND PANEL

    Rendered inside x-ui.detail-panel on farmer/planting/index when a row is
    picked with ?selected=<id>. The full page and the edit form are both
    still reachable from here.

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

    <x-ui.open-full-page :href="route('farmer.planting.show', $record)" />
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

    <div class="flex flex-wrap gap-2 border-t border-border pt-4">
        <x-ui.button size="sm" variant="outline" :href="route('farmer.planting.edit', $record)">
            Edit this record
        </x-ui.button>
    </div>
</div>
