<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The three list pages in the technician sidebar.
 *
 * They all read from the same place, they just answer different questions:
 *
 *   Assigned Reports    everything the office has ever given me
 *   Validation          what I am in the middle of right now
 *   Inspection History  what I have already submitted, as a record
 *
 * The dashboard shows only the first six rows of the queue. These pages are
 * where a technician goes when they need the whole thing with filters and
 * pagination.
 *
 * Same rule as everywhere else in this module: filtered to the signed in
 * technician, always.
 */
class AssignmentController extends Controller
{
    /**
     * ASSIGNED REPORTS
     * Every report the office has assigned to me, whatever state it is in.
     */
    public function index(Request $request)
    {
        $reports = $this->baseQuery($request)
            // Not started, then in progress, then everything finished.
            ->orderByRaw("FIELD(status, 'assigned', 'under_verification', 'flagged', 'verified', 'approved', 'rejected')")
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('technician.reports.index', [
            'reports'   => $reports,
            'barangays' => $this->myBarangays(),
            'filters'   => $request->only(['status', 'barangay', 'q']),
        ]);
    }

    /**
     * VALIDATION
     * The work in front of me right now: not started, or started and not
     * yet submitted. Nothing finished appears here, on purpose. This page
     * should be empty at the end of a good day.
     */
    public function validation(Request $request)
    {
        $reports = $this->baseQuery($request)
            ->whereIn('status', ['assigned', 'under_verification'])
            // Started ones first: those are half done and worth closing out.
            ->orderByRaw("FIELD(status, 'under_verification', 'assigned')")
            ->orderBy('assigned_at')
            ->paginate(15)
            ->withQueryString();

        return view('technician.validation.index', [
            'reports'   => $reports,
            'barangays' => $this->myBarangays(),
            'filters'   => $request->only(['barangay', 'q']),
        ]);
    }

    /**
     * INSPECTION HISTORY
     * Submitted inspections only. Driven off the validations table rather
     * than the report status, because once the office approves or rejects a
     * report the status moves on, but the inspection still happened and the
     * technician should still be able to find it.
     */
    public function history(Request $request)
    {
        $inspections = Validation::where('technician_id', Auth::id())
            ->whereNotNull('validated_at')
            ->with([
                'damageReport.farmer.barangay',
                'damageReport.reportedBarangay',
                'damageReport.crops.crop',
            ])
            ->withCount('photos')
            ->when($request->query('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('damageReport.farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('validated_at')
            ->paginate(15)
            ->withQueryString();

        return view('technician.history.index', [
            'inspections' => $inspections,
            'severities'  => Validation::SEVERITY_SCALE,
            'filters'     => $request->only(['severity', 'q']),
        ]);
    }

    /* ==================================================================
     | Helpers
     ================================================================== */

    /**
     * The starting point for the two report lists: my assignments, with the
     * relationships the tables need eager loaded so we are not firing one
     * query per row.
     */
    private function baseQuery(Request $request)
    {
        return DamageReport::where('assigned_technician_id', Auth::id())
            ->with([
                'farmer.barangay',
                'farmer.association',
                'reportedBarangay',
                'crops.crop',
                'disasters',
                'validation',
            ])
            ->withCount('photos')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('barangay'), function ($q, $barangay) {
                $q->where(function ($inner) use ($barangay) {
                    $inner->where('reported_barangay_id', $barangay)
                          ->orWhereHas('farmer', fn ($f) => $f->where('barangay_id', $barangay));
                });
            })
            // Search by farmer name or by report number.
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->whereHas('farmer', function ($farmer) use ($term) {
                            $farmer->where('first_name', 'like', "%{$term}%")
                                   ->orWhere('last_name', 'like', "%{$term}%");
                        })
                        // Lets someone paste "DR-0007" or just type "7".
                        ->orWhere('id', (int) preg_replace('/\D/', '', $term));
                });
            });
    }

    /**
     * Only the barangays this technician actually has work in. Listing all
     * 41 barangays of Tanza in the filter would be noise on a phone.
     */
    private function myBarangays()
    {
        return Barangay::whereIn('id', function ($query) {
                $query->select('reported_barangay_id')
                      ->from('damage_reports')
                      ->where('assigned_technician_id', Auth::id())
                      ->whereNotNull('reported_barangay_id');
            })
            ->orderBy('name')
            ->get();
    }
}
