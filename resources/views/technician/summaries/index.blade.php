@extends('layouts.app')

@section('title', 'Reports & Analytics')
@section('heading', 'Reports & Analytics')
@section('heading-fil', 'Mga Ulat at Pagsusuri')
@section('subheading', 'Your own inspection performance. You can export a summary of your own completed inspections; official municipal reports are the Municipal Agriculture Office\'s.')

@section('content')

@include('technician.partials.analytics-tabs')

{{--
    This is NOT the MAO's Monthly Reports module (that one lives under
    mao/reports and exports PDF/Excel for the whole office). This page is a
    technician looking at their own numbers: how many inspections they have
    finished, how long each one takes, and what kind of damage they have
    been assessing. On screen only.
--}}

<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-end gap-2">
        <form method="GET" action="{{ route('technician.summaries.index') }}">
            <select name="period" onchange="this.form.submit()" aria-label="Period"
                    class="h-9 rounded-md border border-input bg-card px-3 text-sm font-medium shadow-sm">
                @foreach ($periods as $key => $label)
                    <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <x-ui.button size="sm" variant="outline" :href="route('technician.summaries.export', ['period' => $period])">
            Export my summary (Excel)
        </x-ui.button>
    </div>

    <div class="stagger grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Inspections Completed"
                   :value="number_format($summary['completed'])"
                   tone="primary"
                   :hint="$summary['completed'] . ' of your inspections were completed, ' . strtolower($periods[$period])"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"
                   :href="route('technician.history.index')" />

        <x-ui.stat label="Average Turnaround"
                   :value="$summary['avg_hours'] !== null ? number_format($summary['avg_hours'], 1) : '-'"
                   :suffix="$summary['avg_hours'] !== null ? 'hrs' : null"
                   hint="Average from inspection start to assessment submission"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2" />

        <x-ui.stat label="Pending Validation"
                   :value="number_format($summary['pending'])"
                   tone="warning"
                   hint="Assigned reports awaiting technical validation"
                   icon="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4"
                   :href="route('technician.validation.index')" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Severity breakdown --}}
        <x-ui.card title="Severity Breakdown" :description="$periods[$period]">
            @if (collect($severityBreakdown)->sum('total') === 0)
                <p class="py-10 text-center text-sm text-muted-foreground">
                    No submitted inspections in this period yet.
                </p>
            @else
                <div class="relative mx-auto h-52 w-52">
                    <canvas id="severityDonut" aria-label="Inspections by severity"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-bold leading-none">{{ collect($severityBreakdown)->sum('total') }}</span>
                        <span class="mt-1 text-xs text-muted-foreground">Total Inspections</span>
                    </div>
                </div>

                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($severityBreakdown as $band)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full" style="background: {{ $band['color'] }}"></span>
                                {{ $band['label'] }}
                                <span class="text-xs text-muted-foreground">({{ $band['range'] }})</span>
                            </span>
                            <span class="font-semibold">{{ $band['total'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        {{-- By month --}}
        <x-ui.card title="Inspections Completed by Month" description="{{ $periods[$period] }}">
            @if (collect($byMonth)->sum('total') === 0)
                <p class="py-10 text-center text-sm text-muted-foreground">
                    Nothing submitted in this period yet.
                </p>
            @else
                {{-- Same guard as the MAO dashboard chart: min-w-0 lets the
                     wrapper shrink with its column and max-width:100% caps the
                     inline pixel width Chart.js writes onto the canvas, so a
                     wide bar chart can never push the card past the screen. --}}
                <div class="relative h-52 min-w-0">
                    <canvas id="monthlyBar" class="max-w-full" aria-label="Inspections completed per month"></canvas>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
@endsection

@push('scripts')
    @if (collect($severityBreakdown)->sum('total') > 0 || collect($byMonth)->sum('total') > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Chart === 'undefined') return;

                const severityCanvas = document.getElementById('severityDonut');
                if (severityCanvas) {
                    new Chart(severityCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: @json(collect($severityBreakdown)->pluck('label')),
                            datasets: [{
                                data: @json(collect($severityBreakdown)->pluck('total')),
                                backgroundColor: @json(collect($severityBreakdown)->pluck('color')),
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            cutout: '72%',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                        },
                    });
                }

                const monthlyCanvas = document.getElementById('monthlyBar');
                if (monthlyCanvas) {
                    new Chart(monthlyCanvas, {
                        type: 'bar',
                        data: {
                            labels: @json(collect($byMonth)->pluck('label')),
                            datasets: [{
                                label: 'Inspections',
                                data: @json(collect($byMonth)->pluck('total')),
                                backgroundColor: '#166534',
                                borderRadius: 6,
                                maxBarThickness: 42,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        },
                    });
                }
            });
        </script>
    @endif
@endpush
