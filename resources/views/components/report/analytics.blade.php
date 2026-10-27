{{--
    REPORTS & ANALYTICS: CARDS AND CHARTS

        <x-report.analytics :analytics="$analytics" :years="$years" />
        <x-report.analytics :analytics="$analytics" :years="$years" mode="association" />

    One year of real counts, from App\Services\ReportAnalytics. The year picker
    reloads the page with ?analytics_year=.

    MAO (default)     4 cards, 4 charts
        cards   Planting Records, Damage Reports, Allocations, Distributions
        charts  planting and damage by month (grouped bars); farmers
                designated versus farmers who confirmed receiving, by month
                (lines); damage reports by crop and by cause (horizontal bars)

    Association       4 cards, 3 charts, this association's own members only
        cards   Total Members, Planting Records, Damage Reports, Verified Reports
        charts  planting and damage by month (grouped bars); damage reports by
                their actual status (doughnut); designated members who
                received or have not received assistance (doughnut)

    What each figure counts is written at the top of ReportAnalytics: farmers,
    reports, allocation batches and distribution batches are never mixed.
--}}

@props(['analytics', 'years', 'mode' => 'mao'])

@php
    $id     = 'an' . uniqid();
    $assoc  = $mode === 'association';
    $year   = $analytics['year'];
    $plural = fn ($n, $one, $many) => number_format($n) . ' ' . ($n === 1 ? $one : $many);
@endphp

@php
    $intro = ($assoc ? "Your members' planting, damage reports and assistance" : 'What was planted, reported and handed out') . ' across ' . $year . '.';
@endphp

