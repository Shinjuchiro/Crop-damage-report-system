<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\CropPlantingRecordCrop;
use App\Models\DamageReport;
use App\Models\NotificationBroadcast;
use App\Models\User;
use App\Models\Validation;
use App\Services\RecordListExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Sept 2026: this page used to be the live assignment queue (every report,
 * with an Assign/Reassign button on each row). That moved to Damage Report
 * Monitoring, which is where a report lives before a technician has been
 * sent - see DamageReportMonitorController. This page is now a HISTORY of
 * completed field work: it only lists reports a technician has actually
 * finished inspecting (validation.validated_at is set), so MAO can review
 * what was found without wading through reports still in progress.
 *
 * assign() below still exists and is still routed to from here
 * (mao.validations.assign) only because Damage Report Monitoring's own
 * "Assign Technician" button posts to this same route rather than
 * duplicating the logic - nothing here still uses it directly.
 */
class ValidationMonitorController extends Controller
{
    public function index(Request $request)
    {
        /* By farmer, or flat (revision section 12). Same switch and the
           same ordering helper the Crop Damage list uses, so the two pages
           behave identically. */
        $view = $request->query('view') === 'flat' ? 'flat' : 'grouped';

        // Export Report, built from the same filtered query as the page.
        if ($request->filled('export')) {
            return $this->export($request);
        }

        // Completed inspections only, which is all this page lists.
        $inspected = DamageReportMonitorController::baseQuery($request)
            ->whereHas('validation', fn ($v) => $v->whereNotNull('validated_at'));

        /* Grouped: a handful of farmers a page, each with every inspected
           report they have (App\Support\FarmerGroups). Flat: newest first,
           fifteen a page. */
        $reports = $view === 'grouped'
            ? \App\Support\FarmerGroups::paginate(
                $inspected,
                fn ($q) => $q->select('damage_reports.farmer_id')
                    ->groupBy('damage_reports.farmer_id')
                    ->orderByRaw('MAX(damage_reports.created_at) DESC')
                    ->orderBy('damage_reports.farmer_id'),
                fn ($q, $ids) => $q->whereIn('damage_reports.farmer_id', $ids)
                    ->latest()->orderByDesc('damage_reports.id'),
                fn ($report) => $report->farmer_id
            )
            : $inspected->latest()->paginate(15)->withQueryString();

        \App\Support\PlantingMatch::annotate($reports->getCollection());

        // The same definitions as the dashboard cards (App\Support\MonitorStats),
        // so this row can never disagree with the dashboard or with the list.
        $summary = \App\Support\MonitorStats::validation();

        // List + detail panel (Sept 2026): "View Details" loads the report
        // inline via ?selected=<id> using the same read-only relations as
        // Damage Reports Monitoring, instead of navigating there.
        $selected = $request->filled('selected')
            ? DamageReport::query()->with(DamageReportMonitorController::detailRelations())->find($request->selected)
            : null;

        \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'validation', $selected?->id);

        if ($selected) {
            \App\Support\PlantingMatch::annotate([$selected]);
        }

