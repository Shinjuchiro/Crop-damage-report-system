<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\Association;
use App\Models\Crop;
use App\Models\CropPlantingRecord;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * One place to see everything the office has taken out of the working
 * system - proposal section 72 ("Archive / Deactivate instead of permanent
 * deletion whenever appropriate") and section 91.10 ("keep a record of it").
 *
 * Two tabs, not one:
 *
 *   Archived - a record someone archived (still fully live everywhere else,
 *              just off the active catalogues) - can be restored.
 *   Deleted  - a record someone chose "Delete Permanently" on. Nothing was
 *              actually removed from the database: every field, and who
 *              deleted it and when, is kept (see
 *              app/Models/Concerns/SoftDeletable.php). Read-only - deleting
 *              is meant to be final, so there is no restore action here.
 *
 * Each tab reads whichever mechanism that record type already uses:
 *
 *   Farmers                users.status = 'inactive'   (MembershipApplicationController)
 *   Associations, Crops,   archived_at is not null      (Archivable trait)
 *   Disasters
 *   Technicians / Officers users.status = 'inactive'   (UserManagementController)
 *   Assistance             status = 'inactive'         (AssistanceController)
 *   Alerts                 status = 'archived'         (NotificationBroadcastController)
 *
 * ...and every one of those seven also has deleted_at/deleted_by
 * (SoftDeletable), checked here independently of the mechanism above.
 *
 * Restoring, archiving and deleting are all done by the owning controller
 * for each type, not here - this page only lists and links to those actions.
 *
 * Sept 2026 list+detail redesign: the table's per-row action buttons moved
 * into a right-hand detail panel. A row's "View" link only adds
 * ?selected=<id> to the current query string (type/view/search/page all
 * carry over), so findSelected() re-runs the exact same scoping as
 * baseQuery() and just narrows to one row instead of paginating.
 */
class ArchiveController extends Controller
{
    public const TYPES = [
        'farmers'       => 'Farmers',
        'associations'  => "Farmers' Associations",
        'users'         => 'Technicians & Officers',
        'crops'         => 'Crops',
        'disasters'     => 'Disaster Events',
        'assistance'    => 'Assistance',
        'alerts'        => 'Alerts',
        'crop_planting' => 'Crop Planting Records',
        'damage_reports' => 'Damage Reports',
    ];

    public function index(Request $request)
    {
        $type = $request->query('type', 'farmers');

        if (! array_key_exists($type, self::TYPES)) {
            $type = 'farmers';
        }

        $view = $request->query('view', 'archived');

        if (! in_array($view, ['archived', 'deleted'], true)) {
            $view = 'archived';
        }

        $records = $view === 'deleted'
            ? $this->deletedRecords($type, $request)
            : $this->archivedRecords($type, $request);

        $counts = $view === 'deleted' ? $this->deletedCounts() : $this->archivedCounts();

        $selectedId = $request->query('selected');
        $selected = $selectedId ? $this->findSelected($type, $view, (int) $selectedId) : null;

        return view('mao.archive.index', [
            'type'     => $type,
            'types'    => self::TYPES,
            'view'     => $view,
            'records'  => $records,
            'counts'   => $counts,
            'selected' => $selected,
        ]);
    }

    /**
     * The un-paginated, un-searched query for a type + tab. Both the list
     * (archivedRecords/deletedRecords, which add search/order/paginate) and
     * the single-row lookup for the detail panel (findSelected) start here,
     * so a record can never appear selected in the panel without also being
     * a row the list itself would show.
     */
    private function baseQuery(string $type, string $view): Builder
    {
        if ($view === 'deleted') {
            return match ($type) {
                'farmers'      => Farmer::onlyDeleted()->with(['user', 'association', 'barangay', 'deletedBy']),
                'associations' => Association::onlyDeleted()->with(['barangay', 'deletedBy']),
                'users'        => User::whereIn('role', ['technician', 'association'])->onlyDeleted()->with(['associationOfficer.association', 'deletedBy']),
                'crops'        => Crop::onlyDeleted()->with('deletedBy'),
                'disasters'    => Disaster::onlyDeleted()->with('deletedBy'),
                'assistance'   => Assistance::onlyDeleted()->with(['disaster', 'crop', 'deletedBy']),
                'alerts'       => NotificationBroadcast::onlyDeleted()->with(['createdBy', 'deletedBy']),
                'crop_planting' => CropPlantingRecord::onlyDeleted()->with(['farmer.user', 'farmer.association', 'deletedBy']),
                'damage_reports' => DamageReport::onlyDeleted()->with(['farmer.user', 'farmer.association', 'deletedBy']),
            };
        }

        return match ($type) {
            'farmers' => Farmer::with(['user', 'association', 'barangay'])
                ->notDeleted()
                ->whereHas('user', fn ($q) => $q->where('status', 'inactive')),

            'associations' => Association::onlyArchived()->with(['barangay', 'archivedBy']),

            'users' => User::whereIn('role', ['technician', 'association'])
                ->notDeleted()
                ->where('status', 'inactive')
                ->with('associationOfficer.association'),

            'crops' => Crop::onlyArchived()->with('archivedBy'),

            'disasters' => Disaster::onlyArchived()->with('archivedBy'),

            'assistance' => Assistance::where('status', 'inactive')
                ->notDeleted()
                ->with(['disaster', 'crop']),

            'alerts' => NotificationBroadcast::where('status', 'archived')
                ->notDeleted()
                ->with('createdBy'),

            'crop_planting' => CropPlantingRecord::onlyArchived()->with(['farmer.user', 'farmer.association']),

            'damage_reports' => DamageReport::onlyArchived()->with(['farmer.user', 'farmer.association']),
        };
    }

    /** The extra withCount() every list/detail view needs, per type. */
    private function withCounts(string $type, Builder $query): Builder
    {
        return match ($type) {
            'associations'  => $query->withCount(['farmers', 'officers', 'assistanceAllocations']),
            'crops'         => $query->withCount(['mainCrops', 'plantingRecordCrops', 'damageReportCrops']),
            'disasters'     => $query->withCount('damageReports'),
            'assistance'    => $query->withCount('allocations'),
            'alerts'        => $query->withCount('notifications'),
            'crop_planting' => $query->withCount('crops'),
            'damage_reports' => $query->withCount('crops'),
            default         => $query,
        };
    }

    private function applySearch(Builder $query, string $type, string $term): Builder
    {
        $like = '%' . $term . '%';

        $searchByFarmerName = fn (Builder $q) => $q->whereHas('farmer', fn ($f) => $f
            ->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like));

        return match ($type) {
            'farmers'        => $query->where(fn ($sub) => $sub->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)),
            'users'          => $query->where(fn ($sub) => $sub->where('full_name', 'like', $like)->orWhere('username', 'like', $like)),
            'alerts'         => $query->where('title', 'like', $like),
            'crop_planting', 'damage_reports' => $searchByFarmerName($query),
            default          => $query->where('name', 'like', $like),
        };
    }

    private function orderFor(string $type, string $view): array
    {
        if ($view === 'deleted') {
            return ['deleted_at', 'desc'];
        }

        return match ($type) {
            'farmers'    => ['last_name', 'asc'],
            'users'      => ['full_name', 'asc'],
            'assistance' => ['name', 'asc'],
            default      => ['archived_at', 'desc'],
        };
    }

    private function archivedRecords(string $type, Request $request)
    {
        $query = $this->withCounts($type, $this->baseQuery($type, 'archived'))
            ->when($request->filled('search'), fn ($q) => $this->applySearch($q, $type, $request->search));

        [$column, $direction] = $this->orderFor($type, 'archived');

        return $query->orderBy($column, $direction)->paginate(15)->withQueryString();
    }

    private function deletedRecords(string $type, Request $request)
    {
        $query = $this->withCounts($type, $this->baseQuery($type, 'deleted'))
            ->when($request->filled('search'), fn ($q) => $this->applySearch($q, $type, $request->search));

        [$column, $direction] = $this->orderFor($type, 'deleted');

        return $query->orderBy($column, $direction)->paginate(15)->withQueryString();
    }

    /**
     * One record for the detail panel, scoped exactly like the list it came
     * from - a stale or tampered-with ?selected=id for the wrong type/tab
     * simply finds nothing (null), which the view treats as "nothing
     * selected" rather than leaking a record from another tab.
     */
    private function findSelected(string $type, string $view, int $id)
    {
        return $this->withCounts($type, $this->baseQuery($type, $view))->find($id);
    }

    private function archivedCounts(): array
    {
        return [
            'farmers'      => Farmer::notDeleted()->whereHas('user', fn ($q) => $q->where('status', 'inactive'))->count(),
            'associations' => Association::onlyArchived()->count(),
            'users'        => User::whereIn('role', ['technician', 'association'])->notDeleted()->where('status', 'inactive')->count(),
            'crops'        => Crop::onlyArchived()->count(),
            'disasters'    => Disaster::onlyArchived()->count(),
            'assistance'   => Assistance::where('status', 'inactive')->notDeleted()->count(),
            'alerts'       => NotificationBroadcast::where('status', 'archived')->notDeleted()->count(),
            'crop_planting'  => CropPlantingRecord::onlyArchived()->count(),
            'damage_reports' => DamageReport::onlyArchived()->count(),
        ];
    }

    private function deletedCounts(): array
    {
        return [
            'farmers'      => Farmer::onlyDeleted()->count(),
            'associations' => Association::onlyDeleted()->count(),
            'users'        => User::whereIn('role', ['technician', 'association'])->onlyDeleted()->count(),
            'crops'        => Crop::onlyDeleted()->count(),
            'disasters'    => Disaster::onlyDeleted()->count(),
            'assistance'   => Assistance::onlyDeleted()->count(),
            'alerts'       => NotificationBroadcast::onlyDeleted()->count(),
            'crop_planting'  => CropPlantingRecord::onlyDeleted()->count(),
            'damage_reports' => DamageReport::onlyDeleted()->count(),
        ];
    }
}
