{{--
    THE ASSOCIATION'S OWN MAP

        <x-association.coverage-map :bubbles="$bubbles" :pins="$verifiedPins" :governed="$governed" />

    One component so the dashboard preview and the Maps and Visualization
    page can never drift apart: the same governed-barangay mask, outlines,
    numbered circles, dark popup and optional verified-pin and heatmap layers.
    Props:
        shading   'count' or 'severity' (which colour the circles take)
        height    Tailwind height class
        scroll    allow the mouse wheel to zoom (page: yes, dashboard preview: no)
--}}
@props(['bubbles', 'pins', 'governed', 'center' => ['lat' => 14.3833, 'lng' => 120.85, 'zoom' => 12],
        'shading' => 'count', 'height' => 'h-[30rem]', 'id' => 'associationMap', 'scroll' => true])

<div id="{{ $id }}" data-association-map class="{{ $height }} w-full"
     data-verified-pins="{{ $pins->toJson() }}"
     data-bubbles="{{ $bubbles->toJson() }}"
     data-shading="{{ $shading }}"
     data-geojson="{{ asset('geo/tanza-barangays.json') }}"
     data-governed="{{ $governed->toJson() }}"
     data-scroll-zoom="{{ $scroll ? '1' : '0' }}"
     data-center-lat="{{ $center['lat'] }}"
     data-center-lng="{{ $center['lng'] }}"
     data-zoom="{{ $center['zoom'] }}"></div>

