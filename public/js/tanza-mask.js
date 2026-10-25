/*
 * Hide everything on a Leaflet map that is not Tanza.
 *
 * maxBounds can only be a rectangle, and Tanza is not one, so panning
 * limits alone still leave neighbouring towns (Trece Martires, Naic,
 * General Trias, Rosario, Manila Bay) visible in the corners. This lays an
 * opaque sheet over the whole world with a Tanza-shaped hole cut out of it,
 * so the map shows Tanza and nothing else.
 *
 *     maskOutsideTanza(map, '{{ asset('geo/tanza-outline.json') }}');
 *
 * An association sees only the barangays it governs. Pass them as a third
 * argument and the other barangays are covered too:
 *
 *     maskOutsideTanza(map, outlineUrl, { barangaysUrl: '...', only: ['PH0421...'] });
 *
 * The outline (public/geo/tanza-outline.json) is the union of the 41
 * barangay polygons in tanza-barangays.json. If that file cannot be loaded
 * the map simply stays as it was.
 *
 * The sheet sits in its own pane above the map tiles and below the barangay
 * shapes and markers, and is not interactive, so nothing on the map stops
 * responding to clicks.
 */
(function () {
    window.maskOutsideTanza = function (map, outlineUrl, options) {
        options = options || {};
        if (! map || ! window.L) { return Promise.resolve(null); }

        if (! map.getPane('tanzaMask')) {
            map.createPane('tanzaMask');
            map.getPane('tanzaMask').style.zIndex = 350;
            map.getPane('tanzaMask').style.pointerEvents = 'none';
        }

        return fetch(outlineUrl)
            .then(function (r) { return r.json(); })
            .then(function (geo) {
                var geometry = geo.features[0].geometry;
                var ring = geometry.coordinates[0].map(function (c) { return [c[1], c[0]]; });
                window.tanzaRing = ring;   // [lat, lng] pairs, for insideTanza()

                var world = [[-90, -540], [-90, 540], [90, 540], [90, -540]];
                var page  = getComputedStyle(document.body).backgroundColor;

                L.polygon([world, ring], {
                    pane: 'tanzaMask',
                    stroke: false,
                    fillColor: (page && page !== 'rgba(0, 0, 0, 0)') ? page : '#eef2f6',
                    fillOpacity: 1,
                    interactive: false,
                }).addTo(map);

                var tanzaBounds = L.polygon(ring).getBounds();

                if (! options.only || ! options.only.length || ! options.barangaysUrl) {
                    return tanzaBounds;
                }

                // Cover every barangay that is NOT in the allowed list, with
                // the same opaque colour, so only the governed ones remain.
                // Each is drawn with a stroke of that colour as well, so no
                // hairline of map shows along the shared edges.
                var fill = (page && page !== 'rgba(0, 0, 0, 0)') ? page : '#eef2f6';

                return fetch(options.barangaysUrl)
                    .then(function (r) { return r.json(); })
                    .then(function (barangays) {
                        L.geoJSON(barangays, {
                            pane: 'tanzaMask',
                            filter: function (f) { return options.only.indexOf(f.properties.psgc) === -1; },
                            style: { stroke: true, color: fill, weight: 2, fillColor: fill, fillOpacity: 1 },
                            interactive: false,
                        }).addTo(map);

                        var allowed = L.geoJSON(barangays, {
                            filter: function (f) { return options.only.indexOf(f.properties.psgc) !== -1; },
                        });

                        return allowed.getLayers().length ? allowed.getBounds() : tanzaBounds;
                    });
            })
            .catch(function () { return null; });
    };

    /*
     * True when the point is inside Tanza. Answers true while the outline has
     * not loaded yet (the server checks again), so a slow connection never
     * blocks the first tap.
     */
    window.insideTanza = function (lat, lng) {
        var ring = window.tanzaRing;
        if (! ring) { return true; }

        var inside = false;
        for (var i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            var yi = ring[i][0], xi = ring[i][1], yj = ring[j][0], xj = ring[j][1];
            if (((yi > lat) !== (yj > lat)) && (lng < (xj - xi) * (lat - yi) / ((yj - yi) || 1e-12) + xi)) {
                inside = ! inside;
            }
        }
        return inside;
    };
})();
