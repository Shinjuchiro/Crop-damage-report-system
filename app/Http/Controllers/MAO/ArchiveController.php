<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\Association;
use App\Models\Crop;
use App\Models\Disaster;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Models\User;
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
 */
class ArchiveController extends Controller
{
    public const TYPES = [
        'farmers'      => 'Farmers',
        'associations' => "Farmers' Associations",
        'users'        => 'Technicians & Officers',
        'crops'        => 'Crops',
        'disasters'    => 'Disaster Events',
        'assistance'   => 'Assistance',
        'alerts'       => 'Alerts',
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

        return view('mao.archive.index', [
            'type'    => $type,
            'types'   => self::TYPES,
            'view'    => $view,
            'records' => $records,
            'counts'  => $counts,
        ]);
    }

    /**
     * A record that is currently archived (never a deleted one - see
     * Archivable::scopeOnlyArchived(), which already excludes deleted rows
     * for Associations/Crops/Disasters; Farmers/Users/Assistance/Alerts are
     * excluded explicitly below since they use a status column instead).
     */
    private function archivedRecords(string $type, Request $request)
    {
        return match ($type) {
            'farmers' => Farmer::with(['user', 'association', 'barangay'])
                ->notDeleted()
                ->whereHas('user', fn ($q) => $q->where('status', 'inactive'))
                ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                    $term = '%' . $request->search . '%';
                    $sub->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term);
                }))
                ->orderBy('last_name')
                ->paginate(15)
                ->withQueryString(),

            'associations' => Association::onlyArchived()
                ->with(['barangay', 'archivedBy'])
                ->withCount(['farmers', 'officers', 'assistanceAllocations'])
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderByDesc('archived_at')
                ->paginate(15)
                ->withQueryString(),

            'users' => User::whereIn('role', ['technician', 'association'])
                ->notDeleted()
                ->where('status', 'inactive')
                ->with('associationOfficer.association')
                ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                    $term = '%' . $request->search . '%';
                    $sub->where('full_name', 'like', $term)->orWhere('username', 'like', $term);
                }))
                ->orderBy('full_name')
                ->paginate(15)
                ->withQueryString(),

            'crops' => Crop::onlyArchived()
                ->with('archivedBy')
                ->withCount(['mainCrops', 'plantingRecordCrops', 'damageReportCrops'])
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderByDesc('archived_at')
                ->paginate(15)
                ->withQueryString(),

            'disasters' => Disaster::onlyArchived()
                ->with('archivedBy')
                ->withCount('damageReports')
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderByDesc('archived_at')
                ->paginate(15)
                ->withQueryString(),

            'assistance' => Assistance::where('status', 'inactive')
                ->notDeleted()
                ->with(['disaster', 'crop'])
                ->withCount('allocations')
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),

            'alerts' => NotificationBroadcast::where('status', 'archived')
                ->notDeleted()
                ->with('createdBy')
                ->withCount('notifications')
                ->when($request->filled('search'),
                    fn ($q) => $q->where('title', 'like', '%' . $request->search . '%'))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        };
    }

    /**
     * A record someone permanently deleted. Every field is intact - this
     * reads the exact same table, filtered to deleted_at IS NOT NULL, with no
     * regard for the type's own archived/status column (a record can be
     * deleted straight from its own management page without ever having
     * been archived first, for Crops/Disasters/Associations/Assistance).
     */
    private function deletedRecords(string $type, Request $request)
    {
        return match ($type) {
            'farmers' => Farmer::onlyDeleted()
                ->with(['user', 'association', 'barangay', 'deletedBy'])
                ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                    $term = '%' . $request->search . '%';
                    $sub->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term);
                }))
                ->orderByDesc('deleted_at')
                ->paginate(15)
                ->withQueryString(),

            'associations' => Association::onlyDeleted()
                ->with(['barangay', 'deletedBy'])
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderByDesc('deleted_at')
                ->paginate(15)
                ->withQueryString(),

            'users' => User::whereIn('role', ['technician', 'association'])
                ->onlyDeleted()
                ->with(['associationOfficer.association', 'deletedBy'])
                ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                    $term = '%' . $request->search . '%';
                    $sub->where('full_name', 'like', $term)->orWhere('username', 'like', $term);
                }))
                ->orderByDesc('deleted_at')
                ->paginate(15)
                ->withQueryString(),

            'crops' => Crop::onlyDeleted()
                ->with('deletedBy')
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderByDesc('deleted_at')
                ->paginate(15)
                ->withQueryString(),

            'disasters' => Disaster::onlyDeleted()
                ->with('deletedBy')
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderByDesc('deleted_at')
                ->paginate(15)
                ->withQueryString(),

            'assistance' => Assistance::onlyDeleted()
                ->with(['disaster', 'crop', 'deletedBy'])
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderByDesc('deleted_at')
                ->paginate(15)
                ->withQueryString(),

            'alerts' => NotificationBroadcast::onlyDeleted()
                ->with(['createdBy', 'deletedBy'])
                ->when($request->filled('search'),
                    fn ($q) => $q->where('title', 'like', '%' . $request->search . '%'))
                ->orderByDesc('deleted_at')
                ->paginate(15)
                ->withQueryString(),
        };
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
        ];
    }
}
