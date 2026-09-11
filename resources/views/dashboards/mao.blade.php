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

    {{-- ===================== ALERTS ===================== --}}
    <div class="mb-5 rounded-xl border border-border bg-card shadow-sm">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 class="text-base font-semibold text-foreground">Alerts &amp; Notifications</h2>
            <span class="text-sm font-medium text-muted-foreground">View all</span>
        </div>

        @if ($alerts->isNotEmpty())
            <ul class="divide-y divide-border">
                @foreach ($alerts as $alert)
                    @php
                        $tone = match ($alert->priority) {
                            'critical' => ['bg-red-100', 'text-red-700', 'text-red-700'],
                            'urgent'   => ['bg-orange-100', 'text-orange-700', 'text-orange-700'],
                            'important'=> ['bg-amber-100', 'text-amber-700', 'text-amber-700'],
                            default    => ['bg-sky-100', 'text-sky-700', 'text-sky-800'],
                        };
                    @endphp
                    <li class="flex items-start gap-4 px-6 py-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $tone[0] }}">
                            <svg class="h-6 w-6 {{ $tone[1] }}" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 9v4M12 17h.01M10.3 3.9L2.4 17.5A2 2 0 004.1 20.5h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                            </svg>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold uppercase tracking-wide {{ $tone[2] }}">{{ $alert->title }}</p>
                            <p class="mt-0.5 text-sm text-muted-foreground">{{ $alert->message }}</p>
                        </div>

                        <p class="hidden shrink-0 text-sm text-muted-foreground sm:block">
                            {{ $alert->created_at?->diffForHumans() }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-sm font-medium text-muted-foreground">No alerts yet</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Advisories and announcements you send will be listed here.
                </p>
            </div>
        @endif
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

@push('scripts')
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
