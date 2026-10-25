{{--
    MAP DISPLAY OPTIONS, shared by all three roles

        <x-map.display-options :action="route('association.map.index')" :shading="$shading" />

    These control HOW the filtered reports are drawn, not WHICH reports are
    drawn, so they are kept apart from the filters:

        By Report Count / By Worst Severity   how the circles are coloured (reloads, keeps the filters)
        Verified Pins / Heatmap               layers switched on the map without reloading

    The checkboxes read `pins` and `heat` from the Alpine scope of the card
    that holds the map, and call window.toggleMapLayer, defined by each map's
    script.
--}}

@props(['action', 'shading'])

<div class="flex flex-wrap items-center gap-3">
    <div class="inline-flex rounded-lg border border-input p-1">
        <a href="{{ $action }}?{{ http_build_query(array_merge(request()->except('shading'), ['shading' => 'count'])) }}"
           class="rounded-md px-4 py-1.5 text-xs font-medium {{ $shading === 'count' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted/60' }}">
            By report count
        </a>
        <a href="{{ $action }}?{{ http_build_query(array_merge(request()->except('shading'), ['shading' => 'severity'])) }}"
           class="rounded-md px-4 py-1.5 text-xs font-medium {{ $shading === 'severity' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted/60' }}">
            By worst severity
        </a>
    </div>

    <div class="inline-flex items-center gap-4 rounded-lg border border-input px-4 py-2 text-xs text-muted-foreground">
        <label class="flex cursor-pointer items-center gap-2">
            <input type="checkbox" x-model="pins"
                   @change="window.toggleMapLayer && window.toggleMapLayer('pins', pins)"
                   class="h-3.5 w-3.5 rounded border-input text-primary focus:ring-ring">
            Verified pins
        </label>
        <label class="flex cursor-pointer items-center gap-2">
            <input type="checkbox" x-model="heat"
                   @change="window.toggleMapLayer && window.toggleMapLayer('heat', heat)"
                   class="h-3.5 w-3.5 rounded border-input text-primary focus:ring-ring">
            Heatmap
        </label>
    </div>
</div>
