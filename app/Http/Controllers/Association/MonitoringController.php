<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\CropPlantingRecord;
use App\Models\DamageReport;
use App\Models\Farmer;
use Illuminate\Http\Request;

/**
 * Monitoring  (proposal section 60 and 61)
 *
 * Two lists across the whole membership rather than one member at a time:
 * what the members planted, and what damage they reported.
 *
 * Both are read only. The association watches its members' activity; it does
 * not file or edit anything on their behalf. The damage report list in
 * particular is how an officer answers "has anybody inspected Aling Rosa's
 * farm yet" without phoning the office.
 */
class MonitoringController extends Controller
{
    use ResolvesAssociation;

    /**
     * Planting activities filed by members.
     */
    public function planting(Request $request)
    {
        $association = $this->association();

        $records = CropPlantingRecord::whereIn(
                'farmer_id',
                Farmer::where('association_id', $association->id)->select('id')
            )
            ->with(['farmer.barangay', 'crops.crop'])
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            // Month filter, so an officer can answer "what went in this season".
            ->when($request->query('month'), fn ($q, $month) => $q->whereMonth('date_submitted', $month))
            ->when($request->query('year'), fn ($q, $year) => $q->whereYear('date_submitted', $year))
            ->orderByDesc('date_submitted')
            ->paginate(15)
            ->withQueryString();

        return view('association.planting.index', [
            'association' => $association,
            'records'     => $records,
            'filters'     => $request->only(['q', 'month', 'year']),
        ]);
    }

    /**
     * Damage reports filed by members, with where each one has got to.
     */
    public function damageReports(Request $request)
    {
        $association = $this->association();

        $reports = DamageReport::whereIn(
                'farmer_id',
                Farmer::where('association_id', $association->id)->select('id')
            )
            ->with([
                'farmer.barangay', 'reportedBarangay',
                'crops.crop', 'disasters', 'validation', 'assignedTechnician',
            ])
            ->withCount('photos')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('cause'), fn ($q, $cause) => $q->where('damage_cause', $cause))
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('association.reports.index', [
            'association' => $association,
            'reports'     => $reports,
            'filters'     => $request->only(['status', 'cause', 'q']),
            'causes'      => DamageReport::CAUSES,
            'statuses'    => DamageReport::STATUSES,
        ]);
    }
}
