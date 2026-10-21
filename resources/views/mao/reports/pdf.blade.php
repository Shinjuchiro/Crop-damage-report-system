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
    /* The listings. Smaller type than the summaries above, because a damage
       report row carries seventeen columns and the alternative is a table
       nobody can read across. */
    .records { page-break-before: always; }
    .records h2 { margin-bottom: 2pt; }
    .records .count { margin: 0 0 6pt; font-size: 8pt; color: #6b7280; }
    .records .empty { margin: 0 0 10pt; font-size: 9pt; color: #6b7280; font-style: italic; }

    table.list { font-size: 7pt; table-layout: fixed; width: 100%; }
    table.list th, table.list td { padding: 3pt 4pt; word-wrap: break-word; }
    table.list tbody tr:nth-child(even) td { background: #f5f7f6; }
</style>
</head>
<body>
    {{--
        Shared by two exports, because MonthlyReportBuilder returns the same
        shape either way: the MAO's whole-municipality report, and an
        association's own report scoped to its members
        (Association\ReportController::downloadPdf passes $association).
        Only this heading differs, so only this heading is conditional.
    --}}
    @php $forAssociation = $association ?? null; @endphp

    <h1>Monthly Report: {{ $data['period_label'] }}</h1>
    <p class="muted">
        @if ($forAssociation)
            {{ $forAssociation->name }}<br>
            Crop Damage Reporting and Assistance Allocation System &mdash; Tanza, Cavite.
            Figures cover this association's members only.<br>
        @else
            Crop Damage Reporting and Assistance Allocation System &mdash; Municipal Agriculture Office, Tanza, Cavite.<br>
        @endif
        Generated {{ $data['generated_at']->format('F j, Y g:i A') }}.
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
        {{-- Cash Allocated and In-Kind Allocated were here, with Total
             Distributed below. Every amount left the system in Oct 2026, at
             the client's request, for privacy. --}}
        <tr><td>Farmers Selected</td><td class="num">{{ $data['assistance']['farmers_designated'] }}</td></tr>
        <tr><td>Allocations Made</td><td class="num">{{ $data['assistance']['total_allocated'] }}</td></tr>
        <tr><td>Distributions Recorded</td><td class="num">{{ $data['assistance']['distributions_recorded'] }}</td></tr>
        <tr><td>Beneficiaries</td><td class="num">{{ $data['assistance']['beneficiaries'] }}</td></tr>
        <tr><td>Confirmed Received</td><td class="num">{{ $data['assistance']['confirmed_received'] }}</td></tr>
        <tr><td>Not Received</td><td class="num">{{ $data['assistance']['not_received'] }}</td></tr>
    </table>

    {{-- ============================================================
         THE RECORDS THEMSELVES

         Everything above is a count. These are the rows behind those
         counts, every one of them named: who registered, who planted
         what, who reported damage and what it cost them, which technician
         inspected it, who was allocated assistance and who signed for it.
         Proposal section 76 asks for exactly these listings, and until now
         this document could be read end to end without one person's name
         in it.

         They come after the summaries, so the first page is still the page
         somebody can hand across a table.

         page-break-before keeps a listing from starting three rows above
         the bottom of a page, and the <thead> is repeated by Dompdf on
         every page by itself, which is what keeps a long listing readable
         after the first page.
    ============================================================= --}}
    @foreach ($data['records'] ?? [] as $listing)
        <div class="records">
            <h2>{{ $listing['title'] }}</h2>

            @if (count($listing['rows']) === 0)
                <p class="empty">No records in this period.</p>
            @else
                <p class="count">{{ number_format(count($listing['rows'])) }}
                    {{ \Illuminate\Support\Str::plural('record', count($listing['rows'])) }}</p>

                <table class="list">
                    <thead>
                        <tr>
                            @foreach ($listing['headings'] as $heading)
                                <th>{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listing['rows'] as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell === '' || $cell === null ? '-' : $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

</body>
</html>
