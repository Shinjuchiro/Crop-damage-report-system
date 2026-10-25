{{--
    LEGEND for the Technician and Association maps (the MAO map keeps its own,
    which also has the association colour key).

        <x-map.legend :shading="$shading" :legends="$legends" what="reports in the barangay" />

    The heatmap block appears with the layer and says exactly what the layer
    is: every verified-location report counts the same, so a hot area means
    many reports close together, not severe damage.
--}}

@props(['shading', 'legends', 'what' => 'reports'])

<div class="border-t border-border px-6 py-4">
    @if ($shading === 'count')
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Damage reports filed</span>
            @foreach ($legends['count'] as $band)
                <span class="flex items-center gap-2 text-sm text-foreground">
                    <span class="h-4 w-4 rounded-full ring-2 ring-white" style="background-color: {{ $band['color'] }}"></span>
                    {{ $band['label'] }}
                    <span class="text-xs text-muted-foreground">({{ $band['total'] }})</span>
                </span>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-muted-foreground">
            Colour and size both show how many {{ $what }}. A bigger, darker circle means more reports, not necessarily worse damage.
        </p>
    @else
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Worst severity recorded</span>
            @foreach ($legends['severity'] as $level)
                <span class="flex items-center gap-2 text-sm text-foreground">
                    <span class="h-4 w-4 rounded-full ring-2 ring-white" style="background-color: {{ $level['color'] }}"></span>
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
            Colour shows the highest severity a technician recorded among the {{ $what }}. Circle size still shows how many reports there were.
        </p>
    @endif
</div>

<div x-show="pins" x-cloak class="border-t border-border px-6 py-4">
    <span class="flex items-center gap-2 text-sm text-foreground">
        <span class="h-4 w-4 rounded-full ring-2 ring-white" style="background:#e11d48" aria-hidden="true"></span>
        Technician-Verified Location (pin colour shows the assessed severity)
    </span>
</div>

<div x-show="heat" x-cloak class="border-t border-border px-6 py-4">
    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Heatmap: report concentration</span>
    <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-3">
        <span class="flex items-center gap-2 text-sm text-foreground">
            <span class="h-4 w-16 rounded" style="background: linear-gradient(to right, #2563eb, #22c55e, #eab308, #ef4444)"></span>
            Fewer reports to more reports
        </span>
    </div>
    <p class="mt-3 text-xs text-muted-foreground">
        The heatmap shows <span class="font-medium text-foreground">how many damage reports with a technician-verified location sit near each other</span>,
        and nothing else. Every report counts the same, whatever it was assessed at, so a hot area means many reports in one place, not severe damage.
        Reports a technician has not verified yet have no location and are not on the heatmap.
    </p>
</div>
