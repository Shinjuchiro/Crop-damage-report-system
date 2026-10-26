@extends('layouts.app')

@section('title', 'Executive Dashboard')

{{-- The greeting banner below carries the name and the role. --}}
@section('hideHeading', true)

@section('header-actions')
    <form method="GET" class="flex items-center gap-2">
        <div class="relative">
            <select name="period"
                    class="appearance-none rounded-full bg-primary py-2.5 pl-5 pr-10 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-[#2f9e41]">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-white"
                 fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
            </svg>
        </div>

        <button type="submit"
                class="flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#268336]">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/>
            </svg>
            Filter
        </button>
    </form>
@endsection

@section('content')

    <x-ui.greeting :name="auth()->user()->display_name"
                   role="Municipal Agriculture Office"
                   :href="route('mao.profile')" />

    {{-- Today's weather over Tanza. Renders nothing at all if the
         forecast cannot be fetched. --}}
    <x-ui.weather />

    {{-- ===================== ASSISTANCE DISPUTES ===================== --}}
    @if ($disputeCount > 0)
        <x-ui.alert variant="warning" title="Assistance disputes need follow-up" class="mb-5">
            <p>
                {{ $disputeCount }} {{ $disputeCount === 1 ? 'distribution has' : 'distributions have' }}
                been marked "not received" by the farmer. The association recorded handing it out; the
                farmer says otherwise.
            </p>
            <a href="{{ route('mao.assistance-allocations.disputes') }}"
               class="mt-1.5 inline-block text-sm font-semibold underline hover:no-underline">
                Review disputes
            </a>
        </x-ui.alert>
    @endif

    {{-- ===================== HEADLINE FIGURES ===================== --}}
    {{-- Two across on a phone, not one, now that the stat card is compact
         below sm: all four headline figures fit on one screen. --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-ui.stat label="Total Affected Farmers"
                     :value="number_format($headline['affected_farmers'])"
                     :hint="'Farmers with a damage report, ' . strtolower($headline['period_label'])"
                     icon="M12 9.5v4M12 17h.01M10.3 3.9L2.5 17.5A1.7 1.7 0 004 20h16a1.7 1.7 0 001.5-2.5L13.7 3.9a1.7 1.7 0 00-3 0z" />

        <x-ui.stat label="Active Reports"
                     :value="number_format($headline['active_reports'])"
                     :suffix="strtolower($headline['period_label'])"
                     hint="Not yet verified or closed"
                     icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01" />

        <x-ui.stat label="Validated Reports"
                     :value="$headline['validated_rate'] . '%'"
                     :suffix="strtolower($headline['period_label'])"
                     hint="Share of reports a technician has verified"
                     icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8" />

        <x-ui.stat label="Assistance Distributed"
                     :value="$headline['assistance_rate'] . '%'"
                     suffix="of affected farmers"
                     hint="Farmers who confirmed they received assistance"
                     icon="M20 7.5l-8-4.5-8 4.5m16 0v9l-8 4.5-8-4.5v-9m16 0l-8 4.5m0 0l-8-4.5m8 4.5V21" />
    </div>

    {{-- ===================== DAMAGE MAP ===================== --}}
    {{-- A preview of the full Maps and Visualization page (mao.map.index):
         the same association bubbles, shaded by report count, with no stat
         cards and no filters here - just the map and its legend. --}}
    <div class="mb-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-1 border-b border-border px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Crop Damage by Association</h2>
                <p class="text-xs text-muted-foreground">
                    Each circle is one association. Bigger and darker means more reports from its members.
                </p>
            </div>
            <a href="{{ route('mao.map.index') }}" class="text-sm font-medium text-primary hover:underline">
                Open full map &amp; filters
            </a>
        </div>

        <div id="dashboardMap" class="h-[15rem] w-full"
             data-geojson="{{ asset('geo/tanza-barangays.json') }}"
             data-associations="{{ $mapAssociations->toJson() }}"
             data-center-lat="{{ $mapCenter['lat'] }}"
             data-center-lng="{{ $mapCenter['lng'] }}"
             data-zoom="{{ $mapCenter['zoom'] }}"></div>

        <div class="border-t border-border px-6 py-2.5">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Damage reports filed</span>
                @foreach ($mapCountLegend as $band)
                    <span class="flex items-center gap-1.5 text-xs text-foreground">
                        <span class="h-3 w-3 rounded-full ring-2 ring-white"
                              style="background-color: {{ $band['color'] }}"></span>
                        {{ $band['label'] }}
                        <span class="text-muted-foreground">({{ $band['total'] }})</span>
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===================== CHARTS ===================== --}}
    <div class="grid gap-5 lg:grid-cols-2">

        {{-- Reports summary --}}
        <div class="min-w-0 rounded-xl border border-border bg-card p-6 shadow-sm">
            <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Reports Summary</h2>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Damage reports submitted by farmers against inspections completed by technicians, last 5 months.
            </p>

            @php $hasMonthly = $monthly->sum('submitted') + $monthly->sum('validated') > 0; @endphp

            @if ($hasMonthly)
                <div class="mt-4 flex items-center justify-center gap-6 text-xs text-muted-foreground">
                    <span class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#15803d]"></span> Submitted
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#0284c7]"></span> Validated
                    </span>
                </div>

                {{--
                    relative + min-w-0 + max-w-full is what keeps this chart
                    inside the card on a phone. Chart.js writes an inline pixel
                    width onto the canvas from whatever it measures the wrapper
                    to be, and a canvas that ends up even slightly too wide
                    pushes the card past the screen edge, which is the sideways
                    scroll that showed up on the MAO dashboard in mobile view.
                    min-w-0 lets the wrapper shrink with its column instead of
                    holding the canvas's own width, and max-width:100% caps the
                    inline width Chart.js writes so a transient overshoot during
                    its first resize can never widen the page.
                --}}
                <div class="relative mt-3 h-64 min-w-0">
                    <canvas id="reportsSummaryChart" class="max-w-full"
                            data-labels="{{ json_encode($monthly->pluck('label')) }}"
                            data-submitted="{{ json_encode($monthly->pluck('submitted')) }}"
                            data-validated="{{ json_encode($monthly->pluck('validated')) }}"></canvas>
                </div>
            @else
                <div class="mt-4 flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-border text-center">
                    <p class="text-sm font-medium text-muted-foreground">No reports yet</p>
                    <p class="mt-1 text-xs text-muted-foreground">This chart fills in once farmers start submitting reports.</p>
                </div>
            @endif
        </div>

        {{-- Damage by severity --}}
        <div class="min-w-0 rounded-xl border border-border bg-card p-6 shadow-sm">
            <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Damage by Severity</h2>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Severity assessed by technicians during field inspection.
            </p>

            @if ($severityTotal > 0)
                <div class="mt-4 flex flex-col items-center gap-6 sm:flex-row sm:justify-center">
                    <div class="relative h-52 w-52 shrink-0">
                        <canvas id="severityChart" class="max-w-full"
                                data-labels="{{ json_encode($severity->pluck('label')) }}"
                                data-values="{{ json_encode($severity->pluck('total')) }}"
                                data-colors="{{ json_encode($severity->pluck('color')) }}"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold text-foreground">{{ number_format($severityTotal) }}</span>
                            <span class="text-xs text-muted-foreground">inspections</span>
                        </div>
                    </div>

                    {{-- Legend carries the counts, so identity is never colour alone --}}
                    <ul class="space-y-3">
                        @foreach ($severity as $level)
                            <li class="flex items-center gap-3">
                                <span class="h-4 w-4 shrink-0 rounded-full" style="background-color: {{ $level['color'] }}"></span>
                                <span class="text-sm text-foreground">
                                    <span class="font-medium">{{ $level['label'] }}</span>
                                    <span class="text-muted-foreground">({{ $level['range'] }})</span>
                                </span>
                                <span class="ml-auto text-sm font-semibold tabular-nums text-foreground">
                                    {{ $level['total'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="mt-4 flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-border text-center">
                    <p class="text-sm font-medium text-muted-foreground">No inspections yet</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Severity appears once technicians submit their field assessments.
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        /* Same dark popup as the full Map page (mao/map/index). */
        .assoc-tip { background:#0f172a; border:0; border-radius:12px; color:#fff; padding:12px 15px;
            box-shadow:0 10px 24px -12px rgb(15 23 42 / .6); white-space:normal; width:250px; max-width:250px; }
        .assoc-tip::before { border-top-color:#0f172a; }
        .assoc-tip .tip-kicker { color:#a5b4fc; font-size:10px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; }
        .assoc-tip .tip-name { font-size:13.5px; font-weight:700; line-height:1.35; margin:3px 0 9px; white-space:normal; overflow-wrap:anywhere; }
        .assoc-tip .tip-row { display:flex; align-items:flex-start; gap:7px; font-size:12px; line-height:1.5; color:#cbd5e1; white-space:normal; }
        .assoc-tip .tip-dot { width:9px; height:9px; border-radius:9999px; display:inline-block; flex:none; margin-top:5px; }
        .assoc-tip .tip-sep { border-top:1px solid #1e293b; margin:8px 0 7px; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="{{ asset('js/tanza-mask.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('dashboardMap');
            if (! el || typeof L === 'undefined') return;

            const associations = JSON.parse(el.dataset.associations);

            const map = L.map(el, { scrollWheelZoom: true, maxBoundsViscosity: 1.0, zoomSnap: 0.25 }).setView(
                [parseFloat(el.dataset.centerLat), parseFloat(el.dataset.centerLng)],
                parseInt(el.dataset.zoom)
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            maskOutsideTanza(map, '{{ asset('geo/tanza-outline.json') }}');


            // Circle area grows with the report count, matching the full Map page.
            function radiusFor(reports) {
                return 11 + Math.sqrt(reports) * 7;
            }

            fetch(el.dataset.geojson)
                .then(response => response.json())
                .then(function (geojson) {
                    const boundaries = L.geoJSON(geojson, {
                        style: { color: '#94a3b8', weight: 1, fillColor: '#dcfce7', fillOpacity: 0.35 },
                    }).addTo(map);

                    boundaries.bringToBack();

                    /*
                     * Always open at the same, close-up view of Tanza.
                     *
                     * This used to call fitBounds() once and then add 1.25 to
                     * whatever zoom came out. fitBounds() works from the
                     * size the map box has AT THAT MOMENT, and on this page
                     * the box is still settling when the boundary file
                     * arrives (cards above it, the scrollbar, web fonts), so a
                     * too-small box produced a too-small zoom and the map
                     * opened as a tiny Tanza floating in the middle.
                     *
                     * Now the framing is a function of the real box size: it
                     * re-measures the box first, works the zoom out from that,
                     * and runs again whenever the box changes size, until the
                     * visitor touches the map. After that it leaves their
                     * zoom and position alone.
                     */
                    const bounds = boundaries.getBounds();
                    let touched = false;

                    function frame() {
                        if (touched) return;

                        map.invalidateSize({ animate: false });

                        // Open a little closer than "all of Tanza just fits";
                        // it can be dragged to the edges.
                        const zoom = map.getBoundsZoom(bounds, false, L.point(16, 16)) + 1.25;

                        map.setView(bounds.getCenter(), zoom, { animate: false });
                    }

                    ['pointerdown', 'wheel', 'keydown', 'touchstart'].forEach(function (type) {
                        el.addEventListener(type, function () { touched = true; }, { passive: true });
                    });

                    frame();
                    window.addEventListener('load', frame);
                    if (typeof ResizeObserver !== 'undefined') {
                        new ResizeObserver(frame).observe(el);
                    }

                    // Tanza only: the map cannot be dragged away from the municipality.
                    map.setMaxBounds(bounds.pad(0.15));

                    const centres = {};
                    geojson.features.forEach(function (feature) {
                        centres[feature.properties.psgc] = L.geoJSON(feature).getBounds().getCenter();
                    });

                    // Two associations can share a barangay, so nudge duplicates
                    // apart instead of stacking one on top of the other.
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

                        L.circleMarker([lat, lng], {
                            radius: radiusFor(row.reports),
                            color: '#ffffff',
                            weight: 2,
                            fillColor: row.color,
                            fillOpacity: 0.9,
                        }).bindTooltip(
                            '<span class="tip-kicker">Farmers\' Association</span>' +
                            '<div class="tip-name">' + row.name + '</div>' +
                            '<div class="tip-row"><span class="tip-dot" style="background:' + row.color + '"></span><span>' +
                                (row.reports === 0 ? 'No damage reports'
                                    : row.reports + (row.reports === 1 ? ' damage report' : ' damage reports')) + '</span></div>' +
                            (row.reports > 0 ?
                                '<div class="tip-sep"></div>' +
                                '<div class="tip-row"><span class="tip-dot" style="background:#facc15"></span><span>Awaiting Inspection</span><span style="margin-left:auto;color:#f1f5f9">' + row.awaiting + '</span></div>' +
                                '<div class="tip-row"><span class="tip-dot" style="background:#3b82f6"></span><span>Assigned for Inspection</span><span style="margin-left:auto;color:#f1f5f9">' + row.assigned + '</span></div>' +
                                '<div class="tip-row"><span class="tip-dot" style="background:#22c55e"></span><span>Verified</span><span style="margin-left:auto;color:#f1f5f9">' + row.verified_count + '</span></div>'
                                : ''),
                            { direction: 'top', offset: [0, -8], className: 'assoc-tip' }
                        ).addTo(map);
                    });
                })
                .catch(function () {
                    el.insertAdjacentHTML('beforeend',
                        '<div style="position:absolute;inset:0;z-index:500;display:flex;align-items:center;' +
                        'justify-content:center;background:#f8fafc;color:#64748b;font-size:13px;text-align:center;' +
                        'padding:24px">Map could not be loaded. Check that public/geo/tanza-barangays.json exists.</div>');
                });
        });
    </script>

    @if ($monthly->sum('submitted') + $monthly->sum('validated') > 0 || $severityTotal > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Chart === 'undefined') return;

                const summary = document.getElementById('reportsSummaryChart');
                if (summary) {
                    new Chart(summary, {
                        type: 'bar',
                        data: {
                            labels: JSON.parse(summary.dataset.labels),
                            datasets: [
                                {
                                    label: 'Submitted',
                                    data: JSON.parse(summary.dataset.submitted),
                                    backgroundColor: '#15803d',
                                    borderRadius: 4,
                                    borderSkipped: 'bottom',
                                    maxBarThickness: 26,
                                },
                                {
                                    label: 'Validated',
                                    data: JSON.parse(summary.dataset.validated),
                                    backgroundColor: '#0284c7',
                                    borderRadius: 4,
                                    borderSkipped: 'bottom',
                                    maxBarThickness: 26,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#0f172a',
                                    padding: 10,
                                    callbacks: {
                                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}`,
                                    },
                                },
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    border: { color: '#e2e8f0' },
                                    ticks: { color: '#64748b', font: { size: 11 } },
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#f1f5f9' },
                                    border: { display: false },
                                    ticks: { precision: 0, color: '#64748b', font: { size: 11 } },
                                },
                            },
                        },
                    });
                }

                const severity = document.getElementById('severityChart');
                if (severity) {
                    new Chart(severity, {
                        type: 'doughnut',
                        data: {
                            labels: JSON.parse(severity.dataset.labels),
                            datasets: [{
                                data: JSON.parse(severity.dataset.values),
                                backgroundColor: JSON.parse(severity.dataset.colors),
                                borderColor: '#ffffff',
                                borderWidth: 2,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '62%',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#0f172a',
                                    padding: 10,
                                    callbacks: {
                                        label: (ctx) => `${ctx.label}: ${ctx.parsed}`,
                                    },
                                },
                            },
                        },
                    });
                }
            });
        </script>
    @endif
@endpush
