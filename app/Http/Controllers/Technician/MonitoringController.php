<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Technician\Concerns\ScopedToBarangays;
use App\Models\CropPlantingRecord;
use App\Models\DamageReport;
use App\Models\Farmer;
use Illuminate\Http\Request;

/**
 * Planting Reports and Damage Reports (sidebar items).
 *
 * Neither page is "my assignments" - Assigned Reports and Validation already
 * cover that. These two are wider on purpose: every crop planting record and
 * every damage report filed in the barangays this technician has been sent
 * to, whoever is actually handling each case. A technician who keeps getting
 * sent to Calibuyo benefits from seeing the whole picture there, not just
 * their own slice of it.
 *
 * Both are read only, mirroring the Association module's Monitoring pages
 * (Association\MonitoringController). A technician watches the barangay;
 * they do not edit or reassign anything from here.
 */
class MonitoringController extends Controller
{
    use ScopedToBarangays;

    /**
     * PLANTING REPORTS
     * Every crop planting record filed by a farmer whose barangay is one
     * this technician has worked in. Planting records do not carry their
     * own barangay, so this scopes on the farmer's home barangay.
     */
    public function planting(Request $request)
    {
        $barangayIds = $this->myBarangayIds();

        $records = CropPlantingRecord::whereIn(
                'farmer_id',
                Farmer::whereIn('barangay_id', $barangayIds)->select('id')
            )
            ->with(['farmer.barangay', 'farmer.association', 'crops.crop'])
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->when($request->query('barangay'), fn ($q, $barangay) => $q
                ->whereHas('farmer', fn ($f) => $f->where('barangay_id', $barangay)))
            ->when($request->query('month'), fn ($q, $month) => $q->whereMonth('date_submitted', $month))
            ->when($request->query('year'), fn ($q, $year) => $q->whereYear('date_submitted', $year))
            ->orderByDesc('date_submitted')
            ->paginate(15)
            ->withQueryString();

        return view('technician.planting.index', [
            'records'   => $records,
            'barangays' => $this->myBarangays(),
            'filters'   => $request->only(['q', 'barangay', 'month', 'year']),
        ]);
    }

    /**
     * DAMAGE REPORTS
     * Every damage report reported in, or filed by a farmer belonging to,
     * one of this technician's barangays - not filtered to their own
     * assignments. The Status column is the point: a technician can see
     * whether a neighbour's report has been picked up yet without asking.
     */
    public function damageReports(Request $request)
    {
        $barangayIds = $this->myBarangayIds();

        $reports = DamageReport::where(function ($query) use ($barangayIds) {
                $query->whereIn('reported_barangay_id', $barangayIds)
                      ->orWhereHas('farmer', fn ($f) => $f->whereIn('barangay_id', $barangayIds));
            })
            ->with([
                'farmer.barangay', 'farmer.association', 'reportedBarangay',
                'crops.crop', 'disasters', 'validation', 'assignedTechnician',
            ])
            ->withCount('photos')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('barangay'), function ($q, $barangay) {
                $q->where(function ($inner) use ($barangay) {
                    $inner->where('reported_barangay_id', $barangay)
                          ->orWhereHas('farmer', fn ($f) => $f->where('barangay_id', $barangay));
                });
            })
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('technician.damage.index', [
            'reports'   => $reports,
            'barangays' => $this->myBarangays(),
            'filters'   => $request->only(['status', 'barangay', 'q']),
            'statuses'  => DamageReport::STATUSES,
        ]);
    }
}
