<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Monthly Report - {{ $data['period_label'] }}</title>
<style>
    /* Dompdf's CSS support is limited, so this is plain and print-oriented
       rather than the app's usual Tailwind styling. */
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1c1c1c; }
    h1 { font-size: 18px; margin: 0 0 2px; color: #14532d; }
    h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 2px solid #14532d; padding-bottom: 3px; color: #14532d; }
    h3 { font-size: 11px; margin: 10px 0 4px; }
    p.muted { color: #555; margin: 0 0 14px; line-height: 1.5; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
    th { background: #f1f5f1; font-weight: bold; }
    .num { text-align: right; }
</style>
</head>
<body>
    <h1>Monthly Report: {{ $data['period_label'] }}</h1>
    <p class="muted">
        {{ $association->name }} &mdash; Crop Damage Reporting and Assistance Allocation System, Tanza, Cavite.<br>
        Scoped to this association's own members. Generated {{ $data['generated_at']->format('F j, Y g:i A') }}.
    </p>

    <h2>Farmer Summary</h2>
    <table>
        <tr><th>Metric</th><th class="num">Value</th></tr>
        <tr><td>Total Farmers</td><td class="num">{{ $data['farmer']['total_farmers'] }}</td></tr>
        <tr><td>New Registrations</td><td class="num">{{ $data['farmer']['new_registrations'] }}</td></tr>
        <tr><td>Verified Farmers</td><td class="num">{{ $data['farmer']['verified_farmers'] }}</td></tr>
        <tr><td>Pending Farmers</td><td class="num">{{ $data['farmer']['pending_farmers'] }}</td></tr>
        <tr><td>Rejected Farmers</td><td class="num">{{ $data['farmer']['rejected_farmers'] }}</td></tr>
        <tr><td>Active Farmers</td><td class="num">{{ $data['farmer']['active_farmers'] }}</td></tr>
        <tr><td>Inactive Farmers</td><td class="num">{{ $data['farmer']['inactive_farmers'] }}</td></tr>
        <tr><td>Land Owners</td><td class="num">{{ $data['farmer']['land_owners'] }}</td></tr>
        <tr><td>Tenants</td><td class="num">{{ $data['farmer']['tenants'] }}</td></tr>
        <tr><td>Affected Farmers</td><td class="num">{{ $data['farmer']['affected_farmers'] }}</td></tr>
    </table>

    <h2>Farm Summary</h2>
    <table>
        <tr><th>Metric</th><th class="num">Value</th></tr>
        <tr><td>Total Farms</td><td class="num">{{ $data['farm']['total_farms'] }}</td></tr>
        <tr><td>Total Farm Area (ha)</td><td class="num">{{ number_format($data['farm']['total_farm_area'], 2) }}</td></tr>
    </table>
    <h3>Main Crops</h3>
    <table>
        <tr><th>Crop</th><th class="num">Farmers</th></tr>
        @forelse ($data['farm']['main_crops'] as $row)
            <tr><td>{{ $row->name }}</td><td class="num">{{ $row->total }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>
    <h3>Farms by Barangay</h3>
    <table>
        <tr><th>Barangay</th><th class="num">Farms</th></tr>
        @forelse ($data['farm']['by_barangay'] as $row)
            <tr><td>{{ $row->name }}</td><td class="num">{{ $row->farmers_count }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Planting Summary</h2>
    <table>
        <tr><th>Metric</th><th class="num">Value</th></tr>
        <tr><td>Planting Activities</td><td class="num">{{ $data['planting']['activities'] }}</td></tr>
        <tr><td>Crops Planted</td><td class="num">{{ $data['planting']['crops_planted'] }}</td></tr>
        <tr><td>Total Area Planted (ha)</td><td class="num">{{ number_format($data['planting']['total_area'], 2) }}</td></tr>
    </table>
    <table>
        <tr><th>Crop</th><th class="num">Records</th><th class="num">Area (ha)</th></tr>
        @forelse ($data['planting']['by_crop'] as $row)
            <tr><td>{{ $row->name }}</td><td class="num">{{ $row->records }}</td><td class="num">{{ number_format($row->area, 2) }}</td></tr>
        @empty
            <tr><td colspan="3">No planting activity this period</td></tr>
        @endforelse
    </table>

    <h2>Damage Summary</h2>
    <table>
        <tr><th>Metric</th><th class="num">Value</th></tr>
        <tr><td>Total Damage Reports</td><td class="num">{{ $data['damage']['total_reports'] }}</td></tr>
        <tr><td>Affected Farmers</td><td class="num">{{ $data['damage']['affected_farmers'] }}</td></tr>
        <tr><td>Total Affected Area (ha)</td><td class="num">{{ number_format($data['damage']['total_area'], 2) }}</td></tr>
    </table>
    <h3>By Barangay</h3>
    <table>
        <tr><th>Barangay</th><th class="num">Reports</th></tr>
        @forelse ($data['damage']['by_barangay'] as $row)
            <tr><td>{{ $row->name }}</td><td class="num">{{ $row->total }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>
    <h3>By Crop</h3>
    <table>
        <tr><th>Crop</th><th class="num">Reports</th><th class="num">Area (ha)</th></tr>
        @forelse ($data['damage']['by_crop'] as $row)
            <tr><td>{{ $row->name }}</td><td class="num">{{ $row->reports }}</td><td class="num">{{ number_format($row->area, 2) }}</td></tr>
        @empty
            <tr><td colspan="3">No data</td></tr>
        @endforelse
    </table>
    <h3>By Cause of Damage</h3>
    <table>
        <tr><th>Cause</th><th class="num">Reports</th></tr>
        @foreach ($data['damage']['by_cause'] as $row)
            <tr><td>{{ $row['label'] }}</td><td class="num">{{ $row['total'] }}</td></tr>
        @endforeach
    </table>
    <h3>By Declared Disaster Event</h3>
    <table>
        <tr><th>Event</th><th class="num">Reports</th></tr>
        @forelse ($data['damage']['by_disaster'] as $row)
            <tr><td>{{ $row->name }}</td><td class="num">{{ $row->total }}</td></tr>
        @empty
            <tr><td colspan="2">No declared event linked this period</td></tr>
        @endforelse
    </table>
    <h3>By Status</h3>
    <table>
        <tr><th>Status</th><th class="num">Reports</th></tr>
        @foreach ($data['damage']['by_status'] as $status => $total)
            <tr><td>{{ ucwords(str_replace('_', ' ', $status)) }}</td><td class="num">{{ $total }}</td></tr>
        @endforeach
    </table>
    <h3>By Severity (Technician-Assessed)</h3>
    <table>
        <tr><th>Severity</th><th class="num">Reports</th></tr>
        @foreach ($data['damage']['by_severity'] as $row)
            <tr><td>{{ $row['label'] }}</td><td class="num">{{ $row['total'] }}</td></tr>
        @endforeach
    </table>

    <h2>Verification Summary</h2>
    <table>
        <tr><th>Metric</th><th class="num">Value</th></tr>
        <tr><td>Reports Assigned</td><td class="num">{{ $data['verification']['reports_assigned'] }}</td></tr>
        <tr><td>Inspections Completed</td><td class="num">{{ $data['verification']['inspections_completed'] }}</td></tr>
        <tr><td>Verified Locations</td><td class="num">{{ $data['verification']['verified_locations'] }}</td></tr>
    </table>
    <h3>Inspections by Technician</h3>
    <table>
        <tr><th>Technician</th><th class="num">Inspections Completed</th></tr>
        @forelse ($data['verification']['by_technician'] as $row)
            <tr><td>{{ $row->full_name }}</td><td class="num">{{ $row->total }}</td></tr>
        @empty
            <tr><td colspan="2">No inspections completed this period</td></tr>
        @endforelse
    </table>

    <h2>Assistance Summary</h2>
    <table>
        <tr><th>Metric</th><th class="num">Value</th></tr>
        <tr><td>Cash Allocated</td><td class="num">{{ number_format($data['assistance']['cash_allocated'], 2) }}</td></tr>
        <tr><td>In-Kind Allocated</td><td class="num">{{ number_format($data['assistance']['in_kind_allocated'], 2) }}</td></tr>
        <tr><td>Allocations Made</td><td class="num">{{ $data['assistance']['total_allocated'] }}</td></tr>
        <tr><td>Distributions Recorded</td><td class="num">{{ $data['assistance']['distributions_recorded'] }}</td></tr>
        <tr><td>Total Distributed</td><td class="num">{{ number_format($data['assistance']['total_distributed_quantity'], 2) }}</td></tr>
        <tr><td>Beneficiaries</td><td class="num">{{ $data['assistance']['beneficiaries'] }}</td></tr>
        <tr><td>Confirmed Received</td><td class="num">{{ $data['assistance']['confirmed_received'] }}</td></tr>
        <tr><td>Not Received</td><td class="num">{{ $data['assistance']['not_received'] }}</td></tr>
    </table>
</body>
</html>
