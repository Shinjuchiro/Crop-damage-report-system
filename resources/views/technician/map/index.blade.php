@extends('layouts.app')

@section('title', 'Maps and Visualization')
@section('heading', 'Maps and Visualization')
@section('heading-fil', 'Mapa')
@section('subheading', 'Your own assigned reports, plotted where they are.')

@section('content')

{{--
    A personal working map, not a copy of the MAO's municipality-wide one.
    Only this technician's own assigned reports appear here.

    Filters (Date Range, Disaster, Severity, Validation Status) decide WHICH
    of your reports are drawn. Display options (By report count, By worst
    severity, Verified pins, Heatmap) decide HOW. Circles, pins, heatmap and
    the cards below all come from the same filtered list, so they agree.
    Pins and the heatmap use the Technician-Verified Location: farmer damage
    reports collect no GPS of their own, so a report only has an exact
    position once it has been verified on the ground.
--}}

<div class="space-y-4">

    <div class="stagger grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Assignments" :value="number_format($coverage['reports'])"
                   hint="From {{ $mapBubbles->count() }} {{ \Illuminate\Support\Str::plural('association', $mapBubbles->count()) }}, matching the filters"
                   icon="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4" />
        <x-ui.stat label="Verified" :value="number_format($coverage['verified'])" tone="primary"
                   icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-ui.stat label="Verified Pins" :value="number_format($coverage['pinned'])"
                   hint="Reports with a technician-verified location"
                   icon="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11zM12 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
    </div>

    <x-map.filters role="technician" :action="route('technician.map.index')" :filters="$filters"
                   :keep="['shading' => $shading]" />

    <div x-data="{ pins: false, heat: false }"
         class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-3 border-b border-border px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Assignments by Association</h2>
                <p class="text-xs text-muted-foreground">Each circle is one association; the number is how many of your assignments come from its members. Tap a circle for the breakdown.</p>
            </div>

            <x-map.display-options :action="route('technician.map.index')" :shading="$shading" />
        </div>

        <x-technician.assoc-map :bubbles="$mapBubbles" height="h-[30rem]" id="techMap"
                                :shading="$shading" :pins="$verifiedPins" :layers="true" />

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-border px-6 py-3 text-xs text-foreground">
            <span class="font-semibold uppercase tracking-wide text-muted-foreground">Popup breakdown</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#facc15"></span>Awaiting Inspection</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#3b82f6"></span>Inspection Under Way</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#22c55e"></span>Verified</span>
        </div>

        <x-map.legend :shading="$shading" :legends="$legends" what="of your assignments from each association" />
    </div>
</div>
@endsection
