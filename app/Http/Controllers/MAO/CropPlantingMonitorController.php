<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\CropPlantingRecord;
use App\Models\CropPlantingRecordCrop;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only monitoring of what farmers have planted.
 *
 * One row per planted crop, since a single submission can carry several crops.
 * Submitting a planting record is also what keeps a farmer counted as active.
 */
class CropPlantingMonitorController extends Controller
{
    public function index(Request $request)
    {
        // Section 22's 3-month rule: keep it current before the
        // ?activity_status= filter below runs against it.
        Farmer::sweepInactive();

        $plantings = CropPlantingRecordCrop::query()
            ->with([
                'crop',
                'plantingRecord.farmer.user',
                'plantingRecord.farmer.association',
                'plantingRecord.farmer.barangay',
            ])
            ->when($request->filled('crop_id'),
                fn ($query) => $query->where('crop_id', $request->crop_id))
            ->whereHas('plantingRecord.farmer', function ($farmer) use ($request) {
                $farmer
                    ->when($request->filled('association_id'),
                        fn ($query) => $query->where('association_id', $request->association_id))
                    ->when($request->filled('barangay_id'),
                        fn ($query) => $query->where('barangay_id', $request->barangay_id))
                    ->when($request->filled('activity_status'),
                        fn ($query) => $query->where('activity_status', $request->activity_status))
                    ->when($request->filled('search'), function ($query) use ($request) {
                        $term = '%' . $request->search . '%';

                        $query->where(fn ($sub) => $sub
                            ->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                    });
            })
            ->orderByDesc('date_planted')
            ->paginate(15)
            ->withQueryString();

        $monthStart = Carbon::now()->startOfMonth();

        $summary = [
            'records'       => CropPlantingRecord::count(),
            'this_month'    => CropPlantingRecord::where('date_submitted', '>=', $monthStart)->count(),
            'farmers'       => CropPlantingRecord::distinct('farmer_id')->count('farmer_id'),
            'area_planted'  => (float) DB::table('crop_planting_record_crops')->sum('area_hectares'),
        ];

        return view('mao.crop-planting.index', [
            'plantings'    => $plantings,
            'summary'      => $summary,
            'associations' => Association::orderBy('name')->get(),
            'barangays'    => Barangay::orderBy('name')->get(),
            'crops'        => Crop::orderBy('name')->get(),
        ]);
    }

    public function show(CropPlantingRecord $plantingRecord)
    {
        $plantingRecord->load([
            'farmer.user',
            'farmer.association',
            'farmer.barangay',
            'crops.crop',
            'photos',
        ]);

        return view('mao.crop-planting.show', compact('plantingRecord'));
    }
}