<x-ui.card title="Analytics" :description="$intro">
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div>
            <label for="analytics_year" class="mb-1.5 block text-sm font-medium text-foreground">Year</label>
            <x-ui.select name="analytics_year" :options="$years" :selected="$year" onchange="this.form.submit()" />
        </div>
    </form>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @if ($assoc)
            <x-ui.stat label="Total Members" :value="number_format($analytics['total_members'])"
                       hint="Farmers in your association" />
            <x-ui.stat label="Planting Records" :value="number_format($analytics['totals']['planting'])"
                       :hint="'Submitted by your members in ' . $year" />
            <x-ui.stat label="Damage Reports" :value="number_format($analytics['totals']['damage'])" tone="warning"
                       :hint="'Submitted by your members in ' . $year" />
            <x-ui.stat label="Verified Reports" :value="number_format($analytics['verified'])" tone="primary"
                       :hint="'Technical validation completed in ' . $year" />
        @else
            <x-ui.stat label="Planting Records" :value="number_format($analytics['totals']['planting'])"
                       :hint="$plural($analytics['totals']['planting'], 'crop planting record was', 'crop planting records were') . ' submitted in ' . $year" />
            <x-ui.stat label="Damage Reports" :value="number_format($analytics['totals']['damage'])" tone="warning"
                       :hint="$plural($analytics['totals']['damage'], 'crop damage report was', 'crop damage reports were') . ' submitted in ' . $year" />
            <x-ui.stat label="Allocations" :value="number_format($analytics['totals']['allocated'])"
                       :hint="$plural($analytics['totals']['allocated'], 'assistance allocation batch was', 'assistance allocation batches were') . ' created in ' . $year" />
            <x-ui.stat label="Distributions" :value="number_format($analytics['totals']['distributed'])" tone="primary"
                       :hint="$plural($analytics['totals']['distributed'], 'assistance distribution batch was', 'assistance distribution batches were') . ' recorded in ' . $year" />
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="min-w-0 rounded-xl border border-border p-4">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Planting and damage by month</h3>
            <div class="h-64"><canvas id="{{ $id }}-pd" role="img" aria-label="Planting records and damage reports per month"></canvas></div>
            <p class="mt-2 text-xs text-muted-foreground">Individual records and reports, by the month they were filed.</p>
        </div>

        @if ($assoc)
            <div class="min-w-0 rounded-xl border border-border p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Damage reports by validation status</h3>
                @if ($analytics['by_status']->isEmpty())
                    <p class="py-16 text-center text-sm text-muted-foreground">No damage reports filed in {{ $year }}.</p>
                @else
                    <div class="h-64"><canvas id="{{ $id }}-status" role="img" aria-label="Damage reports by validation status"></canvas></div>
                @endif
                <p class="mt-2 text-xs text-muted-foreground">Where each report stands now. A verified report is not automatically designated for assistance.</p>
            </div>

            <div class="min-w-0 rounded-xl border border-border p-4 lg:col-span-2">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Assistance receipt status</h3>
                @if ($analytics['receipt']['designated'] === 0)
                    <p class="py-16 text-center text-sm text-muted-foreground">No members were designated for assistance in {{ $year }}.</p>
                @else
                    <div class="mx-auto h-64 max-w-sm"><canvas id="{{ $id }}-receipt" role="img" aria-label="Designated members who received or have not received assistance"></canvas></div>
                @endif
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ number_format($analytics['receipt']['designated']) }}
                    {{ $analytics['receipt']['designated'] === 1 ? 'member was' : 'members were' }} designated.
                    Received means the farmer confirmed it.
                </p>
            </div>
        @else
            <div class="min-w-0 rounded-xl border border-border p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Assistance by month</h3>
                <div class="h-64"><canvas id="{{ $id }}-as" role="img" aria-label="Farmers designated for assistance and farmers who confirmed receiving it, per month"></canvas></div>
                <p class="mt-2 text-xs text-muted-foreground">Farmers (people), not records. Designated by allocation date; confirmed by the date the farmer confirmed.</p>
            </div>
            <div class="min-w-0 rounded-xl border border-border p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Damage reports by crop</h3>
                <div class="h-64"><canvas id="{{ $id }}-crop" role="img" aria-label="Damage reports by crop"></canvas></div>
            </div>
            <div class="min-w-0 rounded-xl border border-border p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">Damage reports by cause</h3>
                <div class="h-64"><canvas id="{{ $id }}-cause" role="img" aria-label="Damage reports by cause"></canvas></div>
            </div>
        @endif
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
            const donut = { type: 'doughnut', options: { cutout: '62%', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } } } };

            new Chart(document.getElementById('{{ $id }}-pd'), { type: 'bar', data: { labels: months, datasets: [
                { label: 'Planting records', data: @json($analytics['planting']), backgroundColor: '#15803d', borderRadius: 4 },
                { label: 'Damage reports', data: @json($analytics['damage']), backgroundColor: '#f59e0b', borderRadius: 4 },
            ] }, options: opts(true) });

            @if ($assoc)
                const statusEl = document.getElementById('{{ $id }}-status');
                if (statusEl) {
                    new Chart(statusEl, { ...donut, data: {
                        labels: @json($analytics['by_status']->pluck('label')),
                        datasets: [{ data: @json($analytics['by_status']->pluck('total')), borderWidth: 0,
                            backgroundColor: ['#f59e0b', '#0284c7', '#6366f1', '#15803d', '#ca8a04', '#0d9488', '#dc2626'] }] } });
                }

                const receiptEl = document.getElementById('{{ $id }}-receipt');
                if (receiptEl) {
                    new Chart(receiptEl, { ...donut, data: {
                        labels: ['Received', 'Not Received'],
                        datasets: [{ data: [{{ $analytics['receipt']['received'] }}, {{ $analytics['receipt']['not_received'] }}],
                            backgroundColor: ['#15803d', '#d1d5db'], borderWidth: 0 }] } });
                }
            @else
                new Chart(document.getElementById('{{ $id }}-as'), { type: 'line', data: { labels: months, datasets: [
                    { label: 'Farmers designated', data: @json($analytics['designated']), borderColor: '#0284c7', backgroundColor: '#0284c7', tension: .25 },
                    { label: 'Farmers who confirmed receiving', data: @json($analytics['confirmed']), borderColor: '#15803d', backgroundColor: '#15803d', tension: .25 },
                ] }, options: opts(true) });

                new Chart(document.getElementById('{{ $id }}-crop'), { type: 'bar', data: {
                    labels: @json($analytics['by_crop']->keys()->values()),
                    datasets: [{ label: 'Reports', data: @json($analytics['by_crop']->values()), backgroundColor: '#15803d', borderRadius: 4 }] },
                    options: { ...opts(false), indexAxis: 'y' } });

                new Chart(document.getElementById('{{ $id }}-cause'), { type: 'bar', data: {
                    labels: @json($analytics['by_cause']->keys()->values()),
                    datasets: [{ label: 'Reports', data: @json($analytics['by_cause']->values()), backgroundColor: '#0284c7', borderRadius: 4 }] },
                    options: { ...opts(false), indexAxis: 'y' } });
            @endif
        });
    </script>
@endpush
