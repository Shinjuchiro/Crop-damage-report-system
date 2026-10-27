<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reports (sidebar item) - a technician's own performance summary.
 *
 * Not to be confused with the MAO's Monthly Reports (proposal sections
 * 74-76), which cover the whole office and export to PDF and Excel. This
 * page is one technician looking at their own work: how many inspections
 * they have finished, how long each one takes them, and what kind of
 * damage they have been assessing. On screen only - nothing here is
 * downloaded, because it never leaves this technician's own account.
 */
class SummaryController extends Controller
{
    /**
     * The period selector. Defaults to This Year rather than This Month,
     * because a technician's own trend is more useful to see across a
     * longer stretch than the dashboard's day-to-day queue is.
     */
    public const PERIODS = [
        'this_year'     => 'This Year',
        'last_6_months' => 'Last 6 Months',
    ];

    public function index(Request $request)
    {
        $technicianId = Auth::id();

        $period = $this->periodOf($request);

        [$from, $to] = $this->periodRange($period);

        $base = Validation::where('technician_id', $technicianId)
            ->whereNotNull('validated_at')
            ->whereHas('damageReport', fn ($report) => $report->active())
            ->whereBetween('validated_at', [$from, $to]);

        // Not period based: it is the work on this technician right now.
        $pendingValidation = DamageReport::active()
            ->where('assigned_technician_id', $technicianId)
            ->whereIn('status', ['assigned', 'under_verification'])
            ->count();

        $completed = (clone $base)->count();

        /*
        |----------------------------------------------------------------
        | Average turnaround: Start Inspection to Confirm & Submit.
        |
        | In whole hours rather than days, because a technician does not
        | always finish a field visit the same day it was started, and
        | "1 day" would read as an overnight delay that never happened.
        |----------------------------------------------------------------
        */
        $avgHours = (clone $base)
            ->whereNotNull('inspection_started_at')
            ->get(['inspection_started_at', 'validated_at'])
            ->map(fn ($v) => $v->inspection_started_at->diffInHours($v->validated_at))
            ->avg();

        /*
        |----------------------------------------------------------------
        | Severity breakdown for the selected period.
        |----------------------------------------------------------------
        */
        $severityCounts = (clone $base)
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $severityBreakdown = collect(Validation::SEVERITY_SCALE)
            ->map(fn ($band, $key) => $band + [
                'key'   => $key,
                'total' => (int) ($severityCounts[$key] ?? 0),
            ])
            ->values();

        /*
        |----------------------------------------------------------------
        | Inspections completed by month, for the selected period: the
        | 12 months of this year, or the last 6 calendar months.
        |----------------------------------------------------------------
        */
        $months = $period === 'last_6_months'
            ? collect(range(5, 0))->map(fn ($i) => Carbon::now()->subMonthsNoOverflow($i)->startOfMonth())
            : collect(range(1, 12))->map(fn ($m) => Carbon::create(Carbon::now()->year, $m, 1)->startOfMonth());

        $monthlyCounts = Validation::where('technician_id', $technicianId)
            ->whereNotNull('validated_at')
            ->whereHas('damageReport', fn ($report) => $report->active())
            ->whereBetween('validated_at', [$months->first(), $months->last()->copy()->endOfMonth()])
            ->select(
                DB::raw('YEAR(validated_at) as y'),
                DB::raw('MONTH(validated_at) as m'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($row) => $row->y . '-' . $row->m);

        $byMonth = $months->map(function ($month) use ($monthlyCounts) {
            $row = $monthlyCounts->get($month->year . '-' . $month->month);

            return [
                'label' => $month->format('M Y'),
                'total' => (int) ($row->total ?? 0),
            ];
        })->values();

        return view('technician.summaries.index', [
            'period'  => $period,
            'periods' => self::PERIODS,
            'summary' => [
                'completed' => $completed,
                'avg_hours' => $avgHours,
                'pending'   => $pendingValidation,
            ],
            'severityBreakdown' => $severityBreakdown,
            'byMonth'           => $byMonth,
        ]);
    }

    private function periodRange(string $period): array
    {
        return match ($period) {
            'last_6_months' => [Carbon::now()->subMonthsNoOverflow(5)->startOfMonth(), Carbon::now()->endOfMonth()],
            default         => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
        };
    }

    private function periodOf(Request $request): string
    {
        return array_key_exists((string) $request->query('period'), self::PERIODS)
            ? $request->query('period')
            : 'this_year';
    }

    /**
     * Optional export: a spreadsheet of this technician's OWN completed
     * inspections for the selected period. Only their own rows, so it never
     * becomes a municipal report (those are the MAO's).
     */
    public function exportSummary(Request $request)
    {
        $period = $this->periodOf($request);
        [$from, $to] = $this->periodRange($period);

        $rows = Validation::where('technician_id', Auth::id())
            ->whereNotNull('validated_at')
            ->whereBetween('validated_at', [$from, $to])
            ->whereHas('damageReport', fn ($report) => $report->active())
            ->with(['damageReport.farmer.barangay', 'damageReport.reportedBarangay', 'damageReport.crops'])
            ->orderBy('validated_at')
            ->get();

        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws    = $sheet->getActiveSheet()->setTitle('My inspections');
        $ws->fromArray([[
            'Report', 'Farmer', 'Barangay', 'Inspection started', 'Submitted',
            'Hours', 'Farmer estimate %', 'Assessed %', 'Severity',
        ]], null, 'A1');

        $line = 2;
        foreach ($rows as $v) {
            $report   = $v->damageReport;
            $estimate = $report?->crops->avg('estimated_damage_percent');

            $ws->fromArray([[
                $report?->full_reference,
                $report?->farmer?->full_name,
                $report?->reportedBarangay?->name ?? $report?->farmer?->barangay?->name,
                $v->inspection_started_at?->format('Y-m-d H:i'),
                $v->validated_at?->format('Y-m-d H:i'),
                $v->inspection_started_at ? $v->inspection_started_at->diffInHours($v->validated_at) : null,
                $estimate !== null ? round($estimate) : null,
                $v->assessed_damage_percent !== null ? round($v->assessed_damage_percent) : null,
                Validation::severityLabel($v->severity),
            ]], null, 'A' . $line++);
        }

        foreach (range('A', 'I') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
        $ws->getStyle('A1:I1')->getFont()->setBold(true);

        $filename = 'my-inspections-' . str_replace('_', '-', $period) . '.xlsx';

        return response()->streamDownload(function () use ($sheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Print or save ONE inspection assessment as a PDF. The technician can
     * only open their own submitted inspections.
     */
    public function assessmentPdf(Validation $validation)
    {
        abort_unless($validation->technician_id === Auth::id(), 403);
        abort_if($validation->validated_at === null, 404);

        $validation->load([
            'technician',
            'damageReport.farmer.barangay',
            'damageReport.farmer.association',
            'damageReport.reportedBarangay',
            'damageReport.crops.crop',
            'damageReport.disasters',
        ]);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml(view('technician.history.pdf', [
            'validation' => $validation,
            'report'     => $validation->damageReport,
        ])->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $filename = 'inspection-' . str($validation->damageReport?->reference ?? $validation->id)->slug() . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
