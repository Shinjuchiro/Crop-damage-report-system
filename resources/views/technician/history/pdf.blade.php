{{-- One submitted inspection assessment, printable. Technician's own record only. --}}
@php
    $estimate = $report?->crops->avg('estimated_damage_percent');
    $assessed = $validation->assessed_damage_percent;
    $ha = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . ' ha';
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 17px; margin: 0 0 2px; color: #14532d; }
        h2 { font-size: 12px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d1d5db; text-transform: uppercase; letter-spacing: .04em; color: #14532d; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; color: #4b5563; }
        .kv td:first-child { width: 32%; color: #6b7280; }
        .box { border: 1px solid #d1d5db; padding: 8px; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>Inspection Assessment</h1>
    <p class="muted">{{ $report?->full_reference }} &middot; submitted {{ $validation->validated_at?->format('F j, Y g:i A') }}</p>

    <h2>Farmer and farm</h2>
    <table class="kv">
        <tr><td>Farmer</td><td>{{ $report?->farmer?->full_name ?? '-' }}</td></tr>
        <tr><td>Association</td><td>{{ $report?->farmer?->association?->name ?? '-' }}</td></tr>
        <tr><td>Barangay</td><td>{{ $report?->reportedBarangay?->name ?? $report?->farmer?->barangay?->name ?? '-' }}</td></tr>
        <tr><td>Cause reported</td><td>{{ $report?->damage_cause_label ?? '-' }}</td></tr>
        <tr><td>Disaster event</td><td>{{ $report && $report->disasters->isNotEmpty() ? $report->disasters->pluck('name')->join(', ') : '-' }}</td></tr>
    </table>

    <h2>Crops reported damaged</h2>
    <table>
        <thead><tr><th>Crop</th><th>Damaged area</th><th>Farmer estimate</th></tr></thead>
        <tbody>
        @foreach ($report?->crops ?? [] as $crop)
            <tr>
                <td>{{ $crop->crop_specify ?: ($crop->crop?->name ?? '-') }}</td>
                <td>{{ $ha($crop->damaged_area_hectares) }}</td>
                <td>{{ $crop->estimated_damage_percent !== null ? round($crop->estimated_damage_percent) . '%' : '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Technician assessment</h2>
    <table class="kv">
        <tr><td>Technician</td><td>{{ $validation->technician?->display_name ?? '-' }}</td></tr>
        <tr><td>Inspection started</td><td>{{ $validation->inspection_started_at?->format('F j, Y g:i A') ?? '-' }}</td></tr>
        <tr><td>Submitted</td><td>{{ $validation->validated_at?->format('F j, Y g:i A') ?? '-' }}</td></tr>
        <tr><td>Farmer estimated damage</td><td>{{ $estimate !== null ? round($estimate) . '%' : '-' }}</td></tr>
        <tr><td>Assessed damage</td><td>{{ $assessed !== null ? round($assessed) . '%' : '-' }}</td></tr>
        <tr><td>Severity</td><td>{{ \App\Models\Validation::severityLabel($validation->severity) ?? '-' }}</td></tr>
        <tr><td>Verified location</td><td>{{ $validation->latitude !== null ? $validation->latitude . ', ' . $validation->longitude : 'Not recorded' }}</td></tr>
    </table>

    <h2>Inspection notes</h2>
    <div class="box">{{ filled($validation->notes) ? $validation->notes : 'No notes were recorded.' }}</div>

    <p class="muted" style="margin-top:18px">
        This assessment is a technical finding. It does not by itself qualify a farmer for assistance;
        that decision belongs to the Municipal Agriculture Office.
    </p>
</body>
</html>
