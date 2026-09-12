<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
 * The association's own scoped copy of MAO's Monthly Reports page (proposal
 * sections 74-76, and BUILD-STATUS's Association Reports build). An officer
 * picks a month and year, reviews the parameters, generates the report, and
 * can view it on screen or download it as PDF or Excel — exactly like the
 * MAO's page, except every figure only ever covers this association's own
 * members.
 *
 * That scoping is not re-implemented here: MonthlyReportBuilder::forPeriod()
 * takes the association's id as a third argument and applies the same
 * filtering the MAO's office-wide report already trusts, so the two pages
 * can never disagree about how a shared figure is computed. The Excel
 * workbook is built by the same MonthlyReportSpreadsheetBuilder the MAO uses
 * too, so both exports share one sheet layout.
 *
 * report_generations.association_id is always set to this association's id
 * for a row created here, so this page's history never mixes with the MAO's
 * office-wide list, and one association never sees another's history.
 */
class ReportController extends Controller
{
    use ResolvesAssociation;

    public function index()
    {
        $association = $this->association();

        $months = collect(range(1, 12))
            ->mapWithKeys(fn ($m) => [$m => Carbon::create()->month($m)->format('F')]);

        $earliestYear = Carbon::parse($association->created_at)->year;
        $years        = collect(range(now()->year, min($earliestYear, now()->year)))
            ->mapWithKeys(fn ($y) => [$y => $y]);

        $history = ReportGeneration::where('association_id', $association->id)
            ->with('generatedBy')
            ->orderByDesc('generated_at')
            ->paginate(10);

        return view('association.summaries.index', compact('association', 'months', 'years', 'history'));
    }

    public function store(Request $request)
    {
        $association = $this->association();

        $data = $request->validate([
            'year'  => ['required', 'integer', 'min:2020', 'max:' . now()->year],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $generation = ReportGeneration::create([
            'association_id' => $association->id,
            'year'           => $data['year'],
            'month'          => $data['month'],
            'generated_by'   => Auth::id(),
            'generated_at'   => now(),
        ]);

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Generated monthly report for ' . $association->name . ' — '
                . Carbon::create($data['year'], $data['month'], 1)->format('F Y'),
            'target_table' => 'report_generations',
            'target_id'    => $generation->id,
            'created_at'   => now(),
        ]);

        return redirect()
            ->route('association.summaries.show', ['year' => $data['year'], 'month' => $data['month']])
            ->with('status', 'Report generated successfully.');
    }

    public function show(int $year, int $month)
    {
        $this->assertValidPeriod($year, $month);
        $association = $this->association();

        return view('association.summaries.show', [
            'association' => $association,
            'data'        => MonthlyReportBuilder::forPeriod($year, $month, $association->id),
        ]);
    }

    public function downloadPdf(int $year, int $month)
    {
        $this->assertValidPeriod($year, $month);
        $association = $this->association();

        $data = MonthlyReportBuilder::forPeriod($year, $month, $association->id);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('association.summaries.pdf', ['data' => $data, 'association' => $association])->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $filename = 'monthly-report-' . str($association->name)->slug() . '-'
            . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadExcel(int $year, int $month)
    {
        $this->assertValidPeriod($year, $month);
        $association = $this->association();

        $data        = MonthlyReportBuilder::forPeriod($year, $month, $association->id);
        $spreadsheet = MonthlyReportSpreadsheetBuilder::build($data);
        $filename    = 'monthly-report-' . str($association->name)->slug() . '-'
            . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.xlsx';

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
