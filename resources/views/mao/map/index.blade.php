@extends('layouts.app')

@section('title', 'Maps and Visualization')
@section('heading', 'Maps and Visualization')
@section('subheading', "Crop damage by Farmers' Association across Tanza, Cavite.")

@section('content')
{{-- The card this page used to draw around itself is now the page
     frame in layouts/app.blade.php, on every screen rather than only
     this one. All that is left here is the spacing between blocks. --}}
<div x-data="{ filtersOpen: false }" class="space-y-5">

    {{-- Coverage --}}
    <div class="stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Damage Reports" :value="number_format($coverage['reports'])"
                     hint="Matching the current filters"
                     icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01" />
        <x-ui.stat label="Associations Affected" :value="number_format($coverage['associations'])"
                     :suffix="'of ' . $coverage['total_assoc']"
                     icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM4 12.5a2 2 0 100-4 2 2 0 000 4zM20 12.5a2 2 0 100-4 2 2 0 000 4z" />
        <x-ui.stat label="Total Damaged Area" :value="number_format($coverage['area'], 2)" suffix="ha"
                   icon="M4 8V5a1 1 0 011-1h3M20 8V5a1 1 0 00-1-1h-3M4 16v3a1 1 0 001 1h3M20 16v3a1 1 0 01-1 1h-3" />
        <x-ui.stat label="Verified Pins" :value="number_format($coverage['pinned'])"
                     hint="Locations a technician confirmed on site"
                     icon="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11zM12 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
    </div>

    @if ($coverage['unplaced'] > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ $coverage['unplaced'] }}
            {{ \Illuminate\Support\Str::plural('association', $coverage['unplaced']) }}
            {{ $coverage['unplaced'] === 1 ? 'has' : 'have' }} no area coverage recorded, so
            {{ $coverage['unplaced'] === 1 ? 'it is' : 'they are' }} not drawn on the map
            {{ $coverage['unplaced'] === 1 ? 'unless it has' : 'unless they have' }} damage reports.
            Coverage comes from the office's area coverage sheet. If
            {{ $coverage['unplaced'] === 1 ? 'the association does' : 'an association does' }}
            not belong in the system at all, archive it under
            <a href="{{ route('mao.associations.index') }}" class="font-medium underline">Manage Associations</a>.
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

    {{-- Map

         x-data moved up to the whole card (Oct 2026) so the heatmap legend
         further down can read the same `heat` flag as the checkbox that
         turns the layer on. It used to live on the checkbox row alone,
         which is why there was nowhere to put a legend that appears with
         the layer. --}}
    <div x-data="{ pins: false, heat: false }"
         class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">

        <div class="flex flex-col gap-3 border-b border-border px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Crop Damage by Association</h2>
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

                <div class="inline-flex items-center gap-4 rounded-lg border border-input px-4 py-2 text-xs text-muted-foreground">
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

        {{-- Which association shades which barangays. This legend is the key
             to the fills; the one under it is the key to the bubbles, which
             are a different question (how much damage, not whose area). --}}
        <div class="border-t border-border px-6 py-4">
            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Area coverage per association
            </span>

            {{-- Each chip is both the key to a colour and the switch for it.
                 Eight territories over the same ground is a lot to read at
                 once, and the ones that overlap most are the ones you most
                 often want to see on their own. --}}
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($associationStats as $row)
                    <button type="button"
                            data-assoc="{{ $row['id'] }}"
                            aria-pressed="true"
                            title="{{ $row['name'] }} &mdash; {{ $row['coverage_names'] }}"
                            class="assoc-chip" style="--chip: {{ $row['color'] }}">
                        <span class="assoc-chip-dot"></span>
                        {{ $row['short'] }}
                    </button>
                @endforeach

                <span class="mx-1 w-px self-stretch bg-border"></span>

                <button type="button" data-assoc="all" class="assoc-chip assoc-chip-plain">All</button>
                <button type="button" data-assoc="none" class="assoc-chip assoc-chip-plain">None</button>
            </div>

            <p class="mt-3 text-xs text-muted-foreground">
                Each association is one area, merged from the barangays it covers on the office's area
                coverage sheet. They overlap, so the same ground can sit inside several outlines:
                Tres Cruces is covered by five associations and Calibuyo by four. Switch one off to
                read the rest.
            </p>
        </div>

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

        {{-- HEATMAP LEGEND (added Oct 2026)

             Revision section 12.3: "If a heatmap is used, clearly define
             what it represents. A report-count heatmap must represent the
             concentration or number of relevant reports. It must not imply
             that report density equals damage severity."

             The heatmap had no legend and no definition anywhere on the
             page: a checkbox labelled "Heatmap" and a wash of colour over
             the municipality, which a reader would reasonably take for
             severity, since red means severe everywhere else in this
             system. Both bubble legends above were careful to say the
             opposite ("not necessarily worse damage") and this layer was
             left to be guessed at. Proposal section 59 also asks the legend
             to carry "Higher Report Concentration".

             The claim below is exactly what the code does: L.heatLayer is
             handed [lat, lng, 1] per report, so every report counts the
             same and severity is not a factor. If the weight ever stops
             being 1, this wording has to change with it.

             It appears with the layer rather than always, because a legend
             for something not on the map is clutter. --}}
        <div x-show="heat" x-cloak class="border-t border-border px-6 py-4">
            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Heatmap: report concentration
            </span>

            <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-3">
                <span class="flex items-center gap-2 text-sm text-foreground">
                    <span class="h-4 w-16 rounded"
                          style="background: linear-gradient(to right, #2563eb, #22c55e, #eab308, #ef4444)"></span>
                    Fewer reports to more reports
                </span>
            </div>

            <p class="mt-3 text-xs text-muted-foreground">
                The heatmap shows <span class="font-medium text-foreground">how many damage reports were
                filed near each other</span>, and nothing else. Every report counts the same, whatever a
                technician assessed it at, so a hot area means many reports in one place, not severe damage.
                One total loss on its own farm stays cool; ten slight reports in one barangay run hot. For
                severity, use "By worst severity" above, or the verified pins.
            </p>
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

        /* The association chips. The colour comes in as --chip, so one rule
           serves all eight rather than eight inline styles. Pressed is the
           normal state: everything starts visible. */
        .assoc-chip {
            display: inline-flex; align-items: center; gap: 7px;
            border: 1px solid var(--chip, #cbd5e1);
            border-radius: 9999px; padding: 5px 12px;
            font-size: 13px; font-weight: 600; line-height: 1.2;
            color: #334155; background: #fff;
            cursor: pointer; transition: opacity .15s, background-color .15s;
        }
        .assoc-chip[aria-pressed="true"] {
            background: color-mix(in srgb, var(--chip) 12%, #fff);
        }
        /* Switched off: drained of colour rather than hidden, so you can
           still see what you turned off and put it back. */
        .assoc-chip[aria-pressed="false"] {
            border-color: #cbd5e1; color: #94a3b8; background: #fff;
        }
        .assoc-chip[aria-pressed="false"] .assoc-chip-dot { background: #cbd5e1; }

        .assoc-chip-dot {
            width: 10px; height: 10px; border-radius: 9999px;
            background: var(--chip, #cbd5e1); flex: none;
        }
        .assoc-chip-plain { border-color: #cbd5e1; font-weight: 500; }

        .brgy-label { background: none; border: 0; box-shadow: none; white-space: nowrap; }

        /* Barangay names and association acronyms, readable on the basemap */
        .brgy-label  { color: #475569; text-shadow: 0 1px 2px #fff, 0 0 3px #fff; }
        .assoc-code  { color: #0f172a; text-shadow: 0 1px 2px #fff, 0 0 3px #fff; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="{{ asset('js/tanza-mask.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('damageMap');
            if (! el || typeof L === 'undefined') return;

            const associations = JSON.parse(el.dataset.associations);
            const points       = JSON.parse(el.dataset.points);
            const shading      = el.dataset.shading;

            /* maxBoundsViscosity has to be set here rather than later: it is
               read while a drag is in flight, and 1.0 makes the edge of the
               allowed area solid instead of rubber-banding back. The bounds
               themselves are set once the barangay outlines have loaded and
               the real extent of Tanza is known. */
            const map = L.map(el, {
                maxBoundsViscosity: 1.0,
                /* Leaflet normally snaps to whole zoom levels. Tanza is about
                   10km across, which falls between two of them, so a whole
                   level either overflowed the card or left the municipality
                   floating in a third of it. Quarter steps let the fit
                   actually fill the space. */
                zoomSnap: 0.25,
                zoomDelta: 0.5,
            }).setView(
                [parseFloat(el.dataset.centerLat), parseFloat(el.dataset.centerLng)],
                parseInt(el.dataset.zoom)
            );

            /* ---------------- Basemap ----------------
               One tile source, plain OpenStreetMap, no account and no API key. */
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors | ' +
                             'Boundaries: PSA via faeldon/philippines-json-maps',
            }).addTo(map);
            maskOutsideTanza(map, '{{ asset('geo/tanza-outline.json') }}');


            let boundaries = null;

            const boundaryStyle = { color: '#94a3b8', weight: 1, fillColor: '#dcfce7', fillOpacity: 0.45 };

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
                    (row.reports > 0 ?
                        '<div class="tip-sep"></div>' +
                        '<div class="tip-row"><span class="tip-dot" style="background:#facc15"></span><span>Awaiting Inspection</span>' +
                            '<span style="margin-left:auto;color:#f1f5f9">' + row.awaiting + '</span></div>' +
                        '<div class="tip-row"><span class="tip-dot" style="background:#3b82f6"></span><span>Assigned for Inspection</span>' +
                            '<span style="margin-left:auto;color:#f1f5f9">' + row.assigned + '</span></div>' +
                        '<div class="tip-row"><span class="tip-dot" style="background:#22c55e"></span><span>Verified</span>' +
                            '<span style="margin-left:auto;color:#f1f5f9">' + row.verified_count + '</span></div>'
                        : '') +
                    '<div class="tip-sep"></div>' +
                    '<div class="tip-row"><span>Office</span>' +
                        '<span style="margin-left:auto;color:#f1f5f9">' + row.location + '</span></div>' +
                    '<div class="tip-row" style="align-items:flex-start"><span>Covers</span>' +
                        '<span style="margin-left:auto;max-width:14rem;text-align:right;color:#f1f5f9">' +
                        row.coverage_names + '</span></div>' +
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

            /*
                MERGE A GROUP OF BARANGAYS INTO ONE OUTLINE

                An edge that two of the group's barangays share is a border
                INSIDE the association's area, so it is dropped; an edge only
                one of them has is on the outside, so it is kept. Stitching
                the kept edges end to end gives the outline, or several
                outlines when the barangays are not all neighbours.

                This needs no geometry library, only that neighbouring
                barangays share the same vertices along their common border,
                which the PSGC boundary file does: the four barangays of P2BS
                share 35 edges exactly, and CALACATC's four share 39.

                Points are compared as fixed strings rather than as floats,
                because two coordinates that are equal in the file can differ
                in the last bit after being parsed.
            */
            function dissolve(rings) {
                const key     = p => p[0].toFixed(7) + ',' + p[1].toFixed(7);
                const edgeKey = (a, b) => {
                    const ka = key(a), kb = key(b);
                    return ka < kb ? ka + '|' + kb : kb + '|' + ka;
                };

                const count = new Map();   // edge -> how many barangays have it
                const ends  = new Map();   // edge -> its two points

                rings.forEach(function (ring) {
                    for (let i = 0; i < ring.length - 1; i++) {
                        const a = ring[i], b = ring[i + 1];
                        const k = edgeKey(a, b);
                        count.set(k, (count.get(k) || 0) + 1);
                        if (! ends.has(k)) ends.set(k, [a, b]);
                    }
                });

                const adj      = new Map();
                const boundary = [];

                count.forEach(function (n, k) {
                    if (n !== 1) return;          // an inside border, drop it
                    const pair = ends.get(k);
                    boundary.push(k);
                    [[pair[0], pair[1]], [pair[1], pair[0]]].forEach(function (pq) {
                        const at = key(pq[0]);
                        if (! adj.has(at)) adj.set(at, []);
                        adj.get(at).push(pq[1]);
                    });
                });

                const used = new Set();
                const out  = [];

                boundary.forEach(function (start) {
                    if (used.has(start)) return;

                    const pair = ends.get(start);
                    used.add(start);
                    const ring = [pair[0], pair[1]];

                    // Walk from edge to edge until the ring closes, or until
                    // there is nowhere left to go on a boundary file with a
                    // gap in it. The guard is the edge count, so a malformed
                    // file cannot spin here forever.
                    for (let step = 0; step < boundary.length + 2; step++) {
                        const cur  = ring[ring.length - 1];
                        const prev = ring[ring.length - 2];
                        const options = adj.get(key(cur)) || [];

                        let next = null;
                        for (let i = 0; i < options.length; i++) {
                            const cand = options[i];
                            if (key(cand) === key(prev)) continue;
                            if (used.has(edgeKey(cur, cand))) continue;
                            next = cand;
                            break;
                        }

                        if (! next) break;
                        used.add(edgeKey(cur, next));
                        ring.push(next);
                        if (key(next) === key(ring[0])) break;
                    }

                    if (ring.length > 3) out.push(ring);
                });

                return out;
            }

            const coverage = L.layerGroup().addTo(map);
            const bubbles  = L.layerGroup().addTo(map);

            /* association id -> everything that belongs to it, so a chip can
               take the whole association off the map in one go: its area, its
               bubble and name, and the verified pins its members' reports
               put there. 'hidden' is what the heatmap reads. */
            const shapes      = {};
            const markers     = {};
            const pinsByAssoc = {};
            const hidden      = {};

            fetch(el.dataset.geojson)
                .then(response => response.json())
                .then(function (geojson) {

                    /* ---------- Barangay outlines, context only ---------- */
                    boundaries = L.geoJSON(geojson, {
                        style: boundaryStyle,
                        onEachFeature: function (feature, layer) {
                            layer.bindTooltip(feature.properties.name, {
                                sticky: true,
                                direction: 'top',
                                className: 'brgy-label',
                            });
                        },
                    }).addTo(map);

                    boundaries.bringToBack();

                    /* ---------- Everywhere that is not Tanza ----------

                       One polygon covering the world with Tanza cut out of
                       it, painted the colour of the page. The basemap keeps
                       its roads and landmarks inside the municipality, where
                       they help a technician place a farm, and Naic, Trece
                       Martires and the rest of Cavite simply are not drawn.

                       The outline of Tanza is the same merge used for the
                       associations, run over all 41 barangays at once: the
                       borders between them cancel out and what is left is
                       the municipal boundary.

                       interactive: false, or this sheet would swallow every
                       click and tooltip meant for the map underneath it. */
                    const outerRing = [[-85, -180], [-85, 180], [85, 180], [85, -180]];

                    const tanza = dissolve(geojson.features.map(function (feature) {
                        return feature.geometry.coordinates[0];
                    }));

                    if (tanza.length) {
                        L.polygon(
                            [outerRing].concat(tanza.map(function (ring) {
                                return ring.map(function (p) { return [p[1], p[0]]; });
                            })),
                            {
                                stroke: false,
                                fillColor: '#eef4fb',
                                fillOpacity: 1,
                                interactive: false,
                            }
                        ).addTo(map).bringToBack();
                    }

                    /* ---------- The view, fixed on the municipality ----------

                       Fitted to Tanza, then penned there: you can zoom in to
                       read a barangay, but not out to the whole of Luzon and
                       not sideways into the blank. Before this the map opened
                       at a guessed centre and zoom, and any drag left you
                       looking at empty space.

                       minZoom is whatever the fit came out at, so a phone and
                       a desktop each get their own floor rather than a number
                       picked for one of them. */
                    const extent = boundaries.getBounds().pad(0.05);
                    const inset  = L.point(20, 20);

                    /* The floor is worked out from the bounds rather than
                       read back from the map: fitBounds may still be easing
                       into place when the next line runs, and getZoom() then
                       answers with where the map was a moment ago, which
                       pinned the floor one level too low. */
                    const fitZoom = map.getBoundsZoom(extent, false, inset);

                    map.fitBounds(extent, { padding: [20, 20] });
                    map.setMaxBounds(extent);
                    map.setMinZoom(fitZoom);
                    map.setMaxZoom(17);

                    /* ---------- Every barangay, by its PSGC code ---------- */
                    const features = {};
                    const centres  = {};
                    geojson.features.forEach(function (feature) {
                        features[feature.properties.psgc] = feature;
                        centres[feature.properties.psgc] = L.geoJSON(feature).getBounds().getCenter();
                    });

                    /* ---------- Coverage, one shape per association ----------

                       Each association is ONE shaded area, not a set of
                       shaded barangays. The barangays it covers are merged
                       into a single outline first, so the borders between
                       them disappear and what is left is the association's
                       own territory. PHAMBBSS covers fifteen barangays and
                       reads as one region; Tanza Farmers Association's seven
                       are not all neighbours, so it comes out as three
                       separate pieces, which is the truth about its area.

                       Drawn all at once and translucent on purpose: the
                       office's sheet has five associations working in Tres
                       Cruces and four in Calibuyo, so that ground carries
                       several colours at once, and the overlap is the fact
                       being shown rather than a problem to be hidden.

                       0.28 opacity, not more: four layers over Calibuyo
                       already reach about 0.73 together, and past that the
                       barangay outlines underneath stop reading. */
                    associations.forEach(function (row) {
                        const rings = [];

                        (row.coverage || []).forEach(function (psgc) {
                            const feature = features[psgc];
                            if (feature && feature.geometry) {
                                // A barangay is a simple polygon here, but
                                // read it as a multipolygon so a future
                                // boundary file with islands still works.
                                const geom = feature.geometry;
                                if (geom.type === 'Polygon') {
                                    rings.push(geom.coordinates[0]);
                                } else if (geom.type === 'MultiPolygon') {
                                    geom.coordinates.forEach(function (part) {
                                        rings.push(part[0]);
                                    });
                                }
                            }
                        });

                        if (! rings.length) return;

                        const merged = dissolve(rings);
                        if (! merged.length) return;

                        // [[ring]] per shape, not [ring, ring]: Leaflet reads
                        // a second ring inside one polygon as a HOLE, so the
                        // pieces have to be separate shapes or Tanza Farmers
                        // Association would punch holes in itself.
                        const shape = L.polygon(
                            merged.map(function (ring) {
                                return [ring.map(function (p) { return [p[1], p[0]]; })];
                            }),
                            {
                                /* Outline-led, not fill-led. Eight
                                   translucent fills stacked on the same
                                   ground turn to mud: Tres Cruces carries
                                   five of them and came out a flat brown
                                   with nothing to tell you whose it was.
                                   A strong edge and a faint wash reads as
                                   a territory, and overlapping territories
                                   stay legible because edges cross rather
                                   than blend. */
                                color: row.color,
                                weight: 2.5,
                                opacity: 0.95,
                                fillColor: row.color,
                                fillOpacity: 0.12,
                            }
                        )
                        .bindTooltip(tooltipFor(row), {
                            sticky: true,
                            direction: 'top',
                            className: 'assoc-tip',
                        })
                        .on('click', function () {
                            if (row.reports > 0) window.location = row.url;
                        })
                        .addTo(coverage);

                        row._bounds = shape.getBounds();
                        shapes[row.id] = shape;
                    });

                    /* ---------- One bubble per association ----------

                       At the middle of its coverage, not at its office: Tanza
                       Farmers Association's office is in Daang Amaya, which is
                       not one of the seven barangays it covers, so placing the
                       bubble there would put the figures outside the area they
                       describe. An association with no coverage recorded falls
                       back to its office barangay so it is still on the map. */
                    /* Two associations covering the same ground land on the
                       same point, and their bubbles and names then print on
                       top of each other - "Calibuyo Farmers Assoc" and a
                       second association both sitting on Calibuyo came out as
                       one unreadable smear. Each one after the first is
                       stepped around a small circle so every name can be
                       read. Rounded to 4 decimals, about 11 metres, which is
                       far finer than two bubbles ever need to be apart to
                       count as the same place. */
                    const taken = {};

                    associations.forEach(function (row) {
                        let centre = null;

                        // The merged shape's own middle, set when it was
                        // drawn just above.
                        if (row._bounds && row._bounds.isValid()) {
                            centre = row._bounds.getCenter();
                        }

                        if (! centre) centre = centres[row.psgc];
                        if (! centre) return;

                        const spot = centre.lat.toFixed(4) + ',' + centre.lng.toFixed(4);
                        const nth  = taken[spot] = (taken[spot] || 0);
                        taken[spot]++;

                        let lat = centre.lat, lng = centre.lng;

                        if (nth > 0) {
                            // Six to a ring before the ring widens, so a spot
                            // shared by many still spreads rather than piling
                            // up at one angle.
                            const angle = (nth - 1) * (Math.PI / 3);
                            const step  = 0.009 * (1 + Math.floor((nth - 1) / 6));
                            lat += Math.cos(angle) * step;
                            lng += Math.sin(angle) * step;
                        }

                        const fill = shading === 'severity' ? row.severity_color : row.count_color;

                        const bubble = L.circleMarker([lat, lng], {
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
                        });

                        const label = L.marker([lat, lng], {
                            icon: L.divIcon({
                                className: 'brgy-label',
                                html: '<span class="assoc-code" style="font-weight:700;font-size:11px">' +
                                      row.short + '</span>',
                                iconSize: [90, 14],
                                iconAnchor: [45, -8],
                            }),
                            interactive: false,
                        });

                        /* The bubble and its name travel together, in a group
                           of their own that is then put inside `bubbles`.
                           Adding them to `bubbles` directly as well was a bug:
                           hiding the pair removed the wrapper from the map,
                           but `bubbles` still held the two layers and kept
                           drawing them, so a switched-off association left its
                           circle and its name sitting there. */
                        markers[row.id] = L.layerGroup([bubble, label]).addTo(bubbles);
                    });

                    /* ---------- The chips under the map ----------

                       Eight associations over the same ground is a lot to
                       read at once, so each one can be taken off. The chip
                       carries the association's own colour, which is the
                       only key to the shape it controls. */
                    const chips = document.querySelectorAll('[data-assoc]');

                    /* Each thing goes in and out of the group that owns it,
                       never off the map directly. That keeps the chips and
                       the Pins and Heatmap checkboxes from fighting over the
                       same layers: the checkbox decides whether a group is on
                       the map at all, the chip decides what is in it. */
                    function setVisible(id, on) {
                        hidden[id] = ! on;

                        if (shapes[id]) {
                            on ? coverage.addLayer(shapes[id]) : coverage.removeLayer(shapes[id]);
                        }

                        if (markers[id]) {
                            on ? bubbles.addLayer(markers[id]) : bubbles.removeLayer(markers[id]);
                        }

                        (pinsByAssoc[id] || []).forEach(function (pin) {
                            on ? pins.addLayer(pin) : pins.removeLayer(pin);
                        });

                        if (window.redrawHeat) window.redrawHeat();
                    }

                    chips.forEach(function (chip) {
                        chip.addEventListener('click', function () {
                            const id = chip.dataset.assoc;

                            if (id === 'all' || id === 'none') {
                                const on = id === 'all';
                                chips.forEach(function (other) {
                                    if (other.dataset.assoc === 'all' || other.dataset.assoc === 'none') return;
                                    other.setAttribute('aria-pressed', on ? 'true' : 'false');
                                    setVisible(other.dataset.assoc, on);
                                });
                                return;
                            }

                            const on = chip.getAttribute('aria-pressed') !== 'true';
                            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
                            setVisible(id, on);
                        });
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
                const pin = L.circleMarker([point.lat, point.lng], {
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

                // Filed under its association, so switching that association
                // off takes its reports with it.
                const key = point.association_id;
                if (key) {
                    if (! pinsByAssoc[key]) pinsByAssoc[key] = [];
                    pinsByAssoc[key].push(pin);
                }
            });

            const heat = (typeof L.heatLayer === 'function' && points.length > 0)
                ? L.heatLayer(points.map(p => [p.lat, p.lng, 1]), { radius: 28, blur: 20, maxZoom: 16 })
                : null;

            /* The heatmap is one layer built from a list of points rather
               than a marker each, so hiding an association means handing it
               a shorter list instead of removing anything. */
            window.redrawHeat = function () {
                if (! heat) return;
                heat.setLatLngs(
                    points
                        .filter(p => hidden[p.association_id] !== true)
                        .map(p => [p.lat, p.lng, 1])
                );
            };

            window.toggleMapLayer = function (which, on) {
                const layer = which === 'heat' ? heat : pins;
                if (! layer) return;
                on ? layer.addTo(map) : map.removeLayer(layer);
            };
        });
    </script>
@endpush
