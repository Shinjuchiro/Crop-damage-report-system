@extends('layouts.app')

@section('title', 'Maps and Visualization')
@section('heading', 'Maps and Visualization')
@section('heading-fil', 'Mapa')
@section('subheading', $association->name . '\'s own members, plotted where they are.')

@section('content')

{{--
    A personal working map for this association, not a copy of the MAO's
    municipality-wide one. Only this association's own members' reports
    appear here (proposal section 2: keep the map simple; no association
    bubbles here - reusing that view would reduce this very association to
    one dot). One pin layer: the Technician-Verified Location - farmer
    damage reports no longer collect GPS coordinates of their own, so a
    report only has an exact pin once a technician has actually verified it.
    Circles are one per governed barangay; filters are Date Range, Disaster
    and Severity; Verified pins and Heatmap are optional layers.
--}}

<div class="space-y-4">

    <div class="stagger grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Member Reports" :value="number_format($coverage['reports'])"
                   hint="From {{ $coverage['farmers'] }} {{ \Illuminate\Support\Str::plural('farmer', $coverage['farmers']) }}, matching the filters"
                   icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01" />
        <x-ui.stat label="Barangays With Reports" :value="number_format($coverage['barangays'])"
                   :suffix="'of ' . $governed->count()"
                   icon="M4 8V5a1 1 0 011-1h3M20 8V5a1 1 0 00-1-1h-3M4 16v3a1 1 0 001 1h3M20 16v3a1 1 0 01-1 1h-3" />
        <x-ui.stat label="Technician-Verified Pins" :value="number_format($coverage['pinned'])" tone="primary"
                   hint="Reports with a verified location"
                   icon="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11zM12 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
    </div>

    {{-- Date Range, Disaster, Severity. No association picker: this map is
         always your own association. --}}
    <x-map.filters role="association" :action="route('association.map.index')" :filters="$filters"
                   :keep="['shading' => $shading]" />

    <div x-data="{ pins: false, heat: false }"
         class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-3 border-b border-border px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Members' Reports by Barangay</h2>
                <p class="text-xs text-muted-foreground">Limited to the barangays your association governs. Each circle is one barangay; tap it for the breakdown.</p>
            </div>

            <x-map.display-options :action="route('association.map.index')" :shading="$shading" />
        </div>

        <x-association.coverage-map :bubbles="$bubbles" :pins="$verifiedPins" :governed="$governed"
                                    :center="$center" :shading="$shading" />

        <x-map.legend :shading="$shading" :legends="$legends" what="reports your members filed in each barangay" />

        <div class="border-t border-border px-6 py-3">
            <p class="text-xs text-muted-foreground">
                This map only shows {{ $association->name }}'s own members - for the office-wide
                picture across all associations, ask the Municipal Agriculture Office.
            </p>
        </div>
    </div>
</div>
@endsection
