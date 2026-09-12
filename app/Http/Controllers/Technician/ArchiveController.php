<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Archive (sidebar item).
 *
 * This is NOT the Archivable trait used elsewhere in the system - nothing
 * here is soft deleted, and there is no restore action. It is a
 * technician's own record of damage reports that used to be on their desk
 * and no longer are, kept for exactly two reasons:
 *
 *   1. the report was reassigned to a different technician after this one
 *      had already inspected it, or
 *   2. the MAO rejected the report after this technician verified it.
 *
 * Both cases start from the same fact: this technician's name is on the
 * validation. If it is not, the report was never theirs to lose, and it
 * has no business appearing here.
 */
class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $technicianId = Auth::id();

        $reports = DamageReport::whereHas('validation', fn ($v) => $v->where('technician_id', $technicianId))
            ->where(function ($query) use ($technicianId) {
                $query->where('assigned_technician_id', '!=', $technicianId)
                      ->orWhereNull('assigned_technician_id')
                      ->orWhere('status', 'rejected');
            })
            ->with([
                'farmer.barangay', 'farmer.association', 'reportedBarangay',
                'crops.crop', 'validation', 'assignedTechnician',
            ])
            ->when($request->query('reason') === 'reassigned', fn ($q) => $q
                ->where(function ($inner) use ($technicianId) {
                    $inner->where('assigned_technician_id', '!=', $technicianId)
                          ->orWhereNull('assigned_technician_id');
                }))
            ->when($request->query('reason') === 'rejected', fn ($q) => $q->where('status', 'rejected'))
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('technician.archive.index', [
            'reports' => $reports,
            'filters' => $request->only(['reason', 'q']),
        ]);
    }
}
