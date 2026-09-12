@extends('layouts.app')

@section('title', 'Reports')
@section('heading', 'Reports')
@section('heading-fil', 'Mga Ulat')
@section('subheading', 'Your own inspection performance. Nothing here is shared or downloaded - it is just for you.')

@section('content')

{{--
    This is NOT the MAO's Monthly Reports module (that one lives under
    mao/reports and exports PDF/Excel for the whole office). This page is a
    technician looking at their own numbers: how many inspections they have
    finished, how long each one takes, and what kind of damage they have
    been assessing. On screen only.
--}}

<div class="space-y-4">

    <div class="flex justify-end">
        <form method="GET" action="{{ route('technician.summaries.index') }}">
            <select name="period" onchange="this.form.submit()"
                    class="h-9 rounded-md border border-input bg-card px-3 text-sm font-medium shadow-sm">
                @foreach ($periods as $key => $label)
                    <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="stagger grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Inspections Completed"
                   :value="number_format($summary['completed'])"
                   tone="primary"
                   :hint="strtolower($periods[$period])"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8" />

        <x-ui.stat label="Average Turnaround"
                   :value="$summary['avg_hours'] !== null ? number_format($summary['avg_hours'], 1) : '-'"
                   :suffix="$summary['avg_hours'] !== null ? 'hrs' : null"
                   hint="Start Inspection to Confirm &amp; Submit"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2" />

        <x-ui.stat label="Average Gap vs. Farmer Estimate"
                   :value="$summary['avg_gap'] !== null ? number_format($summary['avg_gap'], 1) : '-'"
                   :suffix="$summary['avg_gap'] !== null ? 'pts' : null"
                   tone="warning"
                   hint="How far your assessments sit from what farmers first reported"
                   icon="M9 20l-5.4 1.8A1 1 0 013 20.9V6.4a1 1 0 01.7-1L9 3.7m0 16.3l6-2.1m-6 2.1V3.7" />
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
        <x-ui.card title="Inspections Completed by Month" description="Last 6 months">
            @if (collect($byMonth)->sum('total') === 0)
                <p class="py-10 text-center text-sm text-muted-foreground">
                    Nothing submitted in the last 6 months yet.
                </p>
            @else
                <div class="h-52">
                    <canvas id="monthlyBar" aria-label="Inspections completed per month"></canvas>
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
