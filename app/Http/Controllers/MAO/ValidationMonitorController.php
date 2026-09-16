<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\NotificationBroadcast;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * MAO watches field verification here and assigns reports to technicians.
 *
 * The MAO never enters an inspection result. Severity, assessed damage and the
 * verified location are the technician's alone.
 */
class ValidationMonitorController extends Controller
{
    public function index(Request $request)
    {
        $reports = DamageReportMonitorController::baseQuery($request)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'unassigned'         => DamageReport::whereNull('assigned_technician_id')->count(),
            'assigned'           => DamageReport::where('status', 'assigned')->count(),
            'under_verification' => DamageReport::where('status', 'under_verification')->count(),
            'verified'           => DamageReport::whereIn('status', ['verified', 'approved'])->count(),
        ];

        // List + detail panel (Sept 2026): "View Details" loads the report
        // inline via ?selected=<id> using the same read-only relations as
        // Damage Reports Monitoring, instead of navigating there.
        $selected = $request->filled('selected')
            ? DamageReport::query()->with(DamageReportMonitorController::detailRelations())->find($request->selected)
            : null;

        return view('mao.validations.index', [
            'reports'      => $reports,
            'selected'     => $selected,
            'summary'      => $summary,
            'technicians'  => User::where('role', 'technician')
                                  ->where('status', 'active')
                                  ->orderBy('full_name')
                                  ->orderBy('username')
                                  ->get(),
            'associations' => Association::orderBy('name')->get(),
            'barangays'    => Barangay::orderBy('name')->get(),
        ]);
    }

    /**
     * Assign or reassign the technician who will inspect this report.
     */
    public function assign(Request $request, DamageReport $damageReport)
    {
        $data = $request->validate([
            'assigned_technician_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'technician')
                    ->where('status', 'active')),
            ],
        ], [], [
            'assigned_technician_id' => 'technician',
        ]);

        if (in_array($damageReport->status, ['under_verification', 'verified', 'approved'], true)) {
            return back()->withErrors([
                'assigned_technician_id' => 'This report is already being inspected or has been verified, so it cannot be reassigned.',
            ]);
        }

        $technician = User::find($data['assigned_technician_id']);

        DB::transaction(function () use ($damageReport, $data, $technician) {
            $damageReport->update([
                'assigned_technician_id' => $data['assigned_technician_id'],
                'status'                 => 'assigned',
                // Stamped here and nowhere else. The technician dashboard
                // counts "Assigned Today" off this column, and updated_at
                // cannot answer that question because it moves every time
                // anything on the report changes.
                'assigned_at'            => now(),
            ]);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Assigned damage report ' . $damageReport->reference
                    . ' to ' . ($technician->full_name ?: $technician->username),
                'target_table' => 'damage_reports',
                'target_id'    => $damageReport->id,
                'created_at'   => now(),
            ]);
        });

        $this->notifyTechnicianOfAssignment($damageReport, $technician);

        return back()->with('status', 'Technician assigned successfully.');
    }

    /**
     * Proposal section 66: a technician should be told about a "newly
     * assigned damage report" - this was previously a badge-only signal
     * (nav-technician.blade.php's "Assigned Reports" count), with nothing in
     * the bell itself. Runs after the transaction above has already
     * committed, and never throws - a notification failure must never make
     * an otherwise-successful assignment appear to fail (see the Sept 2026
     * notification-system rule).
     *
     * 'important' priority (in-app only, no SMS - section 68 reserves that
     * for urgent/critical matters): a new assignment is real work waiting,
     * more than routine but not an emergency. link_type/link_id let the
     * technician's bell open this exact report (NotificationBroadcast::
     * linkUrl()).
     */
    private function notifyTechnicianOfAssignment(DamageReport $damageReport, User $technician): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'New Assignment',
                'message'     => 'You have been assigned to inspect damage report ' . $damageReport->reference . '.',
                'category'    => 'system',
                'priority'    => 'important',
                'target_type' => 'specific_technician',
                'target_id'   => $technician->id,
                'link_type'   => 'damage_report',
                'link_id'     => $damageReport->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify technician ' . $technician->id . ' of assignment to report '
                . $damageReport->id . ': ' . $e->getMessage());
        }
    }
}