@once
    @push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        /* Same dark popup and numbered circle as the MAO and Technician maps. */
        .assoc-pop .leaflet-popup-content-wrapper { background:#0f172a; color:#fff; border-radius:12px; padding:2px; }
        .assoc-pop .leaflet-popup-tip { background:#0f172a; }
        .assoc-pop .leaflet-popup-content { margin:12px 14px; width:230px !important; font-size:12px; line-height:1.5; color:#cbd5e1; }
        .assoc-pop .tip-kicker { color:#a5b4fc; font-size:10px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; }
        .assoc-pop .tip-name { color:#fff; font-size:13.5px; font-weight:700; line-height:1.35; margin:3px 0 9px; }
        .assoc-pop .tip-row { display:flex; align-items:flex-start; gap:7px; }
        .assoc-pop .tip-dot { width:9px; height:9px; border-radius:9999px; flex:none; margin-top:5px; }
        .assoc-pop .tip-sep { border-top:1px solid #1e293b; margin:8px 0 7px; }
        .assoc-pop a.tip-btn { display:block; margin-top:10px; text-align:center; background:#15803d; color:#fff !important;
            border-radius:8px; padding:6px 10px; font-weight:600; text-decoration:none; }
        .assoc-count { display:flex; align-items:center; justify-content:center; border-radius:9999px; color:#fff;
            font-weight:700; border:2px solid #fff; box-shadow:0 2px 6px rgb(0 0 0 / .3); }
    </style>
@endpush

    @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
    <script src="{{ asset('js/tanza-mask.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
          document.querySelectorAll('[data-association-map]').forEach(function (el) {
            if (typeof L === 'undefined') return;

            const verifiedPinsData = JSON.parse(el.dataset.verifiedPins);

            const map = L.map(el, { zoomSnap: 0.25, maxBoundsViscosity: 1.0, scrollWheelZoom: el.dataset.scrollZoom === '1' }).setView(
                [parseFloat(el.dataset.centerLat), parseFloat(el.dataset.centerLng)],
                parseInt(el.dataset.zoom)
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            // Only the barangays this association governs are visible (everything
            // else, including the rest of Tanza, is covered); the map cannot be
            // dragged away from them.
            const governedPsgc = JSON.parse(el.dataset.governed).map(b => b.psgc).filter(Boolean);
            maskOutsideTanza(map, '{{ asset('geo/tanza-outline.json') }}', {
                barangaysUrl: el.dataset.geojson,
                only: governedPsgc,
            }).then(b => { if (b) map.setMaxBounds(b.pad(0.2)); });


            /* Only the barangays this association governs are drawn, and the
               map cannot be dragged away from them. */
            const governed = JSON.parse(el.dataset.governed);
            const psgcs    = governed.map(b => b.psgc).filter(Boolean);
            const bubbles  = JSON.parse(el.dataset.bubbles);
            const shading  = el.dataset.shading || 'count';

            // Light fills (the low end of the count ramp) need dark text.
            const ink = (hex) => {
                const n = parseInt(hex.slice(1), 16);
                const lum = 0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255);
                return lum > 160 ? '#1f2937' : '#ffffff';
            };
            const row = (c, label, n) => '<div class="tip-row"><span class="tip-dot" style="background:' + c + '"></span><span>' +
                label + '</span><span style="margin-left:auto;color:#f1f5f9">' + n + '</span></div>';

            fetch(el.dataset.geojson).then(r => r.json()).then(function (geojson) {
                const own = L.geoJSON(geojson, {
                    filter: f => psgcs.includes(f.properties.psgc),
                    style: { color: '#15803d', weight: 2, fillColor: '#dcfce7', fillOpacity: 0.4 },
                    onEachFeature: (f, layer) => layer.bindTooltip(f.properties.name, { sticky: true }),
                }).addTo(map);
                own.bringToBack();
                if (own.getLayers().length) {
                    // Fit tightly to the governed barangays (a small padding on the
                    // dashboard preview) so the map opens zoomed in on them.
                    map.fitBounds(own.getBounds(), { padding: el.dataset.scrollZoom === '1' ? [20, 20] : [6, 6] });
                }

                // One circle per barangay, at the middle of its boundary.
                const centres = {};
                geojson.features.forEach(f => { centres[f.properties.psgc] = L.geoJSON(f).getBounds().getCenter(); });

                bubbles.forEach(function (b) {
                    const c = centres[b.psgc];
                    if (! c) return;
                    const fill = shading === 'severity' ? b.severity_color : b.count_color;
                    const size = Math.round(26 + Math.sqrt(b.total) * 8);
                    L.marker(c, {
                        icon: L.divIcon({
                            className: '',
                            html: '<div class="assoc-count" style="width:' + size + 'px;height:' + size + 'px;background:' + fill + ';color:' + ink(fill) + '">' + b.total + '</div>',
                            iconSize: [size, size],
                        }),
                    }).bindPopup(
                        '<span class="tip-kicker">Barangay</span>' +
                        '<div class="tip-name">' + b.name + '</div>' +
                        '<div class="tip-row"><span class="tip-dot" style="background:' + fill + '"></span><span>' +
                            b.total + (b.total === 1 ? ' report' : ' reports') + ' from ' + b.farmers + (b.farmers === 1 ? ' farmer' : ' farmers') + '</span></div>' +
                        '<div class="tip-row"><span class="tip-dot" style="background:' + b.severity_color + '"></span><span>Worst severity: ' + b.severity_label + '</span></div>' +
                        '<div class="tip-sep"></div>' +
                        row('#facc15', 'Awaiting Inspection', b.awaiting) +
                        row('#3b82f6', 'Inspection Under Way', b.underway) +
                        row('#22c55e', 'Verified', b.verified) +
                        '<a class="tip-btn" href="' + b.url + '">View Damage Reports</a>',
                        { className: 'assoc-pop' }
                    ).addTo(map);
                });
            }).catch(function () {});

            /* Verified pins and the heatmap, from the same filtered reports
               as the circles. Off until the checkboxes turn them on. */
            const pins = L.layerGroup();
            verifiedPinsData.forEach(function (point) {
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
                ).addTo(pins);
            });

            const heat = (typeof L.heatLayer === 'function' && verifiedPinsData.length > 0)
                ? L.heatLayer(verifiedPinsData.map(p => [p.lat, p.lng, 1]), { radius: 28, blur: 20, maxZoom: 16 })
                : null;

            window.toggleMapLayer = function (which, on) {
                const layer = which === 'heat' ? heat : pins;
                if (! layer) return;
                on ? layer.addTo(map) : map.removeLayer(layer);
            };
          });
        });
    </script>
@endpush
@endonce
