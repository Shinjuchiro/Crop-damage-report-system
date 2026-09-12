@extends('layouts.app')

@section('title', 'Monthly Report - ' . $data['period_label'])
@section('heading', 'Monthly Report: ' . $data['period_label'])
@section('subheading', 'Generated ' . $data['generated_at']->format('M j, Y g:i A') . '. Every figure below is computed live from the database.')

@section('content')
<div class="space-y-6">

    <div class="flex flex-wrap items-center justify-end gap-2">
        <x-ui.button variant="outline" href="{{ route('mao.reports.index') }}">Back to Reports</x-ui.button>
        <x-ui.button variant="outline"
            href="{{ route('mao.reports.pdf', ['year' => $data['year'], 'month' => $data['month']]) }}">
            Download PDF
        </x-ui.button>
        <x-ui.button href="{{ route('mao.reports.excel', ['year' => $data['year'], 'month' => $data['month']]) }}">
            Download Excel
        </x-ui.button>
    </div>

    {{-- Farmer Summary --}}
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

    {{-- Farm Summary --}}
    <x-ui.card title="Farm Summary">
        <div class="mb-5 grid gap-4 sm:grid-cols-2">
            <x-stat-card label="Total Farms" :value="$data['farm']['total_farms']" />
            <x-stat-card label="Total Farm Area" :value="number_format($data['farm']['total_farm_area'], 2)" suffix="hectares" />
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-semibold text-foreground">Main Crops</p>
                <x-ui.table>
                    <x-slot:head><tr><th>Crop</th><th class="text-right">Farmers</th></tr></x-slot:head>
                    @forelse ($data['farm']['main_crops'] as $row)
                        <tr><td>{{ $row->name }}</td><td class="text-right tabular-nums">{{ $row->total }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-4 text-center text-muted-foreground">No data</td></tr>
                    @endforelse
                </x-ui.table>
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold text-foreground">Farms by Barangay</p>
                <x-ui.table>
                    <x-slot:head><tr><th>Barangay</th><th class="text-right">Farms</th></tr></x-slot:head>
                    @forelse ($data['farm']['by_barangay'] as $row)
                        <tr><td>{{ $row->name }}</td><td class="text-right tabular-nums">{{ $row->farmers_count }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-4 text-center text-muted-foreground">No data</td></tr>
                    @endforelse
                </x-ui.table>
            </div>
        </div>
    </x-ui.card>

    {{-- Planting Summary --}}
    <x-ui.card title="Planting Summary">
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Planting Activities" :value="$data['planting']['activities']" />
            <x-stat-card label="Crops Planted" :value="$data['planting']['crops_planted']" />
            <x-stat-card label="Total Area Planted" :value="number_format($data['planting']['total_area'], 2)" suffix="hectares" />
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

    {{-- Damage Summary --}}
    <x-ui.card title="Damage Summary">
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Total Damage Reports" :value="$data['damage']['total_reports']" />
            <x-stat-card label="Affected Farmers" :value="$data['damage']['affected_farmers']" />
            <x-stat-card label="Total Affected Area" :value="number_format($data['damage']['total_area'], 2)" suffix="hectares" />
        </div>

        <div class="mb-5 grid gap-5 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-semibold text-foreground">By Barangay</p>
                <x-ui.table>
                    <x-slot:head><tr><th>Barangay</th><th class="text-right">Reports</th></tr></x-slot:head>
                    @forelse ($data['damage']['by_barangay'] as $row)
                        <tr><td>{{ $row->name }}</td><td class="text-right tabular-nums">{{ $row->total }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-4 text-center text-muted-foreground">No data</td></tr>
                    @endforelse
                </x-ui.table>
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold text-foreground">By Crop</p>
                <x-ui.table>
                    <x-slot:head><tr><th>Crop</th><th class="text-right">Reports</th><th class="text-right">Area (ha)</th></tr></x-slot:head>
                    @forelse ($data['damage']['by_crop'] as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td class="text-right tabular-nums">{{ $row->reports }}</td>
                            <td class="text-right tabular-nums">{{ number_format($row->area, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-muted-foreground">No data</td></tr>
                    @endforelse
                </x-ui.table>
            </div>
        </div>

        <div class="mb-5 grid gap-5 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-semibold text-foreground">By Cause of Damage</p>
                <x-ui.table>
                    <x-slot:head><tr><th>Cause</th><th class="text-right">Reports</th></tr></x-slot:head>
                    @foreach ($data['damage']['by_cause'] as $row)
                        <tr><td>{{ $row['label'] }}</td><td class="text-right tabular-nums">{{ $row['total'] }}</td></tr>
                    @endforeach
                </x-ui.table>
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold text-foreground">By Declared Disaster Event</p>
                <p class="mb-2 text-xs text-muted-foreground">
                    Only reports linked to an event the office has declared. See Cause of Damage above for the complete picture.
                </p>
                <x-ui.table>
                    <x-slot:head><tr><th>Event</th><th class="text-right">Reports</th></tr></x-slot:head>
                    @forelse ($data['damage']['by_disaster'] as $row)
                        <tr><td>{{ $row->name }}</td><td class="text-right tabular-nums">{{ $row->total }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-4 text-center text-muted-foreground">No declared event linked this period</td></tr>
                    @endforelse
                </x-ui.table>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-semibold text-foreground">By Status</p>
                <x-ui.table>
                    <x-slot:head><tr><th>Status</th><th class="text-right">Reports</th></tr></x-slot:head>
                    @foreach ($data['damage']['by_status'] as $status => $total)
                        <tr><td class="capitalize">{{ str_replace('_', ' ', $status) }}</td><td class="text-right tabular-nums">{{ $total }}</td></tr>
                    @endforeach
                </x-ui.table>
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold text-foreground">By Severity (Technician-Assessed)</p>
                <x-ui.table>
                    <x-slot:head><tr><th>Severity</th><th class="text-right">Reports</th></tr></x-slot:head>
                    @foreach ($data['damage']['by_severity'] as $row)
                        <tr><td>{{ $row['label'] }}</td><td class="text-right tabular-nums">{{ $row['total'] }}</td></tr>
                    @endforeach
                </x-ui.table>
            </div>
        </div>
    </x-ui.card>

    {{-- Verification Summary --}}
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

    {{-- Assistance Summary --}}
    <x-ui.card title="Assistance Summary">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Cash Allocated" :value="number_format($data['assistance']['cash_allocated'], 2)" />
            <x-stat-card label="In-Kind Allocated" :value="number_format($data['assistance']['in_kind_allocated'], 2)" />
            <x-stat-card label="Allocations Made" :value="$data['assistance']['total_allocated']" />
            <x-stat-card label="Distributions Recorded" :value="$data['assistance']['distributions_recorded']" />
            <x-stat-card label="Total Distributed" :value="number_format($data['assistance']['total_distributed_quantity'], 2)" />
            <x-stat-card label="Beneficiaries" :value="$data['assistance']['beneficiaries']" />
            <x-stat-card label="Confirmed Received" :value="$data['assistance']['confirmed_received']" />
            <x-stat-card label="Not Received" :value="$data['assistance']['not_received']" />
        </div>
    </x-ui.card>
</div>
@endsection
