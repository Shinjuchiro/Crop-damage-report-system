<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\CropPlantingRecord;
use App\Models\CropPlantingRecordCrop;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring of what farmers have planted, plus (Sept 2026) the Edit and
 * Archive pair every other MAO list already has. Editing here is meant for
 * correcting a farmer's mistake (wrong crop, date or area) - it never
 * touches which farmer the record belongs to, and it never replays the
 * Active/Inactive history: Farmer::sweepInactive()/recordQualifyingActivity()
 * only ever look at current state going forward, so archiving or editing a
 * past record leaves a farmer's already-computed status exactly as it was.
 *
 * One row per planted crop, since a single submission can carry several crops.
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
            ->whereHas('plantingRecord', fn ($q) => $q->notDeleted()->whereNull('archived_at'))
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
            'records'       => CropPlantingRecord::notDeleted()->whereNull('archived_at')->count(),
            'this_month'    => CropPlantingRecord::notDeleted()->whereNull('archived_at')
                ->where('date_submitted', '>=', $monthStart)->count(),
            'farmers'       => CropPlantingRecord::notDeleted()->whereNull('archived_at')
                ->distinct('farmer_id')->count('farmer_id'),
            'area_planted'  => (float) DB::table('crop_planting_record_crops')
                ->join('crop_planting_records', 'crop_planting_records.id', '=', 'crop_planting_record_crops.crop_planting_record_id')
                ->whereNull('crop_planting_records.archived_at')
                ->whereNull('crop_planting_records.deleted_at')
                ->sum('crop_planting_record_crops.area_hectares'),
        ];

        // List + detail panel (Sept 2026): "View" loads the record inline
        // in the right-hand panel via ?selected=<crop_planting_record_id>,
        // instead of navigating to the separate show page.
        $selected = $request->filled('selected')
            ? CropPlantingRecord::query()->with($this->detailRelations())->find($request->selected)
            : null;

        return view('mao.crop-planting.index', [
            'plantings'    => $plantings,
            'selected'     => $selected,
            'summary'      => $summary,
            'associations' => Association::orderBy('name')->get(),
            'barangays'    => Barangay::orderBy('name')->get(),
            'crops'        => Crop::orderBy('name')->get(),
        ]);
    }

    public function show(CropPlantingRecord $plantingRecord)
    {
        $plantingRecord->load($this->detailRelations());

        return view('mao.crop-planting.show', compact('plantingRecord'));
    }

    private function detailRelations(): array
    {
        return [
            'farmer.user',
            'farmer.association',
            'farmer.barangay',
            'crops.crop',
            'photos',
            'archivedBy',
        ];
    }

    /*
     * edit(), update() and their validated() helper were removed in Sept 2026,
     * together with the routes and the edit view. MAO must not rewrite a
     * farmer-submitted planting record: it can view, archive or restore one,
     * the same rule already applied to every other farmer-submitted record.
     * The methods outlived their routes and were left reachable by a stale
     * link on the detail page, so they are gone rather than merely unrouted.
     */

    /**
     * Take the record out of the active monitoring list without losing it -
     * it stays fully intact for audit purposes and never changes the
     * farmer's already-computed Active/Inactive history. See
     * app/Models/Concerns/Archivable.php.
     */
    public function archive(CropPlantingRecord $plantingRecord)
    {
        $plantingRecord->archive(Auth::id());

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Archived crop planting record #' . $plantingRecord->id,
            'target_table' => 'crop_planting_records',
            'target_id'    => $plantingRecord->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Planting record archived.');
    }

    public function restore(CropPlantingRecord $plantingRecord)
    {
        if ($plantingRecord->is_deleted) {
            return back()->withErrors(['plantingRecord' => 'This record was permanently deleted and can no longer be restored.']);
        }

        $plantingRecord->unarchive();

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Restored crop planting record #' . $plantingRecord->id,
            'target_table' => 'crop_planting_records',
            'target_id'    => $plantingRecord->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Planting record restored.');
    }

    public function destroy(CropPlantingRecord $plantingRecord)
    {
        $plantingRecord->markDeleted(Auth::id());

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Deleted crop planting record #' . $plantingRecord->id,
            'target_table' => 'crop_planting_records',
            'target_id'    => $plantingRecord->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Planting record deleted. Its data is kept for audit purposes.');
    }
}
