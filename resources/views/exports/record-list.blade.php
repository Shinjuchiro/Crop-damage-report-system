{{--
    THE PRINTABLE EXPORT

    Rendered by Dompdf, not by a browser, which is why this file looks like
    it was written in 2004: inline styles, a table for layout, no flexbox
    and no CSS variables. Dompdf supports a narrow slice of CSS, and the
    system's own stylesheet is not in that slice.

    It is also why nothing here is fetched over the network. The seal is not
    drawn as an image: Dompdf would have to resolve a URL to render it, and
    a PDF that depends on the web server being reachable at the moment it is
    printed is a PDF that fails in a meeting room.

    The header block repeats the filters that produced the list. A sheet of
    forty reports means nothing until the reader knows it was forty reports
    for one association in one typhoon.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm 10mm; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: {{ $fontSize }}pt;
            color: #111827;
            margin: 0;
        }

        .office {
            font-size: 8pt;
            letter-spacing: .4pt;
            text-transform: uppercase;
            color: #166534;
            margin: 0 0 2pt;
        }

        h1 {
            font-size: 14pt;
            margin: 0 0 4pt;
            color: #0d3d1b;
        }

        .meta {
            font-size: 8pt;
            color: #4b5563;
            margin: 0 0 1.5pt;
        }

        .rule {
            border-bottom: 1.5pt solid #166534;
            margin: 7pt 0 9pt;
        }

        /* FIXED LAYOUT, WIDTHS FROM RecordListExport::layout() (Oct 2026)

           Automatic layout let a 21 column table run off the right edge of
           the page, silently dropping the last columns. With a fixed layout
           the column widths below are the widths, and a cell that is too
           narrow for its text wraps instead of pushing the table wider. */
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }

        th, td { word-wrap: break-word; overflow-wrap: break-word; }

        thead th {
            background: #166534;
            color: #ffffff;
            font-size: {{ max($fontSize - 0.5, 6) }}pt;
            text-align: left;
            padding: 5pt {{ $padding }}pt;
            border: .5pt solid #166534;
        }

        tbody td {
            padding: 4pt {{ $padding }}pt;
            border: .5pt solid #d1d5db;
            vertical-align: top;
        }

        /* Dompdf repeats thead on every page by itself; the zebra striping
           is what keeps a wide row readable across the page. */
        tbody tr:nth-child(even) td { background: #f3f6f4; }

        .empty {
            padding: 20pt;
            text-align: center;
            color: #6b7280;
            font-style: italic;
        }

        .foot {
            margin-top: 9pt;
            font-size: 7.5pt;
            color: #6b7280;
        }
    </style>
</head>
<body>

    <p class="office">Farmers Information and Technology Services Center &middot; Tanza, Cavite</p>
    <h1>{{ $title }}</h1>
    <p class="meta"><strong>Covers:</strong> {{ $filters }}</p>
    <p class="meta"><strong>Records:</strong> {{ number_format(count($rows)) }}</p>
    <p class="meta">Generated {{ $generated }} by {{ $who }}</p>

    <div class="rule"></div>

    <table>
        <thead>
            <tr>
                {{-- Dompdf takes a fixed table's widths from this first row. --}}
                @foreach ($headings as $i => $heading)
                    <th style="width: {{ $widths[$i] ?? (100 / max(count($headings), 1)) }}%">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach (array_values($row) as $value)
                        <td>{{ $value === '' || $value === null ? '-' : $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ max(count($headings), 1) }}">
                        No records matched these filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="foot">
        This export reflects the records held at the time of generation. Figures may change
        as technicians submit inspections and the office records its decisions.
    </p>

</body>
</html>
