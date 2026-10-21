<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export Report, for the three Crop Management Report tabs.
 *
 * WHAT THIS IS NOT
 *
 * It is not the Monthly Report (sections 74 to 76). That one is a period
 * summary with its own sections and its own history, built by
 * MonthlyReportBuilder and kept under Reports & Analytics. This is the
 * humbler thing the office asks for far more often: the list currently on
 * screen, as a file, so it can be mailed, printed for a meeting or totalled
 * in Excel.
 *
 * WHAT IT COVERS
 *
 * Whatever the filters on screen are showing, every page of it, not just the
 * page being looked at. A paginated export would be a strange thing to hand
 * someone. The filters in force are printed at the top of the file, so a
 * reader who was not the one pressing the button can tell what the figures
 * do and do not include. That matters at a defence: a sheet of 40 reports
 * means nothing until you know it was 40 reports for one association in one
 * typhoon.
 *
 * TWO FORMATS, ON PURPOSE
 *
 * Excel for anything the office will sort, filter or total; PDF for
 * something to print, sign or attach to a memo. Section 76 asks for both on
 * the monthly report, so both is what the panel will expect here too.
 *
 * Both are built from one array of rows, so the two files can never disagree
 * about what was exported.
 *
 * It follows the same libraries the Monthly Report already uses, PhpSpreadsheet
 * and Dompdf, rather than adding a third dependency for the same job.
 */
class RecordListExport
{
    /** Agricultural green, matching the system's own primary. */
    private const HEADER_FILL = 'FF166534';

    /**
     * @param  string  $format   'xlsx' or 'pdf'; anything else is treated as xlsx
     * @param  string  $title    what the file is, printed at the top
     * @param  array   $filters  label => value of the filters in force, already readable
     * @param  array   $headings one per column
     * @param  array   $rows     list of arrays, each the same width as $headings
     * @param  string  $basename filename without the extension or the date
     */
    public static function download(
        string $format,
        string $title,
        array $filters,
        array $headings,
        array $rows,
        string $basename
    ) {
        $stamp = now()->format('Y-m-d-His');

        return $format === 'pdf'
            ? self::pdf($title, $filters, $headings, $rows, $basename . '-' . $stamp . '.pdf')
            : self::excel($title, $filters, $headings, $rows, $basename . '-' . $stamp . '.xlsx');
    }

    /**
     * The office's working copy: one sheet, a short header block, then the
     * table with the heading row frozen so it stays put while scrolling.
     */
    private static function excel(
        string $title,
        array $filters,
        array $headings,
        array $rows,
        string $filename
    ) {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Records');

        $width    = max(count($headings), 1);
        $lastCol  = self::columnLetter($width);

        // ---- header block -------------------------------------------------
        $meta = [
            $title,
            'Farmers Information and Technology Services Center - Tanza, Cavite',
            'Generated ' . now()->format('d F Y, g:i A') . ' by ' . self::who(),
            'Covers: ' . self::describeFilters($filters),
            count($rows) . ' ' . str('record')->plural(count($rows)),
        ];

        foreach ($meta as $index => $line) {
            $row = $index + 1;
            $sheet->setCellValue('A' . $row, $line);
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A' . count($meta))->getFont()->setSize(10);

        // ---- the table ----------------------------------------------------
        $headingRow = count($meta) + 2;          // one blank line between

        foreach ($headings as $index => $heading) {
            $sheet->setCellValue(self::columnLetter($index + 1) . $headingRow, $heading);
        }

        $headingStyle = $sheet->getStyle('A' . $headingRow . ':' . $lastCol . $headingRow);
        $headingStyle->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $headingStyle->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::HEADER_FILL);
        $headingStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($headingRow)->setRowHeight(22);

        $cursor = $headingRow + 1;

        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                // setCellValueExplicit is deliberately NOT used: the office
                // wants to total the hectare and percentage columns, so a
                // number has to arrive as a number. Strings that merely look
                // numeric (a reference like DR-0003) are not at risk.
                $sheet->setCellValue(self::columnLetter($index + 1) . $cursor, $value);
            }

            $cursor++;
        }

        if ($cursor > $headingRow + 1) {
            $sheet->getStyle('A' . $headingRow . ':' . $lastCol . ($cursor - 1))
                ->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFD1D5DB');
        }

        for ($column = 1; $column <= $width; $column++) {
            $sheet->getColumnDimension(self::columnLetter($column))->setAutoSize(true);
        }

        // Freeze under the heading row, so the columns stay named while the
        // reader scrolls a long list.
        $sheet->freezePane('A' . ($headingRow + 1));

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * The printable copy. Landscape, because these tables are wider than
     * they are tall and portrait would shrink the type past reading.
     */
    private static function pdf(
        string $title,
        array $filters,
        array $headings,
        array $rows,
        string $filename
    ) {
        $options = new Options();
        $options->set('isRemoteEnabled', false);     // nothing is fetched over the network
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.record-list', [
            'title'     => $title,
            'filters'   => self::describeFilters($filters),
            'headings'  => $headings,
            'rows'      => $rows,
            'generated' => now()->format('d F Y, g:i A'),
            'who'       => self::who(),
        ])->render());

        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * The filters in force, as one readable line. "All records" when there
     * are none, rather than an empty space the reader has to interpret.
     */
    private static function describeFilters(array $filters): string
    {
        $parts = [];

        foreach ($filters as $label => $value) {
            if (filled($value)) {
                $parts[] = $label . ': ' . $value;
            }
        }

        return $parts === [] ? 'All records' : implode('   |   ', $parts);
    }

    /** Who pressed the button, for the file's own header. */
    private static function who(): string
    {
        $user = Auth::user();

        return $user?->full_name ?: ($user?->username ?? 'the system');
    }

    /** 1 => A, 27 => AA. These tables are nowhere near AA, but the rule is cheap. */
    private static function columnLetter(int $index): string
    {
        $letter = '';

        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index  = intdiv($index, 26);
        }

        return $letter ?: 'A';
    }
}
