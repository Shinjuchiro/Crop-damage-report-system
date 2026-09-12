<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\Farmer;
use App\Models\ReportGeneration;
use App\Services\MonthlyReportBuilder;
use App\Services\MonthlyReportSpreadsheetBuilder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Proposal sections 74-76 and 91.13: MAO picks a month and year, reviews the
 * parameters, generates the report, and can view it on screen or download it
 * as PDF or Excel. See app/Services/MonthlyReportBuilder.php for how every
 * figure is actually computed — this controller only wires that up to HTTP
 * and to the two export formats. The Excel workbook itself is built by
 * MonthlyReportSpreadsheetBuilder, shared with Association\ReportController
 * so the office-wide and per-association reports can never format a sheet
 * differently.
 *
 * Nothing is stored except a small audit trail (report_generations) of who
 * generated which period and when, for the Report History list. The report
 * itself is always rebuilt live, so re-opening an old period reflects
 * whatever the database says today rather than a stale snapshot.
 *
 * report_generations.association_id (added when the Association module got
 * its own Reports page) is always null for a row created here, so this
 * page's history stays the office-wide list it always was — an
 * association's own generated periods live in their own history instead,
 * never mixed into this one.
 */
class ReportController extends Controller
{
    public function index()
    {
        $months = collect(range(1, 12))
            ->mapWithKeys(fn ($m) => [$m => Carbon::create()->month($m)->format('F')]);

        $earliestYear = collect([Farmer::min('created_at'), DamageReport::min('created_at')])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->year)
            ->min() ?? now()->year;

        $years = collect(range(now()->year, $earliestYear))->mapWithKeys(fn ($y) => [$y => $y]);

        $history = ReportGeneration::whereNull('association_id')
            ->with('generatedBy')
            ->orderByDesc('generated_at')
            ->paginate(10);

        return view('mao.reports.index', compact('months', 'years', 'history'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year'  => ['required', 'integer', 'min:2020', 'max:' . now()->year],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $generation = ReportGeneration::create([
            'year'         => $data['year'],
            'month'        => $data['month'],
            'generated_by' => Auth::id(),
            'generated_at' => now(),
        ]);

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Generated monthly report for '
                . Carbon::create($data['year'], $data['month'], 1)->format('F Y'),
            'target_table' => 'report_generations',
            'target_id'    => $generation->id,
            'created_at'   => now(),
        ]);

        return redirect()
            ->route('mao.reports.show', ['year' => $data['year'], 'month' => $data['month']])
            ->with('status', 'Report generated successfully.');
    }

    public function show(int $year, int $month)
    {
        $this->assertValidPeriod($year, $month);

        return view('mao.reports.show', ['data' => MonthlyReportBuilder::forPeriod($year, $month)]);
    }

    public function downloadPdf(int $year, int $month)
    {
        $this->assertValidPeriod($year, $month);

        $data = MonthlyReportBuilder::forPeriod($year, $month);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('mao.reports.pdf', ['data' => $data])->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $filename = 'monthly-report-' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadExcel(int $year, int $month)
    {
        $this->assertValidPeriod($year, $month);

        $data        = MonthlyReportBuilder::forPeriod($year, $month);
        $spreadsheet = MonthlyReportSpreadsheetBuilder::build($data);
        $filename    = 'monthly-report-' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function assertValidPeriod(int $year, int $month): void
    {
        abort_unless($month >= 1 && $month <= 12 && $year >= 2020 && $year <= now()->year, 404);
    }
}
