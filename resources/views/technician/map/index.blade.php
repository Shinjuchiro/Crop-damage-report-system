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
    no other technicians' work). Two pin layers per section 49: where the
    farmer said the damage was, and where this technician actually verified
    it on the ground.
--}}

<div class="space-y-4">

    <div class="stagger grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Assigned Reports Shown" :value="number_format($coverage['total'])"
                   hint="With a location on file" />
        <x-ui.stat label="Farmer-Reported Pins" :value="number_format($coverage['farmer'])" />
        <x-ui.stat label="Technician-Verified Pins" :value="number_format($coverage['verified'])" tone="primary" />
    </div>

    <x-ui.card>
        <form method="GET" action="{{ route('technician.map.index') }}"
              class="flex flex-wrap items-end gap-3">
            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="status">Status</label>
                <select id="status" name="status"
                        class="h-10 rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit">Apply</x-ui.button>
            <x-ui.button variant="outline" :href="route('technician.map.index')">Clear</x-ui.button>
        </form>
    </x-ui.card>

    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-3 border-b border-border px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-foreground">Your Reports on the Map</h2>
                <p class="text-xs text-muted-foreground">Tap a pin for the farmer, crop and status, then open the full report.</p>
            </div>

            <div class="inline-flex items-center gap-4 rounded-lg border border-input px-4 py-2 text-xs text-muted-foreground"
                 x-data="{ farmerPins: true, verifiedPins: true }">
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="checkbox" x-model="farmerPins"
                           @change="window.toggleTechMapLayer && window.toggleTechMapLayer('farmer', farmerPins)"
                           class="h-3.5 w-3.5 rounded border-input text-primary focus:ring-ring">
                    Farmer-reported
                </label>
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="checkbox" x-model="verifiedPins"
                           @change="window.toggleTechMapLayer && window.toggleTechMapLayer('verified', verifiedPins)"
                           class="h-3.5 w-3.5 rounded border-input text-primary focus:ring-ring">
                    Technician-verified
                </label>
            </div>
        </div>

        <div id="technicianMap" class="h-[30rem] w-full"
             data-farmer-pins="{{ $farmerPins->toJson() }}"
             data-verified-pins="{{ $verifiedPins->toJson() }}"
             data-center-lat="{{ $center['lat'] }}"
             data-center-lng="{{ $center['lng'] }}"
             data-zoom="{{ $center['zoom'] }}"></div>

        {{-- Legend --}}
        <div class="border-t border-border px-6 py-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <span class="flex items-center gap-2 text-sm text-foreground">
                    <span class="h-4 w-4 rounded-full ring-2 ring-white" style="background:#2563eb" aria-hidden="true"></span>
                    📍 Farmer-Reported Location
                </span>
                <span class="flex items-center gap-2 text-sm text-foreground">
                    <span class="h-4 w-4 rounded-full ring-2 ring-white" style="background:#e11d48" aria-hidden="true"></span>
                    📍 Technician-Verified Location
                </span>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">
                Verified pins are coloured by the severity you assessed. This map only shows reports assigned to you -
                for damage across your whole barangay, see Damage Reports; for the office-wide picture, see the MAO map.
            </p>
        </div>
    </div>
</div>
@endsection

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('technicianMap');
            if (! el || typeof L === 'undefined') return;

            const farmerPinsData   = JSON.parse(el.dataset.farmerPins);
            const verifiedPinsData = JSON.parse(el.dataset.verifiedPins);

            const map = L.map(el).setView(
                [parseFloat(el.dataset.centerLat), parseFloat(el.dataset.centerLng)],
                parseInt(el.dataset.zoom)
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const farmerLayer   = L.layerGroup().addTo(map);
            const verifiedLayer = L.layerGroup().addTo(map);
            const bounds        = [];

            farmerPinsData.forEach(function (point) {
                bounds.push([point.lat, point.lng]);
                L.circleMarker([point.lat, point.lng], {
                    radius: 7, color: '#ffffff', weight: 2, fillColor: '#2563eb', fillOpacity: 1,
                }).bindPopup(
                    '<div style="min-width:190px">' +
                        '<p style="font-weight:700;margin:0 0 2px">' + point.farmer + '</p>' +
                        '<p style="margin:0 0 8px;color:#64748b;font-size:12px">' + point.report + ' &middot; ' + point.barangay + '</p>' +
                        '<p style="margin:0;font-size:12px"><b>Crops:</b> ' + point.crops + '</p>' +
                        '<p style="margin:0 0 8px;font-size:12px"><b>Status:</b> ' + point.status + '</p>' +
                        '<a href="' + point.url + '" style="color:#166534;font-weight:600;font-size:12px">View full report</a>' +
                    '</div>'
                ).addTo(farmerLayer);
            });

            verifiedPinsData.forEach(function (point) {
                bounds.push([point.lat, point.lng]);
                L.circleMarker([point.lat, point.lng], {
                    radius: 7, color: '#ffffff', weight: 2, fillColor: point.color, fillOpacity: 1,
                }).bindPopup(
                    '<div style="min-width:190px">' +
                        '<p style="font-weight:700;margin:0 0 2px">' + point.farmer + '</p>' +
                        '<p style="margin:0 0 8px;color:#64748b;font-size:12px">' + point.report + ' &middot; ' + point.barangay + '</p>' +
                        '<p style="margin:0;font-size:12px"><b>Severity:</b> ' + point.severity +
                            (point.assessed !== null ? ' (' + point.assessed + '%)' : '') + '</p>' +
                        '<a href="' + point.url + '" style="color:#166534;font-weight:600;font-size:12px">View full report</a>' +
                    '</div>'
                ).addTo(verifiedLayer);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
            }

            window.toggleTechMapLayer = function (which, on) {
                const layer = which === 'farmer' ? farmerLayer : verifiedLayer;
                on ? layer.addTo(map) : map.removeLayer(layer);
            };
        });
    </script>
@endpush
