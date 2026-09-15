@extends('layouts.app')

@section('title', 'Executive Dashboard')

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
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Total Affected Farmers"
                     :value="number_format($headline['affected_farmers'])"
                     :hint="'Farmers with a damage report, ' . strtolower($headline['period_label'])" />

        <x-ui.stat label="Active Reports"
                     :value="number_format($headline['active_reports'])"
                     :suffix="strtolower($headline['period_label'])"
                     hint="Not yet verified or closed" />

        <x-ui.stat label="Validated Reports"
                     :value="$headline['validated_rate'] . '%'"
                     :suffix="strtolower($headline['period_label'])"
                     hint="Share of reports a technician has verified" />

        <x-ui.stat label="Assistance Distributed"
                     :value="$headline['assistance_rate'] . '%'"
                     suffix="of affected farmers"
                     hint="Farmers who confirmed they received assistance" />
    </div>

    {{-- ===================== DAMAGE MAP ===================== --}}
    {{-- A preview of the full Maps and Visualization page (mao.map.index):
         the same association bubbles, shaded by report count, with no stat
         cards and no filters here - just the map and its legend. --}}
    <div class="mb-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-1 border-b border-border px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-foreground">Crop Damage by Association</h2>
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
        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h2 class="text-base font-semibold text-foreground">Reports Summary</h2>
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

                <div class="mt-3 h-64">
                    <canvas id="reportsSummaryChart"
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
        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h2 class="text-base font-semibold text-foreground">Damage by Severity</h2>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Severity assessed by technicians during field inspection.
            </p>

            @if ($severityTotal > 0)
                <div class="mt-4 flex flex-col items-center gap-6 sm:flex-row sm:justify-center">
                    <div class="relative h-52 w-52 shrink-0">
                        <canvas id="severityChart"
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
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('dashboardMap');
            if (! el || typeof L === 'undefined') return;

            const associations = JSON.parse(el.dataset.associations);

            const map = L.map(el, { scrollWheelZoom: false }).setView(
                [parseFloat(el.dataset.centerLat), parseFloat(el.dataset.centerLng)],
                parseInt(el.dataset.zoom)
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

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
                    map.fitBounds(boundaries.getBounds(), { padding: [16, 16] });

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
                            '<b>' + row.name + '</b><br>' +
                            row.reports + (row.reports === 1 ? ' report' : ' reports'),
                            { direction: 'top', offset: [0, -8] }
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
