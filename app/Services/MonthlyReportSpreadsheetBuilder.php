<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Turns one MonthlyReportBuilder::forPeriod() array into an Excel workbook:
 * the summary sheets first, then one sheet per kind of record, every row
 * named.
 *
 * Pulled out of MAO\ReportController so the Association's own Reports page
 * (proposal-style monthly report, scoped to one association - see
 * BUILD-STATUS) can produce an Excel file with exactly the same sheet
 * layout, from exactly the same code, rather than a second hand-maintained
 * copy that could quietly drift out of step with section 76's "separate
 * sheets for..." list.
 */
class MonthlyReportSpreadsheetBuilder
{
    /**
     * One sheet per section of proposal section 76 ("Excel may contain
     * separate sheets for..."). Each sheet is built as a plain grid of rows
     * and written in one call via fromArray(), with the row indexes that are
     * section headings passed in to be bolded afterward.
     */
    public static function build(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        self::putSheet($spreadsheet, 'Farmer Summary', [
            ['Metric', 'Value'],
            ['Total Farmers', $data['farmer']['total_farmers']],
            ['New Registrations', $data['farmer']['new_registrations']],
            ['Verified Farmers', $data['farmer']['verified_farmers']],
            ['Pending Farmers', $data['farmer']['pending_farmers']],
            ['Rejected Farmers', $data['farmer']['rejected_farmers']],
            ['Active Farmers', $data['farmer']['active_farmers']],
            ['Inactive Farmers', $data['farmer']['inactive_farmers']],
            ['Land Owners', $data['farmer']['land_owners']],
            ['Tenants', $data['farmer']['tenants']],
            ['Affected Farmers', $data['farmer']['affected_farmers']],
        ]);

        $farmRows = [
            ['Metric', 'Value'],
            ['Total Farms', $data['farm']['total_farms']],
            ['Total Farm Area (ha)', round($data['farm']['total_farm_area'], 2)],
            [],
            ['Main Crops', 'Farmers'],
        ];
        $bold = [0, count($farmRows) - 1];
        foreach ($data['farm']['main_crops'] as $row) {
            $farmRows[] = [$row->name, $row->total];
        }
        $farmRows[] = [];
        $bold[] = count($farmRows);
        $farmRows[] = ['Barangay', 'Farms'];
        foreach ($data['farm']['by_barangay'] as $row) {
            $farmRows[] = [$row->name, $row->farmers_count];
        }
        self::putSheet($spreadsheet, 'Farm Summary', $farmRows, $bold);

        $plantingRows = [
            ['Metric', 'Value'],
            ['Planting Activities', $data['planting']['activities']],
            ['Crops Planted', $data['planting']['crops_planted']],
            ['Total Area Planted (ha)', round($data['planting']['total_area'], 2)],
            [],
            ['Crop', 'Records', 'Area (ha)'],
        ];
        $bold = [0, count($plantingRows) - 1];
        foreach ($data['planting']['by_crop'] as $row) {
            $plantingRows[] = [$row->name, $row->records, round($row->area, 2)];
        }
        self::putSheet($spreadsheet, 'Planting Summary', $plantingRows, $bold);

        $damageRows = [
            ['Metric', 'Value'],
            ['Total Damage Reports', $data['damage']['total_reports']],
            ['Affected Farmers', $data['damage']['affected_farmers']],
            ['Total Affected Area (ha)', round($data['damage']['total_area'], 2)],
            [],
            ['Barangay', 'Reports'],
        ];
        $bold = [0, count($damageRows) - 1];
        foreach ($data['damage']['by_barangay'] as $row) {
            $damageRows[] = [$row->name, $row->total];
        }
        $damageRows[] = [];
        $bold[] = count($damageRows);
        $damageRows[] = ['Crop', 'Reports', 'Area (ha)'];
        foreach ($data['damage']['by_crop'] as $row) {
            $damageRows[] = [$row->name, $row->reports, round($row->area, 2)];
        }
        $damageRows[] = [];
        $bold[] = count($damageRows);
        $damageRows[] = ['Cause of Damage', 'Reports'];
        foreach ($data['damage']['by_cause'] as $row) {
            $damageRows[] = [$row['label'], $row['total']];
        }
        $damageRows[] = [];
        $bold[] = count($damageRows);
        $damageRows[] = ['Declared Disaster Event', 'Reports'];
        foreach ($data['damage']['by_disaster'] as $row) {
            $damageRows[] = [$row->name, $row->total];
        }
        $damageRows[] = [];
        $bold[] = count($damageRows);
        $damageRows[] = ['Status', 'Reports'];
        foreach ($data['damage']['by_status'] as $status => $total) {
            $damageRows[] = [ucwords(str_replace('_', ' ', $status)), $total];
        }
        $damageRows[] = [];
        $bold[] = count($damageRows);
        $damageRows[] = ['Severity (Technician-Assessed)', 'Reports'];
        foreach ($data['damage']['by_severity'] as $row) {
            $damageRows[] = [$row['label'], $row['total']];
        }
        self::putSheet($spreadsheet, 'Damage Summary', $damageRows, $bold);

        $verificationRows = [
            ['Metric', 'Value'],
            ['Reports Assigned', $data['verification']['reports_assigned']],
            ['Inspections Completed', $data['verification']['inspections_completed']],
            ['Verified Locations', $data['verification']['verified_locations']],
            [],
            ['Technician', 'Inspections Completed'],
        ];
        $bold = [0, count($verificationRows) - 1];
        foreach ($data['verification']['by_technician'] as $row) {
            $verificationRows[] = [$row->full_name, $row->total];
        }
        self::putSheet($spreadsheet, 'Verification Summary', $verificationRows, $bold);

        self::putSheet($spreadsheet, 'Assistance Summary', [
            ['Metric', 'Value'],
            /* Cash Allocated, In-Kind Allocated and Total Distributed were
               here, and are the three figures the master prompt's section 75
               asks for by name. Every amount came out of this system in Oct
               2026 at the client's request, for privacy. The rows below
               count batches and people instead. */
            ['Allocations Made', $data['assistance']['total_allocated']],
            ['Farmers Selected', $data['assistance']['farmers_designated']],
            ['Distributions Recorded', $data['assistance']['distributions_recorded']],
            ['Beneficiaries', $data['assistance']['beneficiaries']],
            ['Confirmed Received', $data['assistance']['confirmed_received']],
            ['Not Received', $data['assistance']['not_received']],
        ]);

        /* ---------- the records themselves ----------
         |
         | Proposal section 76 lists separate sheets for Damage Reports,
         | Planting Activities, Technician Inspections, Assistance and
         | Distribution. Until now every sheet above was a SUMMARY, so a
         | workbook could be opened and closed without one person's name
         | appearing in it. These are the records behind those counts, one
         | row each, named - see MonthlyReportBuilder::records().
         |
         | They go after the summaries rather than before them: the first
         | sheet somebody opens should still be the figure they came for.
         */
        foreach ($data['records'] ?? [] as $listing) {
            self::putSheet(
                $spreadsheet,
                $listing['title'],
                array_merge([$listing['headings']], $listing['rows'] ?: [
                    ['No records in this period'],
                ])
            );
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private static function putSheet(Spreadsheet $spreadsheet, string $title, array $rows, array $boldRowIndexes = [0]): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(substr($title, 0, 31)); // Excel's own sheet-name limit

        if ($rows) {
            $sheet->fromArray($rows, null, 'A1');
        }

        $highestColumn = $sheet->getHighestColumn();

        foreach ($boldRowIndexes as $index) {
            $excelRow = $index + 1; // fromArray rows are 0-indexed, Excel rows are 1-indexed
            $sheet->getStyle('A' . $excelRow . ':' . $highestColumn . $excelRow)->getFont()->setBold(true);
        }

        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
