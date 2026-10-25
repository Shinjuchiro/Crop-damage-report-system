{{--
    THE TECHNICIAN'S ASSOCIATION MAP

        <x-technician.assoc-map :bubbles="$mapBubbles" height="h-72" />

    A numbered circle per association (how many of this technician's
    assignments come from its members), with the same dark popup the
    office's map uses: Awaiting Inspection (yellow), Inspection Under Way
    (blue) and Verified (green). "View Details" opens this technician's
    assignment list filtered to that association. Locked to Tanza.
--}}

{{-- Optional, used by the full Map page only (the dashboard preview passes none):
        shading   'count' or 'severity' - which colour the circles take
        pins      technician-verified pins to draw on top (switched by the Verified pins checkbox)
        zoomIn    how many zoom levels closer than "whole of Tanza just fits" to open at (default 0.5).
                  Fitting the whole municipality into a wide, short box left it small, so it opens
                  closer, centred on Tanza, and can be dragged to the edges.
        layers    true on the Map page: registers window.toggleMapLayer for the pins and heatmap checkboxes --}}
@props(['bubbles', 'height' => 'h-72', 'id' => 'techAssocMap', 'shading' => 'count', 'pins' => [], 'layers' => false, 'zoomIn' => 0.5])

<div id="{{ $id }}" class="{{ $height }} w-full"
     data-geojson="{{ asset('geo/tanza-barangays.json') }}"
     data-bubbles="{{ $bubbles->toJson() }}"
     data-shading="{{ $shading }}"
     data-pins="{{ json_encode($pins) }}"
     data-layers="{{ $layers ? '1' : '0' }}"
     data-zoom-in="{{ $zoomIn }}"></div>

