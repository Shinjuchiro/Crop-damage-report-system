@extends('layouts.app')

@section('title', 'Technical Dashboard')
@section('heading', 'Welcome back, ' . auth()->user()->display_name . '!')
@section('subheading', 'Field Inspection and Validation Management')

@section('content')

{{--
    Technician dashboard, laid out to match the approved mockup.

    Reading order down the page:
      1. the three tiles      how much work do I have
      2. assigned inspections what exactly is on my desk
      3. the bottom row       what is next, how am I doing, one tap to start

    Everything here comes out of DashboardController. There is not a single
    hardcoded number on this page, which matters because the panel WILL ask.
--}}

<div x-data="{ showFilters: {{ ($barangay || $filter) ? 'true' : 'false' }} }" class="space-y-4">

    {{-- =================================================================
         PERIOD + FILTER (top right in the mockup)
         Both are real GET filters. The period reloads the page rather than
         hiding rows in the browser, so the tiles and the donut change with
         it and cannot disagree with the table.
    ================================================================== --}}
    <div class="flex flex-wrap items-center justify-end gap-2">

        <form method="GET" action="{{ route('technician.dashboard') }}">
            @if ($filter) <input type="hidden" name="status" value="{{ $filter }}"> @endif
            @if ($barangay) <input type="hidden" name="barangay" value="{{ $barangay }}"> @endif

            <select name="period" onchange="this.form.submit()"
                    class="h-9 rounded-md border border-input bg-card px-3 text-sm font-medium shadow-sm">
                @foreach ($periods as $key => $label)
                    <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <x-ui.button size="sm" @click="showFilters = ! showFilters"
                     :variant="($barangay || $filter) ? 'default' : 'outline'">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/>
            </svg>
            Filter
            @if ($barangay || $filter)
                <span class="ml-1 rounded-full bg-primary-foreground/25 px-1.5 text-[11px] leading-4">
                    {{ collect([$barangay, $filter])->filter()->count() }}
                </span>
            @endif
        </x-ui.button>
    </div>

    {{-- The filter panel. Hidden until the Filter button is pressed so it
         does not eat a third of a phone screen for people who never use it. --}}
    <x-ui.card x-show="showFilters" x-cloak x-transition>
        <form method="GET" action="{{ route('technician.dashboard') }}"
              class="flex flex-wrap items-end justify-end gap-3">

            <input type="hidden" name="period" value="{{ $period }}">

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="filter-status">Status</label>
                <select id="filter-status" name="status"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm sm:w-40">
                    <option value="">All</option>
                    <option value="to_inspect" @selected($filter === 'to_inspect')>To Inspect</option>
                    <option value="in_progress" @selected($filter === 'in_progress')>In Progress</option>
                    <option value="done" @selected($filter === 'done')>Completed</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="filter-barangay">Barangay</label>
                <select id="filter-barangay" name="barangay"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm sm:w-44">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $option)
                        <option value="{{ $option->id }}" @selected((string) $barangay === (string) $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-ui.button type="submit">Apply</x-ui.button>
        </form>
    </x-ui.card>

    {{-- =================================================================
         THE TILES
    ================================================================== --}}
    <div class="stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-3">

        <x-ui.stat label="Pending Validation"
                   :value="number_format($summary['pending_validation'])"
                   tone="warning"
                   hint="Waiting for your inspection"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2"
                   :href="route('technician.validation.index')" />

        <x-ui.stat label="Assigned Today"
                   :value="number_format($summary['assigned_today'])"
                   hint="Bago ngayong araw"
                   icon="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4"
                   :href="route('technician.reports.index', ['status' => 'assigned'])" />

        <x-ui.stat label="Validation Reports"
                   :value="number_format($summary['validation_reports'])"
                   tone="primary"
                   :hint="'Submitted, ' . strtolower($periods[$period])"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                   :href="route('technician.history.index')" />
    </div>

    {{-- =================================================================
         ASSIGNED INSPECTIONS
         Six rows only. The full list with pagination lives on the
         Assigned Reports page, which is what "View All" goes to.
    ================================================================== --}}
    <x-ui.card title="Assigned Inspections" :padded="false"
               description="Damage reports the office has handed to you.">

        <x-slot:actions>
            <form method="GET" action="{{ route('technician.dashboard') }}">
                <input type="hidden" name="period" value="{{ $period }}">
                @if ($barangay) <input type="hidden" name="barangay" value="{{ $barangay }}"> @endif

                <select name="status" onchange="this.form.submit()"
                        class="h-8 rounded-md border border-input bg-card px-2 text-xs font-medium shadow-sm">
                    <option value="" @selected(! $filter)>ALL</option>
                    <option value="to_inspect" @selected($filter === 'to_inspect')>To Inspect</option>
                    <option value="in_progress" @selected($filter === 'in_progress')>In Progress</option>
                    <option value="done" @selected($filter === 'done')>Completed</option>
                </select>
            </form>
        </x-slot:actions>

        @if ($reports->isEmpty())
            <x-ui.empty title="Nothing assigned to you yet"
                        message="The Municipal Agriculture Office assigns damage reports to technicians. When one is assigned to you it appears here, and you inspect it from this page.">
                <span class="text-xs text-muted-foreground">Wala pang nakatalagang ulat sa inyo.</span>
            </x-ui.empty>
        @else
            {{-- ---------- PHONE: one card per report ----------

                 This is a technician's landing page, and the spec makes
                 technician screens mobile first, but an eight column table
                 puts the Action button around 670px in, so the one control
                 that matters could only be reached by scrolling the card
                 sideways.

                 The card itself lives in technician/partials/report-card,
                 shared with technician/reports/index.blade.php so the two
                 lists cannot drift apart again.
            --}}
            <ul class="space-y-3 p-4 sm:hidden">
                @foreach ($reports as $report)
                    <li>@include('technician.partials.report-card', ['report' => $report])</li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP: the table ---------- --}}
            <div class="hidden sm:block">
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <th>Report Id</th>
                        <th>Farmer</th>
                        <th>Barangay</th>
                        <th>Crop</th>
                        <th>Severity</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </x-slot:head>

                @foreach ($reports as $report)
                    @php
                        /* The Severity column shows YOUR assessed figure once the
                           inspection is in, and the farmer's estimate before that.
                           They are labelled differently so nobody mistakes one for
                           the other (proposal section 45). */
                        $assessed = $report->validation?->assessed_damage_percent;
                        $estimate = $report->crops->avg('estimated_damage_percent');
                        $shown    = $assessed ?? $estimate;

                        /* The little pin in the first column, coloured by that
                           percentage. Same bands as the severity scale, so the
                           colour means the same thing here as on the map. */
                        $pin = match (true) {
                            $shown === null => '#94a3b8',
                            $shown >= 100   => '#6b21a8',
                            $shown >= 51    => '#e11d48',
                            $shown >= 26    => '#ca8a04',
                            default         => '#22c55e',
                        };
                    @endphp

                    <tr>
                        <td class="whitespace-nowrap font-medium">
                            <span class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none"
                                     stroke="{{ $pin }}" stroke-width="2.2" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 21s7-5.7 7-11a7 7 0 10-14 0c0 5.3 7 11 7 11z"/>
                                    <circle cx="12" cy="10" r="2.5"/>
                                </svg>
                                {{ $report->reference }}
                            </span>
                            @if ($report->photos_count)
                                <span class="ml-6 block text-xs text-muted-foreground">
                                    {{ $report->photos_count }} farmer photo{{ $report->photos_count === 1 ? '' : 's' }}
                                </span>
                            @endif
                        </td>

                        <td>
                            <span class="font-medium">{{ $report->farmer?->full_name ?? 'Unknown' }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ $report->farmer?->association?->name ?? 'No association' }}
                            </span>
                        </td>

                        <td class="text-muted-foreground">
                            {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? '-' }}
                        </td>

                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($report->crops as $crop)
                                    <x-ui.badge variant="primary">
                                        {{ $crop->crop_specify ?: $crop->crop?->name }}
                                    </x-ui.badge>
                                @endforeach
                            </div>
                        </td>

                        <td class="whitespace-nowrap">
                            @if ($shown === null)
                                <span class="text-muted-foreground">-</span>
                            @else
                                <span class="font-semibold">{{ round($shown) }}%</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ $assessed !== null ? 'you assessed' : 'farmer estimate' }}
                                </span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap text-muted-foreground">
                            {{ $report->created_at?->format('M d, Y') }}
                        </td>

                        <td><x-ui.status :value="$report->status" /></td>

                        <td class="whitespace-nowrap text-right">
                            {{-- One button per row, and it says what happens next
                                 rather than a generic "View". --}}
                            @if ($report->status === 'assigned')
                                <x-ui.button size="sm" variant="view" :href="route('technician.reports.show', $report)">
                                    View Details
                                </x-ui.button>
                            @elseif ($report->status === 'under_verification')
                                <x-ui.button size="sm" :href="route('technician.inspection.edit', $report)">
                                    Continue
                                </x-ui.button>
                            @else
                                <x-ui.button size="sm" variant="outline"
                                             :href="route('technician.reports.show', $report)">
                                    View
                                </x-ui.button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            </div>{{-- /hidden sm:block --}}

            <x-slot:footer>
                <div class="flex justify-end">
                    <a href="{{ route('technician.reports.index') }}"
                       class="text-sm font-medium text-primary hover:underline">View All</a>
                </div>
            </x-slot:footer>
        @endif
    </x-ui.card>

    {{-- =================================================================
         BOTTOM ROW: upcoming, summary donut, quick access
    ================================================================== --}}
    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Upcoming activities.
             These are not entries in a calendar somebody has to maintain.
             They ARE the assignments that have not been started yet, oldest
             first, which is exactly the list a technician plans their day
             around. --}}
        <x-ui.card title="Upcoming Activities" description="Hindi pa nasisimulan">
            @if ($upcoming->isEmpty())
                <p class="py-4 text-center text-sm text-muted-foreground">
                    Nothing waiting. Every report assigned to you has been started.
                </p>
            @else
                <ul class="space-y-3">
                    @foreach ($upcoming as $item)
                        <li>
                            <a href="{{ route('technician.reports.show', $item) }}"
                               class="flex items-start gap-3 rounded-lg px-2 py-1.5 -mx-2 transition hover:bg-muted">

                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center
                                             rounded-lg bg-accent text-primary" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <rect x="3" y="5" width="18" height="16" rx="2"/>
                                        <path d="M3 10h18M8 3v4M16 3v4"/>
                                    </svg>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">
                                        Field Validation
                                        &middot; {{ $item->reportedBarangay?->name ?? $item->farmer?->barangay?->name ?? 'Location not set' }}
                                    </span>
                                    <span class="block text-xs text-muted-foreground">
                                        {{ $item->reference }}
                                        &middot; assigned {{ $item->assigned_at?->format('M d, Y') ?? 'recently' }}
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-slot:footer>
                <div class="flex justify-end">
                    <a href="{{ route('technician.validation.index') }}"
                       class="text-sm font-medium text-primary hover:underline">View All</a>
                </div>
            </x-slot:footer>
        </x-ui.card>

        {{-- Validation summary. Two slices only, so the chart makes one
             point instead of five. --}}
        <x-ui.card title="Validation Summary" :description="$periods[$period]">
            @if ($donut['total'] === 0)
                <p class="py-10 text-center text-sm text-muted-foreground">
                    No assigned reports in this period yet.
                </p>
            @else
                <div class="relative mx-auto h-52 w-52">
                    <canvas id="validationDonut"
                            data-validated="{{ $donut['validated'] }}"
                            data-pending="{{ $donut['pending'] }}"
                            aria-label="Validated versus pending reports"></canvas>

                    {{-- The total sits in the hole in the middle, as in the
                         mockup. pointer-events-none so it does not block the
                         chart's own hover tooltips. --}}
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-bold leading-none">{{ $donut['total'] }}</span>
                        <span class="mt-1 text-xs text-muted-foreground">Total Reports</span>
                    </div>
                </div>

                {{-- A written legend as well as colour, so the chart still
                     works for anyone who cannot separate the two greens. --}}
                <ul class="mt-4 space-y-2 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" style="background:#166534"></span>
                            Validated
                        </span>
                        <span class="font-semibold">{{ $donut['validated'] }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" style="background:#eab308"></span>
                            Pending
                        </span>
                        <span class="font-semibold">{{ $donut['pending'] }}</span>
                    </li>
                </ul>
            @endif
        </x-ui.card>

        {{-- Quick access. One tap to whatever is next, so a technician does
             not have to read the table to work out where to start. --}}
        <x-ui.card title="Quick Access Inspection" description="Simulan agad">
            @if ($nextUp)
                <a href="{{ $nextUp->status === 'under_verification'
                        ? route('technician.inspection.edit', $nextUp)
                        : route('technician.reports.show', $nextUp) }}"
                   class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed border-input
                          px-4 py-6 text-center transition hover:border-primary hover:bg-accent">

                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-accent text-primary">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4"/>
                        </svg>
                    </span>

                    <span>
                        <span class="block text-base font-semibold">
                            {{ $nextUp->status === 'under_verification' ? 'Continue Inspection' : 'Validate Report' }}
                        </span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">
                            {{ $nextUp->reference }}
                            &middot; {{ $nextUp->reportedBarangay?->name ?? $nextUp->farmer?->barangay?->name ?? 'Location not set' }}
                        </span>
                    </span>
                </a>
            @else
                <p class="py-8 text-center text-sm text-muted-foreground">
                    You are all caught up. Nothing is waiting to be inspected.
                    <span class="mt-1 block text-xs">Wala na pong naghihintay na ulat.</span>
                </p>
            @endif
        </x-ui.card>
    </div>
</div>
@endsection

@push('scripts')
    @if ($donut['total'] > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
            /*
             * The validation summary donut.
             *
             * The counts are written into data attributes by Blade rather than
             * printed into JavaScript, which keeps the numbers coming from the
             * database and keeps this script from turning into templated code.
             */
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Chart === 'undefined') return;

                const canvas = document.getElementById('validationDonut');
                if (! canvas) return;

                new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Validated', 'Pending'],
                        datasets: [{
                            data: [
                                Number(canvas.dataset.validated),
                                Number(canvas.dataset.pending),
                            ],
                            // Dark green and amber: different in hue AND in
                            // lightness, so they stay apart in greyscale and
                            // for colour-blind viewers.
                            backgroundColor: ['#166534', '#eab308'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        cutout: '72%',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            // The written legend under the chart does this job
                            // better, and it does not disappear on a phone.
                            legend: { display: false },
                        },
                    },
                });
            });
        </script>
    @endif
@endpush
