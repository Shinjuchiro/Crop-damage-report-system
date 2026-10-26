@extends('layouts.app')

@section('title', 'Monthly Report - ' . $data['period_label'])
@section('heading', 'Monthly Report: ' . $data['period_label'])
@section('subheading', $association->name . ' — generated ' . $data['generated_at']->format('M j, Y g:i A') . '. Every figure below is computed live from the database, scoped to this association\'s own members.')

@section('content')
<div class="space-y-6">

    <div class="flex flex-wrap items-center justify-end gap-2">
        <x-ui.button variant="outline" href="{{ route('association.summaries.index') }}">Back to Reports</x-ui.button>
        <x-ui.button variant="outline"
            href="{{ route('association.summaries.pdf', ['year' => $data['year'], 'month' => $data['month'], 'types' => $data['everything'] ? [] : $data['types']]) }}">
            Download PDF
        </x-ui.button>
        <x-ui.button href="{{ route('association.summaries.excel', ['year' => $data['year'], 'month' => $data['month'], 'types' => $data['everything'] ? [] : $data['types']]) }}">
            Download Excel
        </x-ui.button>
    </div>

    {{-- Farmer Summary --}}
@if ($data['show']['farmer'] ?? true)
    <x-ui.card title="Farmer Summary">
        <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <x-stat-card label="Total Farmers" :value="$data['farmer']['total_farmers']" />
            <x-stat-card label="New Registrations" :value="$data['farmer']['new_registrations']" />
            <x-stat-card label="Verified" :value="$data['farmer']['verified_farmers']" />
            <x-stat-card label="Pending" :value="$data['farmer']['pending_farmers']" />
            <x-stat-card label="Rejected" :value="$data['farmer']['rejected_farmers']" />
            <x-stat-card label="Active" :value="$data['farmer']['active_farmers']" />
            <x-stat-card label="Inactive" :value="$data['farmer']['inactive_farmers']" />
            <x-stat-card label="Land Owners" :value="$data['farmer']['land_owners']" />
            <x-stat-card label="Tenants" :value="$data['farmer']['tenants']" />
            <x-stat-card label="Affected Farmers" :value="$data['farmer']['affected_farmers']" />
        </div>
    </x-ui.card>
@endif

    {{-- Farm Summary --}}
@if ($data['show']['farm'] ?? true)
    <x-ui.card title="Farm Summary">
        <x-report.breakdown-grid>
            {{-- The two figures are stacked so together they are about as tall
                 as the Main Crops table beside them. --}}
            <div class="grid gap-4" style="flex:1 1 19rem;min-width:0">
                <x-stat-card label="Total Farms" :value="$data['farm']['total_farms']" />
                <x-stat-card label="Total Farm Area" :value="number_format($data['farm']['total_farm_area'], 2)" suffix="hectares" />
            </div>

            <x-report.breakdown title="Main Crops" :headers="['Crop', 'Farmers']"
                :rows="collect($data['farm']['main_crops'])->map(fn ($r) => [$r->name, $r->total])" />

            <x-report.breakdown title="Farms by Barangay" :headers="['Barangay', 'Farms']"
                :rows="collect($data['farm']['by_barangay'])->map(fn ($r) => [$r->name, $r->farmers_count])" />
        </x-report.breakdown-grid>
    </x-ui.card>
@endif

    {{-- Planting Summary --}}