        return view('mao.validations.index', [
            'view'               => $view,
            'reports'            => $reports,
            'farmerTotals'       => collect(),
            'history'            => $selected ? DamageReport::query()->notDeleted()->where('farmer_id', $selected->farmer_id)
                ->whereHas('validation', fn ($v) => $v->whereNotNull('validated_at'))
                ->orderByDesc('created_at')->orderByDesc('id')
                ->get(['id', 'farmer_id', 'farmer_report_no', 'status', 'created_at']) : collect(),
            'selected'           => $selected,
            'summary'            => $summary,
            'associations'       => Association::orderBy('name')->get(),
            'barangays'          => Barangay::orderBy('name')->get(),
        ]);
    }

    /**
     * Export Report: the completed field inspections, as currently filtered.
     *
     * The columns are the ones this page exists for: what the farmer
     * estimated, what the technician assessed, and where the technician
     * actually stood. Both coordinates are included, because section 49 is
     * that the two locations are kept apart, and an export that carried
     * only one of them would quietly undo that.
     */
    private function export(Request $request)
    {
        $reports = DamageReportMonitorController::baseQuery($request)
            ->whereHas('validation', fn ($v) => $v->whereNotNull('validated_at'))
            ->latest()
            ->get();

        \App\Support\PlantingMatch::annotate($reports);

        $rows = $reports->map(function ($report) {
            $validation = $report->validation;

            return [
                $report->full_reference,
                $report->farmer?->full_name ?? 'Unknown',
                $report->farmer?->user?->phone_number ?: '-',
                $report->farmer?->barangay?->name ?? '-',
                $report->farmer?->association?->name ?? '-',
                $report->assignedTechnician?->full_name
                    ?: ($report->assignedTechnician?->username ?? '-'),
                $validation?->inspection_started_at?->format('d M Y g:i A') ?? '-',
                $validation?->validated_at?->format('d M Y g:i A') ?? '-',
                $report->crops->max('estimated_damage_percent') !== null
                    ? (float) $report->crops->max('estimated_damage_percent')
                    : '',
                $validation?->assessed_damage_percent !== null
                    ? (float) $validation->assessed_damage_percent
                    : '',
                Validation::severityLabel($validation?->severity) ?? '-',
                round((float) ($report->crops_sum_damaged_area_hectares ?? 0), 2),
                $report->reported_latitude !== null && $report->reported_longitude !== null
                    ? $report->reported_latitude . ', ' . $report->reported_longitude
                    : '-',
                $validation?->latitude !== null && $validation?->longitude !== null
                    ? $validation->latitude . ', ' . $validation->longitude
                    : '-',
                match ($report->review_state) {
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    default    => 'Pending Review',
                },
                \App\Support\PlantingMatch::label($report->planting_match),
                (string) ($report->review_remarks ?: '-'),
                (string) ($validation?->notes ?: '-'),
            ];
        })->all();

        return RecordListExport::download(
            $request->query('export') === 'pdf' ? 'pdf' : 'xlsx',
            'Validation and Field Inspection Records',
            [
                'Validation Result' => $request->filled('status')
                    ? match ($request->status) {
                        'approved'       => 'Approved',
                        'rejected'       => 'Rejected',
                        'pending_review' => 'Pending Review',
                        default          => null,
                    }
                    : null,
                'Association' => $request->filled('association_id')
                    ? Association::find($request->association_id)?->name
                    : null,
                'Barangay'    => $request->filled('barangay_id')
                    ? Barangay::find($request->barangay_id)?->name
                    : null,
                'Search'      => $request->query('search'),
            ],
            [
                'Report ID', 'Farmer', 'Contact', 'Barangay', 'Association', 'Technician',
                'Inspection Started', 'Inspection Submitted',
                'Farmer Estimate (%)', 'Assessed Damage (%)',
                'Severity', 'Damaged Area (ha)', 'Farmer-Reported Location',
                'Technician-Verified Location', 'Validation Result', 'Crop Planting Match',
                'Review Remarks', 'Inspection Notes',
            ],
            $rows,
            'validation-records'
        );
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

        // Only a report nobody has started inspecting can be given to a
        // technician or moved to a different one. Once an inspection exists
        // (started or submitted) it belongs to the technician who made it,
        // whatever the report's status says.
        if (! $damageReport->can_be_assigned) {
            return back()->withErrors([
                'assigned_technician_id' => 'This report is already being inspected or has been inspected, so it cannot be reassigned.',
            ]);
        }

        $technician = User::find($data['assigned_technician_id']);

        // Assigning is acting on the report, so it no longer counts as new.
        \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'damage_report', $damageReport->id);

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
                'action'       => 'Assigned damage report ' . $damageReport->full_reference
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
                'message'     => 'You have been assigned to inspect damage report ' . $damageReport->full_reference . '.',
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
