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
    An association can span more than one barangay, so a barangay filter is
    added alongside status.
--}}

<div class="space-y-4">

    <div class="stagger grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Member Reports Shown" :value="number_format($coverage['total'])"
                   hint="With a technician-verified location" />
        <x-ui.stat label="Technician-Verified Pins" :value="number_format($coverage['total'])" tone="primary" />
    </div>

    <x-ui.card>
        <form method="GET" action="{{ route('association.map.index') }}"
              class="flex flex-wrap items-end gap-3">
            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="barangay_id">Barangay</label>
                <select id="barangay_id" name="barangay_id"
                        class="h-10 rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected(($filters['barangay_id'] ?? '') == $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
            </div>
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
            <x-ui.button variant="outline" :href="route('association.map.index')">Clear</x-ui.button>
        </form>
    </x-ui.card>

    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-3 border-b border-border px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-foreground">Members' Reports on the Map</h2>
                <p class="text-xs text-muted-foreground">Tap a pin for the farmer, crop and status, then open the full report list.</p>
            </div>
        </div>

        <div id="associationMap" class="h-[30rem] w-full"
             data-verified-pins="{{ $verifiedPins->toJson() }}"
             data-center-lat="{{ $center['lat'] }}"
             data-center-lng="{{ $center['lng'] }}"
             data-zoom="{{ $center['zoom'] }}"></div>

        {{-- Legend --}}
        <div class="border-t border-border px-6 py-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <span class="flex items-center gap-2 text-sm text-foreground">
                    <span class="h-4 w-4 rounded-full ring-2 ring-white" style="background:#e11d48" aria-hidden="true"></span>
                    📍 Technician-Verified Location
                </span>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">
                Pins are coloured by the assessed severity. A report gets a pin once a technician has verified it
                on the ground. This map only shows {{ $association->name }}'s own members - for the office-wide
                picture across all associations, ask the Municipal Agriculture Office.
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
            const el = document.getElementById('associationMap');
            if (! el || typeof L === 'undefined') return;

            const verifiedPinsData = JSON.parse(el.dataset.verifiedPins);

            const map = L.map(el).setView(
                [parseFloat(el.dataset.centerLat), parseFloat(el.dataset.centerLng)],
                parseInt(el.dataset.zoom)
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const verifiedLayer = L.layerGroup().addTo(map);
            const bounds        = [];

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
                        '<a href="' + point.url + '" style="color:#166534;font-weight:600;font-size:12px">View in Damage Reports</a>' +
                    '</div>'
                ).addTo(verifiedLayer);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
            }
        });
    </script>
@endpush