@once
    @push('head')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
        <style>
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
                document.querySelectorAll('[data-bubbles]').forEach(function (el) {
                    if (typeof L === 'undefined') return;
                    const bubbles = JSON.parse(el.dataset.bubbles);
                    const shading = el.dataset.shading || 'count';
                    const pinData = JSON.parse(el.dataset.pins || '[]');
                    // Light fills (the low end of the count ramp) need dark text.
                    const ink = (hex) => {
                        const n = parseInt(hex.slice(1), 16);
                        const lum = 0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255);
                        return lum > 160 ? '#1f2937' : '#ffffff';
                    };
                    const map = L.map(el, { scrollWheelZoom: false, maxBoundsViscosity: 1.0, zoomSnap: 0.25 })
                        .setView([14.3833, 120.85], 12);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(map);
            maskOutsideTanza(map, '{{ asset('geo/tanza-outline.json') }}');


                    // Dashboard preview (no colours supplied) keeps its own ramp.
                    const legacy = (n) => n >= 10 ? '#b91c1c' : n >= 5 ? '#ea580c' : n >= 2 ? '#ca8a04' : '#15803d';
                    const colour = (b) => b.count_color
                        ? (shading === 'severity' ? b.severity_color : b.count_color)
                        : legacy(b.total);
                    const row = (c, label, n) => '<div class="tip-row"><span class="tip-dot" style="background:' + c + '"></span><span>' +
                        label + '</span><span style="margin-left:auto;color:#f1f5f9">' + n + '</span></div>';

                    /* Verified pins and the heatmap, built from the same filtered
                       reports as the circles. Off until the checkboxes turn them on. */
                    const pinLayer = L.layerGroup();
                    pinData.forEach(function (p) {
                        L.circleMarker([p.lat, p.lng], { radius: 7, color: '#ffffff', weight: 2, fillColor: p.color, fillOpacity: 1 })
                            .bindPopup('<div style="min-width:190px"><p style="font-weight:700;margin:0 0 2px">' + p.farmer + '</p>' +
                                '<p style="margin:0 0 8px;color:#64748b;font-size:12px">' + p.report + ' &middot; ' + p.barangay + '</p>' +
                                '<p style="margin:0 0 8px;font-size:12px"><b>Severity:</b> ' + p.severity +
                                    (p.assessed !== null ? ' (' + p.assessed + '%)' : '') + '</p>' +
                                '<a href="' + p.url + '" style="color:#166534;font-weight:600;font-size:12px">View report</a></div>')
                            .addTo(pinLayer);
                    });
                    const heatLayer = (typeof L.heatLayer === 'function' && pinData.length > 0)
                        ? L.heatLayer(pinData.map(p => [p.lat, p.lng, 1]), { radius: 28, blur: 20, maxZoom: 16 })
                        : null;
                    if (el.dataset.layers === '1') {
                        window.toggleMapLayer = function (which, on) {
                            const layer = which === 'heat' ? heatLayer : pinLayer;
                            if (! layer) return;
                            on ? layer.addTo(map) : map.removeLayer(layer);
                        };
                    }

                    fetch(el.dataset.geojson).then(r => r.json()).then(function (geojson) {
                        const outline = L.geoJSON(geojson, {
                            style: { color: '#94a3b8', weight: 1, fillColor: '#dcfce7', fillOpacity: 0.4 },
                        }).addTo(map);
                        outline.bringToBack();
                        const tanza = outline.getBounds();
                        map.fitBounds(tanza, { padding: [12, 12] });
                        map.setView(tanza.getCenter(), map.getZoom() + parseFloat(el.dataset.zoomIn || '0.5'), { animate: false });
                        map.setMaxBounds(outline.getBounds().pad(0.15));

                        const centres = {};
                        geojson.features.forEach(f => { centres[f.properties.psgc] = L.geoJSON(f).getBounds().getCenter(); });
                        const seen = {};

                        bubbles.forEach(function (b) {
                            const c = centres[b.psgc];
                            if (! c) return;
                            const i = seen[b.psgc] = (seen[b.psgc] || 0);
                            seen[b.psgc]++;
                            let lat = c.lat, lng = c.lng;
                            if (i > 0) { const a = (i - 1) * (Math.PI * 2 / 3); lat += Math.cos(a) * 0.008; lng += Math.sin(a) * 0.008; }

                            const size = Math.round(26 + Math.sqrt(b.total) * 8);
                            L.marker([lat, lng], {
                                icon: L.divIcon({
                                    className: '',
                                    html: '<div class="assoc-count" style="width:' + size + 'px;height:' + size + 'px;background:' + colour(b) + ';color:' + ink(colour(b)) + '">' + b.total + '</div>',
                                    iconSize: [size, size],
                                }),
                            }).bindPopup(
                                '<span class="tip-kicker">Farmers\' Association</span>' +
                                '<div class="tip-name">' + b.name + '</div>' +
                                '<div class="tip-row"><span class="tip-dot" style="background:' + colour(b) + '"></span><span>' +
                                    b.total + (b.total === 1 ? ' assignment' : ' assignments') + '</span></div>' +
                                (b.severity_label ? '<div class="tip-row"><span class="tip-dot" style="background:' + b.severity_color + '"></span><span>Worst severity: ' + b.severity_label + '</span></div>' : '') +
                                '<div class="tip-sep"></div>' +
                                row('#facc15', 'Awaiting Inspection', b.awaiting) +
                                row('#3b82f6', 'Inspection Under Way', b.underway) +
                                row('#22c55e', 'Verified', b.verified) +
                                '<a class="tip-btn" href="' + b.url + '">View Details</a>',
                                { className: 'assoc-pop' }
                            ).addTo(map);
                        });
                    }).catch(function () {
                        el.insertAdjacentHTML('beforeend', '<div style="position:absolute;inset:0;z-index:500;display:flex;align-items:center;justify-content:center;background:#f8fafc;color:#64748b;font-size:13px;padding:24px">Map could not be loaded.</div>');
                    });
                });
            });
        </script>
    @endpush
@endonce
