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
     * Landscape page widths in points (1 pt = 1/72 in), narrowest first.
     * "folio" is 8.5 x 13 in, the long bond paper Philippine offices
     * actually print on, and the only larger size worth offering: A3 would
     * export fine and then not go through the office printer.
     */
    private const PAPERS = ['a4' => 841.89, 'folio' => 936.0];

    /** Left and right page margin together: @page is 10 mm a side. */
    private const SIDE_MARGINS_PT = 56.7;

    /** Body type sizes tried in order, largest first. */
    private const FONT_SIZES = [8.5, 8.0, 7.5, 7.0, 6.5];

    /**
     * Horizontal cell padding, per side. A table of six columns can afford
     * 4 pt; a table of twenty-one spends a sixth of the page on padding at
     * that, and the type has to shrink to pay for it.
     */
    private const PADDING_PT = 4.0;
    private const PADDING_WIDE_PT = 2.5;
    private const WIDE_AFTER_COLUMNS = 12;

    /**
     * The printable copy. Landscape, because these tables are wider than
     * they are tall and portrait would shrink the type past reading.
     *
     * WHY THE LAYOUT IS WORKED OUT HERE (fixed Oct 2026)
     *
     * This used to hand Dompdf a 100%-wide table and let it size the
     * columns. That is fine for six columns and fails for twenty-one:
     * Dompdf's automatic layout gives every column at least the width of its
     * longest unbreakable word, the sum of those came to more than the page,
     * and it did not shrink or wrap anything. It just kept going past the
     * right edge, so the Crop Damage Reports PDF lost everything after
     * "Assessed Damage": severity, date inspected and the farmer's remarks.
     * No error, a file that looked complete, and the missing columns were
     * the technician's findings.
     *
     * So the widths are decided before Dompdf sees the table. See layout().
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

        $layout = self::layout($headings, $rows);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.record-list', [
            'title'     => $title,
            'filters'   => self::describeFilters($filters),
            'headings'  => $headings,
            'rows'      => $rows,
            'generated' => now()->format('d F Y, g:i A'),
            'who'       => self::who(),
            'widths'    => $layout['widths'],
            'fontSize'  => $layout['font'],
            'padding'   => $layout['padding'],
        ])->render());

        $dompdf->setPaper($layout['paper'], 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Choose the paper, the type size and every column's width so the whole
     * table fits the page.
     *
     * The method: measure, in characters, the longest unbreakable word each
     * column has to hold (its floor) and how wide its text would like to be
     * (its preference). Then try A4 at the largest type, then smaller type,
     * then long bond, and take the first combination where every floor fits.
     * Whatever width is left over goes to the columns that want it most, so
     * Remarks and Association grow while Date Assigned and a two-digit
     * percentage stay narrow, instead of twenty-one equal slices.
     *
     * Estimating from characters is approximate on purpose: Dompdf cannot be
     * asked how wide a word is before it lays the page out. 0.58 em per
     * character is a little above Helvetica's real average (digits are 0.556,
     * capitals up to 0.72), so a column errs wide rather than clipping a
     * phone number, and headings get a further 10 per cent for being bold.
     *
     * A single token longer than 14 characters is not allowed to set the
     * floor. Without that cap one pasted URL in a remark would force every
     * other column to shrink; the cell wraps it instead.
     *
     * @return array{paper:string, font:float, widths:array<int,float>, padding:float}
     */
    private static function layout(array $headings, array $rows): array
    {
        $count    = max(count($headings), 1);
        $padding  = $count > self::WIDE_AFTER_COLUMNS ? self::PADDING_WIDE_PT : self::PADDING_PT;
        $overhead = $padding * 2 + 1;          // both sides, plus the border
        $floor    = array_fill(0, $count, 1.0);
        $want  = array_fill(0, $count, 1.0);

        foreach (array_values($headings) as $i => $heading) {
            $longestWord = self::longestWord((string) $heading);
            $floor[$i]   = max($floor[$i], min($longestWord, 14) * 1.1);
            $want[$i]    = max($want[$i], $floor[$i]);
        }

        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $value) {
                if ($i >= $count) {
                    break;
                }

                $text = trim((string) $value);
                $text = $text === '' ? '-' : $text;

                $floor[$i] = max($floor[$i], min(self::longestWord($text), 14));
                $want[$i]  = max($want[$i], min(mb_strlen($text), 36));
            }
        }

        // A column never wants less than it needs.
        foreach ($floor as $i => $f) {
            $want[$i] = max($want[$i], $f);
        }

        $attempts = [];
        foreach (self::PAPERS as $paper => $pageWidth) {
            foreach (self::FONT_SIZES as $font) {
                $attempts[] = [$paper, $pageWidth - self::SIDE_MARGINS_PT, $font];
            }
        }

        // Nothing fits at a readable size: take the last resort rather than
        // clip. Long bond at the smallest size, and the widths below are
        // squeezed proportionally. Cells wrap their words instead.
        $chosen = null;
        foreach ($attempts as [$paper, $usable, $font]) {
            $needed = array_sum(array_map(
                fn ($f) => $f * $font * 0.58 + $overhead, $floor
            ));

            if ($needed <= $usable) {
                $chosen = [$paper, $usable, $font];
                break;
            }
        }

        $chosen ??= ['folio', self::PAPERS['folio'] - self::SIDE_MARGINS_PT, 6.0];
        [$paper, $usable, $font] = $chosen;

        $charWidth = $font * 0.58;
        $widths    = [];
        foreach ($floor as $i => $f) {
            $widths[$i] = $f * $charWidth + $overhead;
        }

        $spare = $usable - array_sum($widths);

        if ($spare > 0) {
            // First hand out what the columns asked for, most-wanting first
            // in proportion, then whatever is still left across the lot.
            $wants     = array_map(fn ($w, $f) => max(0, $w - $f) * $charWidth, $want, $floor);
            $totalWant = array_sum($wants);

            if ($totalWant > 0) {
                $share = min(1.0, $spare / $totalWant);
                foreach ($wants as $i => $w) {
                    $widths[$i] += $w * $share;
                }
            }

            $spare = $usable - array_sum($widths);

            if ($spare > 0) {
                $sum = array_sum($widths);
                foreach ($widths as $i => $w) {
                    $widths[$i] += $spare * ($w / $sum);
                }
            }
        }

        // To percentages that add to exactly 100, so the table is the page
        // width and never a hair past it.
        $total  = array_sum($widths);
        $widths = array_map(fn ($w) => round($w / $total * 100, 2), $widths);

        return ['paper' => $paper, 'font' => $font, 'widths' => $widths, 'padding' => $padding];
    }

    /** Length in characters of the longest run with no space in it. */
    private static function longestWord(string $text): int
    {
        $longest = 1;

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $longest = max($longest, mb_strlen($word));
        }

        return $longest;
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
