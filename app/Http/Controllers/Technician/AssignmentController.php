<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\CropPlantingRecord;
use App\Models\CropPlantingRecordCrop;
use App\Models\DamageReport;
use App\Models\Validation;
use App\Services\PlantingComparison;
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
        /* One page is a handful of FARMERS, each with all of their reports
           (the same by-farmer list the MAO's Crop Management Report uses).
           Farmers with work still open come first, then whoever reported
           most recently. Inside a farmer: not started, then in progress,
           then everything finished. */
        $statusOrder = "FIELD(damage_reports.status, 'assigned', 'under_verification', 'flagged', 'verified', 'approved', 'rejected')";

        $reports = \App\Support\FarmerGroups::paginate(
            $this->baseQuery($request),
            fn ($q) => $q->select('damage_reports.farmer_id')
                ->groupBy('damage_reports.farmer_id')
                ->orderByRaw("MIN({$statusOrder})")
                ->orderByRaw('MAX(damage_reports.created_at) DESC')
                ->orderBy('damage_reports.farmer_id'),
            fn ($q, $ids) => $q->whereIn('damage_reports.farmer_id', $ids)
                ->orderByRaw($statusOrder)
                ->latest('damage_reports.created_at')
                ->orderByDesc('damage_reports.id'),
            fn ($report) => $report->farmer_id
        );

        // Section 62's cross-check: a match signal only, never an eligible/
        // ineligible label - a report with no matching planting record is
        // still fully available to inspect.
        // One query for the whole page, and the same rule the office uses.
        \App\Support\PlantingMatch::annotate($reports->getCollection());
        $reports->getCollection()->each(
            fn ($report) => $report->hasPlantingMatch = $report->planting_match === \App\Support\PlantingMatch::MATCHED
        );

        return view('technician.reports.index', [
            'reports'   => $reports,
            'barangays' => $this->myBarangays(),
            'filters'   => $request->only(['status', 'barangay', 'q', 'association_id']),
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
    /**
     * ONE COMPLETED INSPECTION, END TO END
     *
     * Inspection History answers "what did I do", and a row on its own
     * does not answer it properly. This page puts the three stages in the
     * order they happened: what the farmer recorded planting, what they
     * then reported damaged, and what this technician found when they went
     * out and looked.
     *
     * The same planted-against-damaged comparison the technician saw on
     * the Validation briefing is rebuilt here, so the record shows what
     * they were working from at the time, not just the conclusion.
     *
     * Only this technician's own inspections. A validation belonging to
     * somebody else is a 403, not an empty page, because the id being
     * guessable in the URL is exactly the case this guards.
     */
    public function historyShow(Validation $validation)
    {
        abort_unless($validation->technician_id === Auth::id(), 403);
        abort_if($validation->validated_at === null, 404);

        $validation->load([
            'photos',
            'technician',
            'damageReport.farmer.barangay',
            'damageReport.farmer.association',
            'damageReport.reportedBarangay',
            'damageReport.crops.crop',
            'damageReport.disasters',
            'damageReport.photos',
        ]);

        $report = $validation->damageReport;

        /*
         | The farmer's planting records that could relate to this report:
         | filed before the report, not archived. Shown in full as the
         | first stage of the chain, separately from the crop by crop
         | comparison underneath it.
         */
        $planting = CropPlantingRecord::where('farmer_id', $report->farmer_id)
            ->whereNull('archived_at')
            ->whereDate('date_submitted', '<=', $report->created_at)
            ->with('crops.crop')
            ->orderByDesc('date_submitted')
            ->get();

        return view('technician.history.show', [
            'validation' => $validation,
            'report'     => $report,
            'planting'   => $planting,
            'comparison' => PlantingComparison::build($report),
        ]);
    }

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
            // From the map's "View Details": only this association's members.
            ->when($request->query('association_id'), fn ($q, $assoc) => $q
                ->whereHas('farmer', fn ($f) => $f->where('association_id', $assoc)))
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