@if ($data['show']['planting'] ?? true)
    <x-ui.card title="Planting Summary">
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Planting Activities" :value="$data['planting']['activities']" />
            <x-stat-card label="Crops Planted" :value="$data['planting']['crops_planted']" />
            {{-- Same relabel as mao/reports/show: planted area in the period, not acreage. --}}
            <x-stat-card label="Area Planted This Month" :value="number_format($data['planting']['total_area'], 2)" suffix="hectares" />
        </div>
        <x-ui.table>
            <x-slot:head><tr><th>Crop</th><th class="text-right">Records</th><th class="text-right">Area (ha)</th></tr></x-slot:head>
            @forelse ($data['planting']['by_crop'] as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td class="text-right tabular-nums">{{ $row->records }}</td>
                    <td class="text-right tabular-nums">{{ number_format($row->area, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-4 text-center text-muted-foreground">No planting activity this period</td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
@endif

    {{-- Damage Summary --}}
@if ($data['show']['damage'] ?? true)
    <x-ui.card title="Damage Summary">
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Total Damage Reports" :value="$data['damage']['total_reports']" />
            <x-stat-card label="Affected Farmers" :value="$data['damage']['affected_farmers']" />
            <x-stat-card label="Total Affected Area" :value="number_format($data['damage']['total_area'], 2)" suffix="hectares" />
        </div>

        <x-report.breakdown-grid>
            <x-report.breakdown title="By Crop" :headers="['Crop', 'Reports', 'Area (ha)']"
                :rows="collect($data['damage']['by_crop'])->map(fn ($r) => [$r->name, $r->reports, number_format($r->area, 2)])" />

            <x-report.breakdown title="By Cause of Damage" :headers="['Cause', 'Reports']"
                :rows="collect($data['damage']['by_cause'])->map(fn ($r) => [$r['label'], $r['total']])" />

            <x-report.breakdown title="By Status" :headers="['Status', 'Reports']"
                :rows="collect($data['damage']['by_status'])->map(fn ($total, $status) => [ucfirst(str_replace('_', ' ', $status)), $total])" />

            <x-report.breakdown title="By Severity (Technician-Assessed)" :headers="['Severity', 'Reports']"
                :rows="collect($data['damage']['by_severity'])->map(fn ($r) => [$r['label'], $r['total']])" />

            <x-report.breakdown title="By Declared Disaster Event" :headers="['Event', 'Reports']"
                note="Only reports linked to an event the office has declared. See Cause of Damage for the complete picture."
                :rows="collect($data['damage']['by_disaster'])->map(fn ($r) => [$r->name, $r->total])"
                empty="No declared event linked this period" />

            <x-report.breakdown title="By Barangay" :headers="['Barangay', 'Reports']"
                :rows="collect($data['damage']['by_barangay'])->map(fn ($r) => [$r->name, $r->total])" />
        </x-report.breakdown-grid>
    </x-ui.card>
@endif

    {{-- Verification Summary --}}
@if ($data['show']['verification'] ?? true)
    <x-ui.card title="Verification Summary">
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Reports Assigned" :value="$data['verification']['reports_assigned']" />
            <x-stat-card label="Inspections Completed" :value="$data['verification']['inspections_completed']" />
            <x-stat-card label="Verified Locations" :value="$data['verification']['verified_locations']" />
        </div>
        <p class="mb-2 text-sm font-semibold text-foreground">Inspections by Technician</p>
        <x-ui.table>
            <x-slot:head><tr><th>Technician</th><th class="text-right">Inspections Completed</th></tr></x-slot:head>
            @forelse ($data['verification']['by_technician'] as $row)
                <tr><td>{{ $row->full_name }}</td><td class="text-right tabular-nums">{{ $row->total }}</td></tr>
            @empty
                <tr><td colspan="2" class="py-4 text-center text-muted-foreground">No inspections completed this period</td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
@endif

    {{-- Assistance Summary --}}
@if ($data['show']['assistance'] ?? true)
    <x-ui.card title="Assistance Summary">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Cash Allocated, In-Kind Allocated and Total Distributed
                 were the first three cards here. Every amount left the
                 system in Oct 2026, at the client's request, for privacy.
                 Batches and people are counted instead. --}}
            <x-stat-card label="Allocations Made" :value="$data['assistance']['total_allocated']" />
            <x-stat-card label="Farmers Selected" :value="$data['assistance']['farmers_designated']" />
            <x-stat-card label="Distributions Recorded" :value="$data['assistance']['distributions_recorded']" />
            <x-stat-card label="Beneficiaries" :value="$data['assistance']['beneficiaries']" />
            <x-stat-card label="Confirmed Received" :value="$data['assistance']['confirmed_received']" />
            <x-stat-card label="Not Received" :value="$data['assistance']['not_received']" />
        </div>
    </x-ui.card>
@endif

    {{-- The rows behind every figure above, named, and scoped to this
         association's own members exactly as the figures are - see
         x-report.listings and MonthlyReportBuilder::records(). --}}
    <x-report.listings :records="$data['records'] ?? []" />
</div>
@endsection
