{{--
    THE MAP FILTERS, shared by the MAO, Technician and Association maps

        <x-map.filters role="mao" :action="route('mao.map.index')" :filters="$filters"
                       :associations="$associations" :keep="['shading' => $shading]" />

    Which controls appear depends on the role:

        MAO          Date Range, Farmers' Association, Disaster, Severity
        Technician   Date Range, Disaster, Severity, Validation Status
        Association  Date Range, Disaster, Severity   (no association picker:
                     the controller already limits it to its own members)

    Start Date and End Date show only when "Custom Date Range" is chosen, and
    are disabled otherwise so a stale date can never be submitted with
    another range. The filters are what the controller reads; this file only
    draws them. Display options (By Report Count, By Worst Severity, Verified
    Pins, Heatmap) are separate: see x-map.display-options.
--}}

@props(['role', 'action', 'filters', 'associations' => [], 'keep' => []])

@php
    $ranges     = \App\Support\MapFilters::RANGES;
    $disasters  = \App\Support\MapFilters::DISASTERS;
    $severities = \App\Models\Validation::SEVERITY_SCALE;
    $applied    = \App\Support\MapFilters::active($filters);
    $control    = 'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring';
@endphp

<div x-data="{ open: {{ $applied ? 'true' : 'false' }}, range: @js($filters['range']) }"
     class="rounded-xl border border-border bg-card shadow-sm">
    <button type="button" @click="open = ! open"
            class="flex w-full items-center justify-between px-6 py-4 text-left">
        <span class="text-sm font-semibold text-foreground">Filters</span>
        <span class="flex items-center gap-2 text-xs text-muted-foreground">
            {{ $applied ? 'Filters applied' : 'Showing everything' }}
            <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
            </svg>
        </span>
    </button>

    <form method="GET" action="{{ $action }}" x-show="open" x-cloak x-transition
          class="border-t border-border px-6 py-5">
        @foreach ($keep as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        @if (! empty($filters['notice']))
            <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $filters['notice'] }}</p>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-1.5">
                <label for="map-range" class="block text-xs font-medium text-muted-foreground">Date Range</label>
                <select id="map-range" name="range" x-model="range" class="{{ $control }}">
                    @foreach ($ranges as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($role === 'mao')
                <div class="space-y-1.5">
                    <label for="map-association" class="block text-xs font-medium text-muted-foreground">Farmers' Association</label>
                    <select id="map-association" name="association_id" class="{{ $control }}">
                        <option value="">All Associations</option>
                        @foreach ($associations as $association)
                            <option value="{{ $association->id }}" @selected((int) ($filters['association_id'] ?? 0) === $association->id)>
                                {{ $association->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="space-y-1.5">
                <label for="map-disaster" class="block text-xs font-medium text-muted-foreground">Disaster</label>
                <select id="map-disaster" name="disaster" class="{{ $control }}">
                    <option value="">All Disasters</option>
                    @foreach ($disasters as $key => $label)
                        <option value="{{ $key }}" @selected($filters['disaster'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="map-severity" class="block text-xs font-medium text-muted-foreground">Severity</label>
                <select id="map-severity" name="severity" class="{{ $control }}">
                    <option value="">All Severities</option>
                    @foreach ($severities as $key => $level)
                        <option value="{{ $key }}" @selected($filters['severity'] === $key)>{{ $level['label'] }} ({{ $level['range'] }})</option>
                    @endforeach
                </select>
            </div>

            @if ($role === 'technician')
                <div class="space-y-1.5">
                    <label for="map-validation" class="block text-xs font-medium text-muted-foreground">Validation Status</label>
                    <select id="map-validation" name="validation" class="{{ $control }}">
                        <option value="">All Assigned Reports</option>
                        @foreach (\App\Support\MapFilters::VALIDATION as $key => $option)
                            <option value="{{ $key }}" @selected($filters['validation'] === $key)>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div x-show="range === 'custom'" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-1.5">
                <label for="map-start" class="block text-xs font-medium text-muted-foreground">Start Date</label>
                <input id="map-start" type="date" name="start" value="{{ $filters['start'] }}"
                       :disabled="range !== 'custom'" class="{{ $control }}">
            </div>
            <div class="space-y-1.5">
                <label for="map-end" class="block text-xs font-medium text-muted-foreground">End Date</label>
                <input id="map-end" type="date" name="end" value="{{ $filters['end'] }}"
                       :disabled="range !== 'custom'" class="{{ $control }}">
            </div>
        </div>

        <div class="mt-4 flex gap-3">
            <button class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                Apply filters
            </button>
            <a href="{{ $action }}{{ ! empty($keep) ? '?' . http_build_query($keep) : '' }}"
               class="rounded-lg border border-input px-6 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
                Reset
            </a>
        </div>
    </form>
</div>
