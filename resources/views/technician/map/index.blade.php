@extends('layouts.app')

@section('title', 'Maps and Visualization')
@section('heading', 'Maps and Visualization')
@section('heading-fil', 'Mapa')
@section('subheading', 'Your own assigned reports, plotted where they are.')

@section('content')

{{--
    A personal working map, not a copy of the MAO's municipality-wide one.
    Only this technician's own assigned reports appear here (proposal
    section 2: keep the map simple; no association bubbles, no heatmap,
    no other technicians' work). One pin layer: the Technician-Verified
    Location - farmer damage reports no longer collect GPS coordinates of
    their own, so a report only has an exact pin once this technician has
    actually verified it.
--}}

<div class="space-y-4">

    <div class="stagger grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Assignments" :value="number_format($mapBubbles->sum('total'))"
                   hint="From {{ $mapBubbles->count() }} {{ \Illuminate\Support\Str::plural('association', $mapBubbles->count()) }}"
                   icon="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4" />
        <x-ui.stat label="Verified" :value="number_format($mapBubbles->sum('verified'))" tone="primary"
                   icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </div>

    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Assignments by Association</h2>
            <p class="text-xs text-muted-foreground">Each circle is one association; the number is how many of your assignments come from its members. Tap a circle for the breakdown.</p>
        </div>

        <x-technician.assoc-map :bubbles="$mapBubbles" height="h-[30rem]" id="techMap" />

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-border px-6 py-3 text-xs text-foreground">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#facc15"></span>Awaiting Inspection</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#3b82f6"></span>Inspection Under Way</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#22c55e"></span>Verified</span>
        </div>
    </div>
</div>
@endsection
