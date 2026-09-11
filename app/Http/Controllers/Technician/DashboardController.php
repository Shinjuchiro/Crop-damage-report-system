<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Technician Dashboard  (proposal section 39)
 *
 * This is the first screen a technician sees. It answers three questions
 * without them having to click anything:
 *
 *   1. How much work is waiting for me?
 *   2. Did anything new land on me today?
 *   3. How much have I actually finished?
 *
 * The one rule that runs through every query in this file: a technician sees
 * ONLY their own assignments. Every query below is filtered by
 * assigned_technician_id, and the inspection pages check ownership again
 * before doing anything, because hiding a row from a list is not the same
 * thing as protecting it.
 *
 * Nothing on this page is hardcoded. Every number comes from a query.
 */
class DashboardController extends Controller
{
    /**
     * The period selector at the top right of the page.
     * Key is what appears in the URL, value is what the user reads.
     */
    public const PERIODS = [
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year'  => 'This Year',
        'all'        => 'All Time',
    ];

    public function index(Request $request)
    {
        $technicianId = Auth::id();

        // Fall back to This Month if somebody types a period we do not know.
        $period = array_key_exists((string) $request->query('period'), self::PERIODS)
            ? $request->query('period')
            : 'this_month';

        [$from, $to] = $this->periodRange($period);

        /* ==============================================================
         | 1. THE TILES
         |
         | "Pending Validation" and "Assigned Today" are deliberately NOT
         | filtered by the period. They describe the work sitting on the
         | technician right now, and a backlog does not stop mattering
         | because you changed a dropdown to last month.
         ============================================================== */

        $pendingValidation = DamageReport::where('assigned_technician_id', $technicianId)
            ->whereIn('status', ['assigned', 'under_verification'])
            ->count();

        $assignedToday = DamageReport::where('assigned_technician_id', $technicianId)
            ->whereDate('assigned_at', Carbon::today())
            ->count();

        // Inspections this technician actually submitted. Counted from the
        // validations table, not from the report status, because the report
        // can move on to approved or rejected afterwards and the inspection
        // still happened.
        $validationReports = Validation::where('technician_id', $technicianId)
            ->whereNotNull('validated_at')
            ->when($from, fn ($q) => $q->whereBetween('validated_at', [$from, $to]))
            ->count();

        /* ==============================================================
         | 2. THE ASSIGNED INSPECTIONS TABLE
         ============================================================== */

        $reports = DamageReport::where('assigned_technician_id', $technicianId)
            ->with([
                'farmer.barangay',
                'farmer.association',
                'reportedBarangay',
                'crops.crop',
                'disasters',
                'validation',
            ])
            ->withCount('photos')
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            // Status chip. Anything we do not recognise falls through to "all".
            ->when($request->query('status') === 'to_inspect', fn ($q) => $q->where('status', 'assigned'))
            ->when($request->query('status') === 'in_progress', fn ($q) => $q->where('status', 'under_verification'))
            ->when($request->query('status') === 'done', fn ($q) => $q->whereIn('status', ['verified', 'approved']))
            // Optional barangay filter behind the Filter button.
            ->when($request->query('barangay'), function ($q, $barangay) {
                $q->where(function ($inner) use ($barangay) {
                    $inner->where('reported_barangay_id', $barangay)
                          ->orWhereHas('farmer', fn ($f) => $f->where('barangay_id', $barangay));
                });
            })
            // Work that has not been started comes first. That is the queue.
            ->orderByRaw("FIELD(status, 'assigned', 'under_verification', 'flagged', 'verified', 'approved', 'rejected')")
            ->latest()
            ->limit(6)
            ->get();

        /* ==============================================================
         | 3. UPCOMING ACTIVITIES
         |
         | Not a separate calendar table. These ARE the assignments that
         | have not been started yet, shown the way a technician thinks
         | about them: "field validation, this barangay, handed to me on
         | this date". Oldest first, because that is the one going stale.
         ============================================================== */

        $upcoming = DamageReport::where('assigned_technician_id', $technicianId)
            ->where('status', 'assigned')
            ->with(['reportedBarangay', 'farmer.barangay'])
            ->orderBy('assigned_at')
            ->limit(5)
            ->get();

        /* ==============================================================
         | 4. VALIDATION SUMMARY (the donut)
         |
         | Two slices only, so the chart says one thing clearly:
         | of the reports on my desk in this period, how many have I
         | finished and how many are still open?
         ============================================================== */

        $donutCounts = DB::table('damage_reports')
            ->where('assigned_technician_id', $technicianId)
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $validated = (int) ($donutCounts['verified'] ?? 0)
                   + (int) ($donutCounts['approved'] ?? 0);

        $stillOpen = (int) ($donutCounts['assigned'] ?? 0)
                   + (int) ($donutCounts['under_verification'] ?? 0)
                   + (int) ($donutCounts['flagged'] ?? 0);

        /* ==============================================================
         | 5. QUICK ACCESS
         |
         | One tap to the next thing that needs doing, so the technician
         | does not have to scan the table to find where to start.
         ============================================================== */

        $nextUp = DamageReport::where('assigned_technician_id', $technicianId)
            ->whereIn('status', ['assigned', 'under_verification'])
            ->orderByRaw("FIELD(status, 'under_verification', 'assigned')")
            ->orderBy('assigned_at')
            ->first();

        return view('technician.dashboard', [
            'reports'  => $reports,
            'upcoming' => $upcoming,
            'nextUp'   => $nextUp,
            'period'   => $period,
            'periods'  => self::PERIODS,
            'filter'   => $request->query('status'),
            'barangay' => $request->query('barangay'),

            // Only barangays that this technician actually has work in.
            // A dropdown of all 41 barangays would be noise.
            'barangays' => Barangay::whereIn('id', function ($query) use ($technicianId) {
                    $query->select('reported_barangay_id')
                          ->from('damage_reports')
                          ->where('assigned_technician_id', $technicianId)
                          ->whereNotNull('reported_barangay_id');
                })
                ->orderBy('name')
                ->get(),

            'summary' => [
                'pending_validation' => $pendingValidation,
                'assigned_today'     => $assignedToday,
                'validation_reports' => $validationReports,
            ],

            'donut' => [
                'validated' => $validated,
                'pending'   => $stillOpen,
                'total'     => $validated + $stillOpen,
            ],
        ]);
    }

    /**
     * Turns a period key into a pair of dates for whereBetween.
     *
     * Returns [null, null] for "all", and every caller uses ->when($from, ...)
     * so a null simply means "do not filter by date at all".
     */
    private function periodRange(string $period): array
    {
        return match ($period) {
            'last_month' => [
                Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                Carbon::now()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            'all'       => [null, null],
            default     => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };
    }
}
