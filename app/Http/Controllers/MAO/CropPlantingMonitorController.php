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
use App\Services\RecordListExport;
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
        // Section 22's inactivity rule: keep it current before the
        // ?activity_status= filter below runs against it.
        Farmer::sweepInactive();

        /* By farmer, or flat (revision section 10). Ordering only: the same
           rows come back either way. Grouped puts a farmer's crops together
           so the page can print the name once above them; flat is newest
           first, which is what the office wants when the question is "what
           was filed this week".

           The farmer is two tables away from this row - a crop belongs to a
           planting record, which belongs to a farmer - so anything about the
           farmer is a correlated subquery rather than a column.

           Grouped used to be alphabetical, which buried this morning's
           record behind every member whose surname begins with A. The
           blocks are now ordered by who filed last. $farmerLastFiled returns
           one value per farmer, so every row of theirs sorts to the same
           place and their block stays in one piece.

           $farmerOfRow is the tie-breaker, and it is not optional here:
           date_submitted is a date, not a timestamp, so several farmers
           filing on the same day is the normal case rather than a freak
           one. Without something that is the same for all of one farmer's
           rows and different between farmers, those rows would interleave
           and every block on the page would come apart. */
        $view = $request->query('view') === 'flat' ? 'flat' : 'grouped';

        $farmerLastFiled = DB::table('crop_planting_records as owner')
            ->selectRaw('MAX(mine.date_submitted)')
            ->join('crop_planting_records as mine', 'mine.farmer_id', '=', 'owner.farmer_id')
            ->whereColumn('owner.id', 'crop_planting_record_crops.crop_planting_record_id')
            ->whereNull('mine.deleted_at')
            ->whereNull('mine.archived_at');

        $farmerOfRow = DB::table('crop_planting_records as owner_of_row')
            ->select('owner_of_row.farmer_id')
            ->whereColumn('owner_of_row.id', 'crop_planting_record_crops.crop_planting_record_id')
            ->limit(1);

        // Export Report. Taken before the page is built, from the same
        // filtered query, so the file and the screen can never disagree.
        if ($request->filled('export')) {
            return $this->export($request);
        }

        $limited = \App\Support\LatestPerFarmer::applies($request, $view);

        $plantings = $this->filtered($request)
            ->when($limited, fn ($query) => \App\Support\LatestPerFarmer::plantings($query))
            ->when($view === 'grouped', fn ($query) => $query
                ->orderByDesc($farmerLastFiled)
                ->orderBy($farmerOfRow))
            ->orderByDesc('date_planted')
            ->orderByDesc('crop_planting_record_crops.id')
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

        \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'planting', $selected?->id);

        return view('mao.crop-planting.index', [
            'plantings'    => $plantings,
            'farmerTotals' => $limited ? \App\Support\LatestPerFarmer::plantingTotals() : collect(),
            'view'         => $view,
            'selected'     => $selected,
            'history'      => $selected ? CropPlantingRecord::query()->notDeleted()->whereNull('archived_at')
                ->where('farmer_id', $selected->farmer_id)->with('crops.crop')
                ->orderByDesc('date_submitted')->orderByDesc('id')->get() : collect(),
            'summary'      => $summary,
            'associations' => Association::orderBy('name')->get(),
            'barangays'    => Barangay::orderBy('name')->get(),
            'crops'        => Crop::inDisplayOrder()->get(),
        ]);
    }

    /**
     * The filtered list, without ordering or pagination.
     *
     * Pulled out of index() when Export Report arrived: the export has to
     * cover exactly what the page is showing, and the only way to be sure
     * of that is for both to be built from one query rather than from two
     * that happen to look alike today.
     */
    private function filtered(Request $request)
    {
        return CropPlantingRecordCrop::query()
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
            });
    }

    /**
     * Export Report: the Crop Planted list as currently filtered.
     *
     * One row per planted crop, which is what this page lists - a farmer who
     * recorded rice and corn in one submission is two rows here, and the
     * area column totals correctly because of it.
     */
    private function export(Request $request)
    {
        $rows = $this->filtered($request)
            ->orderByDesc('date_planted')
            ->get()
            ->map(function ($planting) {
                $farmer = $planting->plantingRecord?->farmer;

                return [
                    $farmer ? 'FARM-' . str_pad((string) $farmer->id, 3, '0', STR_PAD_LEFT) : '-',
                    $farmer?->full_name ?? 'Unknown',
                    $farmer?->user?->phone_number ?: '-',
                    $farmer?->barangay?->name ?? '-',
                    $farmer?->association?->name ?? '-',
                    $planting->crop_specify ?: ($planting->crop?->name ?? '-'),
                    $planting->date_planted?->format('d M Y') ?? '-',
                    round((float) $planting->area_hectares, 2),
                    $planting->plantingRecord?->date_submitted?->format('d M Y') ?? '-',
                    'REC-' . str_pad((string) $planting->crop_planting_record_id, 4, '0', STR_PAD_LEFT),
                    ucfirst((string) ($farmer?->activity_status ?? '-')),
                ];
            })
            ->all();

        return RecordListExport::download(
            $request->query('export') === 'pdf' ? 'pdf' : 'xlsx',
            'Crop Planting Records',
            [
                'Crop'        => $request->filled('crop_id')
                    ? Crop::find($request->crop_id)?->name
                    : null,
                'Association' => $request->filled('association_id')
                    ? Association::find($request->association_id)?->name
                    : null,
                'Barangay'    => $request->filled('barangay_id')
                    ? Barangay::find($request->barangay_id)?->name
                    : null,
                'Farmer status' => $request->filled('activity_status')
                    ? ucfirst((string) $request->activity_status)
                    : null,
                'Search'      => $request->query('search'),
            ],
            [
                'Farmer ID', 'Farmer', 'Contact', 'Barangay', 'Association', 'Crop',
                'Date Planted', 'Area (ha)', 'Date Submitted', 'Record No.', 'Farmer Status',
            ],
            $rows,
            'crop-planting-records'
        );
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
}
