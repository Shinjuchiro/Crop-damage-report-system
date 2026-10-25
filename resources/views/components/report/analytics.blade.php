{{--
    REPORTS & ANALYTICS CHARTS

        <x-report.analytics :analytics="$analytics" :years="$years" />

    Planting, damage and assistance over one year, from ReportAnalytics.
    Real counts. The year picker reloads the page with ?analytics_year=.
--}}

@props(['analytics', 'years'])

@php $id = 'an' . uniqid(); @endphp

<x-ui.card title="Analytics" description="What was planted, reported and handed out across {{ $analytics['year'] }}.">
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div>
            <label for="analytics_year" class="mb-1.5 block text-sm font-medium text-foreground">Year</label>
            <x-ui.select name="analytics_year" :options="$years" :selected="$analytics['year']" onchange="this.form.submit()" />
        </div>
    </form>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Planting Records" :value="number_format($analytics['totals']['planting'])" />
        <x-ui.stat label="Damage Reports" :value="number_format($analytics['totals']['damage'])" tone="warning" />
        <x-ui.stat label="Allocations" :value="number_format($analytics['totals']['allocated'])" />
        <x-ui.stat label="Distributions" :value="number_format($analytics['totals']['distributed'])" tone="primary" />
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="min-w-0 rounded-xl border border-border p-4">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Planting and damage by month</h3>
            <div class="h-64"><canvas id="{{ $id }}-pd" role="img" aria-label="Planting records and damage reports per month"></canvas></div>
        </div>
        <div class="min-w-0 rounded-xl border border-border p-4">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Assistance by month</h3>
            <div class="h-64"><canvas id="{{ $id }}-as" role="img" aria-label="Assistance allocated and distributed per month"></canvas></div>
        </div>
        <div class="min-w-0 rounded-xl border border-border p-4">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Damage reports by crop</h3>
            <div class="h-64"><canvas id="{{ $id }}-crop" role="img" aria-label="Damage reports by crop"></canvas></div>
        </div>
        <div class="min-w-0 rounded-xl border border-border p-4">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Damage reports by cause</h3>
            <div class="h-64"><canvas id="{{ $id }}-cause" role="img" aria-label="Damage reports by cause"></canvas></div>
        </div>
    </div>
</x-ui.card>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;
            const months = @json($analytics['months']);
            const opts = (legend) => ({ responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: legend } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } });

            new Chart(document.getElementById('{{ $id }}-pd'), { type: 'bar', data: { labels: months, datasets: [
                { label: 'Planting records', data: @json($analytics['planting']), backgroundColor: '#15803d', borderRadius: 4 },
                { label: 'Damage reports', data: @json($analytics['damage']), backgroundColor: '#f59e0b', borderRadius: 4 },
            ] }, options: opts(true) });

            new Chart(document.getElementById('{{ $id }}-as'), { type: 'line', data: { labels: months, datasets: [
                { label: 'Allocated', data: @json($analytics['allocated']), borderColor: '#0284c7', backgroundColor: '#0284c7', tension: .25 },
                { label: 'Distributed', data: @json($analytics['distributed']), borderColor: '#15803d', backgroundColor: '#15803d', tension: .25 },
            ] }, options: opts(true) });

            new Chart(document.getElementById('{{ $id }}-crop'), { type: 'bar', data: {
                labels: @json($analytics['by_crop']->keys()->values()),
                datasets: [{ label: 'Reports', data: @json($analytics['by_crop']->values()), backgroundColor: '#15803d', borderRadius: 4 }] },
                options: { ...opts(false), indexAxis: 'y' } });

            new Chart(document.getElementById('{{ $id }}-cause'), { type: 'bar', data: {
                labels: @json($analytics['by_cause']->keys()->values()),
                datasets: [{ label: 'Reports', data: @json($analytics['by_cause']->values()), backgroundColor: '#0284c7', borderRadius: 4 }] },
                options: { ...opts(false), indexAxis: 'y' } });
        });
    </script>
@endpush
