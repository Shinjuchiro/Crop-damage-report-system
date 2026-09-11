@extends('layouts.app')

@section('title', 'Maps and Visualization')
@section('heading', 'Maps and Visualization')
@section('subheading', "Crop damage by Farmers' Association across Tanza, Cavite.")

@section('content')
<div x-data="{ filtersOpen: false }" class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6 space-y-5">

    {{-- Coverage --}}
    <div class="stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Damage Reports" :value="number_format($coverage['reports'])"
                     hint="Matching the current filters" />
        <x-ui.stat label="Associations Affected" :value="number_format($coverage['associations'])"
                     :suffix="'of ' . $coverage['total_assoc']" />
        <x-ui.stat label="Total Damaged Area" :value="number_format($coverage['area'], 2)" suffix="ha" />
        <x-ui.stat label="Verified Pins" :value="number_format($coverage['pinned'])"
                     hint="Locations a technician confirmed on site" />
    </div>

    @if ($coverage['unplaced'] > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60 px-4 py-3 text-sm text-amber-800">
            {{ $coverage['unplaced'] }}
            {{ \Illuminate\Support\Str::plural('association', $coverage['unplaced']) }}
            {{ $coverage['unplaced'] === 1 ? 'has' : 'have' }} no location barangay set, so
            {{ $coverage['unplaced'] === 1 ? 'it does' : 'they do' }} not appear on the map.
            Set it under <a href="{{ route('mao.associations.index') }}" class="font-medium underline">Manage Associations</a>.
        </div>
    @endif

    {{-- Filters --}}
    <div class="rounded-xl border border-border bg-card shadow-sm">
        <button type="button" @click="filtersOpen = ! filtersOpen"
                class="flex w-full items-center justify-between px-6 py-4 text-left">
            <span class="text-sm font-semibold text-foreground">Filters</span>
            <span class="flex items-center gap-2 text-xs text-muted-foreground">
                {{ request()->hasAny(['month', 'year', 'disaster_id', 'crop_id', 'barangay_id', 'association_id', 'status', 'severity'])
                    ? 'Filters applied' : 'Showing everything' }}
                <svg class="h-4 w-4 transition-transform" :class="filtersOpen && 'rotate-180'"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                </svg>
            </span>
        </button>

        <form method="GET" x-show="filtersOpen" x-cloak x-transition
              class="border-t border-border px-6 py-5">
            <input type="hidden" name="shading" value="{{ $shading }}">

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <select name="month" class="rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Any month</option>
                    @foreach (range(1, 12) as $month)
                        <option value="{{ $month }}" @selected(request('month') == $month)>
                            {{ \Carbon\Carbon::create()->month($month)->format('F') }}
                        </option>
                    @endforeach
                </select>

                <select name="year" class="rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Any year</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected(request('year') == $year)>{{ $year }}</option>
                    @endforeach
                </select>

                <select name="disaster_id" class="rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Any disaster</option>
                    @foreach ($disasters as $disaster)
                        <option value="{{ $disaster->id }}" @selected(request('disaster_id') == $disaster->id)>
                            {{ $disaster->name }}
                        </option>
                    @endforeach
                </select>

                <select name="crop_id" class="rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Any crop</option>
                    @foreach ($crops as $crop)
                        <option value="{{ $crop->id }}" @selected(request('crop_id') == $crop->id)>{{ $crop->name }}</option>
                    @endforeach
                </select>

                <select name="association_id" class="rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Any association</option>
                    @foreach ($associations as $association)
                        <option value="{{ $association->id }}" @selected(request('association_id') == $association->id)>
                            {{ $association->name }}
                        </option>
                    @endforeach
                </select>

                <select name="barangay_id" class="rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Any barangay</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected(request('barangay_id') == $barangay->id)>
                            {{ $barangay->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Any report status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ ucwords(str_replace('_', ' ', $status)) }}
                        </option>
                    @endforeach
                </select>

                <select name="severity" class="rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    <option value="">Any severity</option>
                    @foreach ($severityLegend as $level)
                        <option value="{{ $level['key'] }}" @selected(request('severity') === $level['key'])>
                            {{ $level['label'] }} ({{ $level['range'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mt-4 flex gap-3">
                <button class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                    Apply filters
                </button>
                <a href="{{ route('mao.map.index', ['shading' => $shading]) }}"
                   class="rounded-lg border border-input px-6 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Map --}}
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">

        <div class="flex flex-col gap-3 border-b border-border px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-foreground">Crop Damage by Association</h2>
                <p class="text-xs text-muted-foreground">
                    Each circle is one association, placed at its location. Bigger means more reports from its members.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="inline-flex rounded-lg border border-input p-1">
                    <a href="{{ route('mao.map.index', array_merge(request()->except('shading'), ['shading' => 'count'])) }}"
                       class="rounded-md px-4 py-1.5 text-xs font-medium {{ $shading === 'count' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted/60' }}">
                        By report count
                    </a>
                    <a href="{{ route('mao.map.index', array_merge(request()->except('shading'), ['shading' => 'severity'])) }}"
                       class="rounded-md px-4 py-1.5 text-xs font-medium {{ $shading === 'severity' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted/60' }}">
                        By worst severity
                    </a>
                </div>

                <button type="button" id="basemapToggle"
                        class="inline-flex items-center gap-2 rounded-lg border border-input px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-muted/60">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
                    </svg>
                    <span id="basemapLabel">Dark map</span>
                </button>

                <div class="inline-flex items-center gap-4 rounded-lg border border-input px-4 py-2 text-xs text-muted-foreground"
                     x-data="{ pins: false, heat: false }">
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
        </div>

        <div id="damageMap" class="h-[34rem] w-full"
             data-geojson="{{ asset('geo/tanza-barangays.json') }}"
             data-associations="{{ $associationStats->toJson() }}"
             data-points="{{ $points->toJson() }}"
             data-shading="{{ $shading }}"
             data-center-lat="{{ $center['lat'] }}"
             data-center-lng="{{ $center['lng'] }}"
             data-zoom="{{ $center['zoom'] }}"></div>

        {{-- Legend --}}
        <div class="border-t border-border px-6 py-4">
            @if ($shading === 'count')
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Damage reports filed</span>
                    @foreach ($countLegend as $band)
                        <span class="flex items-center gap-2 text-sm text-foreground">
                            <span class="h-4 w-4 rounded-full ring-2 ring-white"
                                  style="background-color: {{ $band['color'] }}"></span>
                            {{ $band['label'] }}
                            <span class="text-xs text-muted-foreground">({{ $band['total'] }})</span>
                        </span>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Colour and size both show how many reports the association's members filed. A bigger,
                    darker circle means more reports, not necessarily worse damage.
                </p>
            @else
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Worst severity recorded</span>
                    @foreach ($severityLegend as $level)
                        <span class="flex items-center gap-2 text-sm text-foreground">
                            <span class="h-4 w-4 rounded-full ring-2 ring-white"
                                  style="background-color: {{ $level['color'] }}"></span>
                            {{ $level['label'] }}
                            <span class="text-xs text-muted-foreground">({{ $level['range'] }})</span>
                            <span class="font-semibold tabular-nums text-foreground">{{ $level['total'] }}</span>
                        </span>
                    @endforeach
                    <span class="flex items-center gap-2 text-sm text-foreground">
                        <span class="h-4 w-4 rounded-full ring-2 ring-white" style="background-color: #cbd5e1"></span>
                        Not yet assessed
                    </span>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Colour shows the highest severity a technician recorded among that association's reports.
                    Circle size still shows how many reports there were.
                </p>
            @endif
        </div>
    </div>
</div>
@endsection

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        /* Leaflet puts tooltips on a single line by default, which cuts off the
           longer association names. These wrap instead. */
        .assoc-tip {
            background: #0f172a;
            border: 0;
            border-radius: 12px;
            color: #fff;
            padding: 12px 15px;
            box-shadow: 0 10px 24px -12px rgb(15 23 42 / .6);
            white-space: normal;
            width: 275px;
            max-width: 275px;
        }
        .assoc-tip::before { border-top-color: #0f172a; }
        .assoc-tip .tip-kicker {
            color: #a5b4fc; font-size: 10px; font-weight: 700;
            letter-spacing: .09em; text-transform: uppercase;
        }
        .assoc-tip .tip-name {
            font-size: 13.5px; font-weight: 700; line-height: 1.35;
            margin: 3px 0 9px; white-space: normal; overflow-wrap: anywhere;
        }
        .assoc-tip .tip-row {
            display: flex; align-items: flex-start; gap: 7px;
            font-size: 12px; line-height: 1.5; color: #cbd5e1; white-space: normal;
        }
        .assoc-tip .tip-dot {
            width: 9px; height: 9px; border-radius: 9999px;
            display: inline-block; flex: none; margin-top: 5px;
        }
        .assoc-tip .tip-sep { border-top: 1px solid #1e293b; margin: 8px 0 7px; }

        .brgy-label { background: none; border: 0; box-shadow: none; white-space: nowrap; }

        /* Barangay names and association acronyms, readable on either basemap */
        .map-light .brgy-label { color: #475569; text-shadow: 0 1px 2px #fff, 0 0 3px #fff; }
        .map-dark  .brgy-label { color: #e2e8f0; text-shadow: 0 1px 3px #000, 0 0 4px #000; }
        .map-light .assoc-code { color: #0f172a; text-shadow: 0 1px 2px #fff, 0 0 3px #fff; }
        .map-dark  .assoc-code { color: #ffffff; text-shadow: 0 1px 3px #000, 0 0 4px #000; }

        /* Dark mode without a tile provider: invert the basemap, spin the hue
           back so water still reads as blue, and take the edge off the glare.
           Scoped to .leaflet-tile-pane so only the map tiles are affected. */
        .map-dark { background: #0b1120; }
        .map-dark .leaflet-tile-pane {
            filter: invert(1) hue-rotate(180deg) brightness(0.92) contrast(0.9) saturate(0.7);
        }
        .map-dark .leaflet-control-attribution {
            background: rgba(15, 23, 42, .75);
            color: #cbd5e1;
        }
        .map-dark .leaflet-control-attribution a { color: #93c5fd; }
        .map-dark .leaflet-bar a {
            background: #1e293b; color: #e2e8f0; border-bottom-color: #334155;
        }
        .map-dark .leaflet-bar a:hover { background: #334155; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('damageMap');
            if (! el || typeof L === 'undefined') return;

            const associations = JSON.parse(el.dataset.associations);
            const points       = JSON.parse(el.dataset.points);
            const shading      = el.dataset.shading;

            const map = L.map(el).setView(
                [parseFloat(el.dataset.centerLat), parseFloat(el.dataset.centerLng)],
                parseInt(el.dataset.zoom)
            );

            /* ---------------- Basemap ----------------
               One tile source, plain OpenStreetMap, no account and no API key.
               Dark mode is a CSS filter applied to the tile pane only, so the
               barangay polygons, circles and labels above it keep their colours. */
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors | ' +
                             'Boundaries: PSA via faeldon/philippines-json-maps',
            }).addTo(map);

            let theme = 'dark';
            try { theme = localStorage.getItem('mapTheme') || 'dark'; } catch (e) { /* private mode */ }
            if (theme !== 'light') theme = 'dark';

            let boundaries = null;

            function boundaryStyle() {
                return theme === 'dark'
                    ? { color: '#64748b', weight: 1, fillColor: '#22c55e', fillOpacity: 0.12 }
                    : { color: '#94a3b8', weight: 1, fillColor: '#dcfce7', fillOpacity: 0.45 };
            }

            function applyTheme() {
                el.classList.toggle('map-dark', theme === 'dark');
                el.classList.toggle('map-light', theme === 'light');

                if (boundaries) boundaries.setStyle(boundaryStyle());

                const label = document.getElementById('basemapLabel');
                if (label) label.textContent = theme === 'dark' ? 'Light map' : 'Dark map';
            }

            applyTheme();

            const toggle = document.getElementById('basemapToggle');
            if (toggle) {
                toggle.addEventListener('click', function () {
                    theme = theme === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('mapTheme', theme); } catch (e) { /* private mode */ }
                    applyTheme();
                });
            }

            /* Circle area grows with the report count, so ten reports look ten
               times bigger in area rather than ten times wider. */
            function radiusFor(reports) {
                return 11 + Math.sqrt(reports) * 7;
            }

            function tooltipFor(row) {
                const dot = shading === 'severity' ? row.severity_color : row.count_color;
                const headline = row.reports === 0
                    ? 'No damage reports'
                    : row.reports + (row.reports === 1 ? ' damage report' : ' damage reports');

                let html =
                    '<span class="tip-kicker">Farmers\' Association</span>' +
                    '<div class="tip-name">' + row.name + '</div>' +
                    '<div class="tip-row"><span class="tip-dot" style="background:' + dot + '"></span>' +
                        '<span>' + headline + '</span></div>' +
                    '<div class="tip-sep"></div>' +
                    '<div class="tip-row"><span>Location</span>' +
                        '<span style="margin-left:auto;color:#f1f5f9">' + row.location + '</span></div>' +
                    '<div class="tip-row"><span>Registered farmers</span>' +
                        '<span style="margin-left:auto;color:#f1f5f9">' + row.members + '</span></div>';

                if (row.reports > 0) {
                    html +=
                        '<div class="tip-row"><span>Farmers affected</span>' +
                            '<span style="margin-left:auto;color:#f1f5f9">' + row.farmers + '</span></div>' +
                        '<div class="tip-row"><span>Damaged area</span>' +
                            '<span style="margin-left:auto;color:#f1f5f9">' + row.area.toFixed(2) + ' ha</span></div>' +
                        '<div class="tip-row"><span>Worst severity</span>' +
                            '<span style="margin-left:auto;color:#f1f5f9">' + row.severity_label + '</span></div>';
                }

                return html;
            }

            const bubbles = L.layerGroup().addTo(map);

            fetch(el.dataset.geojson)
                .then(response => response.json())
                .then(function (geojson) {

                    /* ---------- Barangay outlines, context only ---------- */
                    boundaries = L.geoJSON(geojson, {
                        style: boundaryStyle(),
                        onEachFeature: function (feature, layer) {
                            layer.bindTooltip(feature.properties.name, {
                                sticky: true,
                                direction: 'top',
                                className: 'brgy-label',
                            });
                        },
                    }).addTo(map);

                    boundaries.bringToBack();
                    map.fitBounds(boundaries.getBounds(), { padding: [20, 20] });

                    /* ---------- Where each barangay sits ---------- */
                    const centres = {};
                    geojson.features.forEach(function (feature) {
                        centres[feature.properties.psgc] = L.geoJSON(feature).getBounds().getCenter();
                    });

                    /* Two associations share Calibuyo, so nudge duplicates apart
                       instead of stacking one on top of the other. */
                    const seen = {};

                    associations.forEach(function (row) {
                        const centre = centres[row.psgc];
                        if (! centre) return;

                        const index = seen[row.psgc] = (seen[row.psgc] || 0);
                        seen[row.psgc]++;

                        let lat = centre.lat, lng = centre.lng;
                        if (index > 0) {
                            const angle = (index - 1) * (Math.PI * 2 / 3);
                            lat += Math.cos(angle) * 0.008;
                            lng += Math.sin(angle) * 0.008;
                        }

                        const fill = shading === 'severity' ? row.severity_color : row.count_color;

                        L.circleMarker([lat, lng], {
                            radius: radiusFor(row.reports),
                            color: '#ffffff',
                            weight: 2.5,
                            fillColor: fill,
                            fillOpacity: 0.9,
                        })
                        .bindTooltip(tooltipFor(row), {
                            direction: 'top',
                            className: 'assoc-tip',
                            offset: [0, -10],
                        })
                        .on('click', function () {
                            if (row.reports > 0) window.location = row.url;
                        })
                        .addTo(bubbles);

                        L.marker([lat, lng], {
                            icon: L.divIcon({
                                className: 'brgy-label',
                                html: '<span class="assoc-code" style="font-weight:700;font-size:11px">' +
                                      row.short + '</span>',
                                iconSize: [90, 14],
                                iconAnchor: [45, -8],
                            }),
                            interactive: false,
                        }).addTo(bubbles);
                    });
                })
                .catch(function () {
                    el.insertAdjacentHTML('beforeend',
                        '<div style="position:absolute;inset:0;z-index:500;display:flex;align-items:center;' +
                        'justify-content:center;background:#f8fafc;color:#64748b;font-size:13px;text-align:center;' +
                        'padding:24px">Barangay boundaries could not be loaded. Check that ' +
                        'public/geo/tanza-barangays.json exists.</div>');
                });

            /* ---------- Verified pins ---------- */
            const pins = L.layerGroup();

            points.forEach(function (point) {
                L.circleMarker([point.lat, point.lng], {
                    radius: 6,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: point.color,
                    fillOpacity: 1,
                })
                .bindPopup(
                    '<div style="min-width:190px">' +
                        '<p style="font-weight:700;margin:0 0 2px">' + point.farmer + '</p>' +
                        '<p style="margin:0 0 8px;color:#64748b;font-size:12px">' +
                            point.report + ' &middot; ' + point.barangay + '</p>' +
                        '<p style="margin:0;font-size:12px"><b>Association:</b> ' + point.association + '</p>' +
                        '<p style="margin:0;font-size:12px"><b>Crops:</b> ' + point.crops + '</p>' +
                        '<p style="margin:0 0 8px;font-size:12px"><b>Severity:</b> ' + point.label +
                            (point.assessed !== null ? ' (' + point.assessed + '%)' : '') + '</p>' +
                        '<a href="' + point.url + '" style="color:#166534;font-weight:600;font-size:12px">View full report</a>' +
                    '</div>'
                )
                .addTo(pins);
            });

            const heat = (typeof L.heatLayer === 'function' && points.length > 0)
                ? L.heatLayer(points.map(p => [p.lat, p.lng, 1]), { radius: 28, blur: 20, maxZoom: 16 })
                : null;

            window.toggleMapLayer = function (which, on) {
                const layer = which === 'heat' ? heat : pins;
                if (! layer) return;
                on ? layer.addTo(map) : map.removeLayer(layer);
            };
        });
    </script>
@endpush
