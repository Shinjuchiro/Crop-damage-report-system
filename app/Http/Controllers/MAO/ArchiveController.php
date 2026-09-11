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
 * One place to see everything the office has archived, and to bring any of
 * it back. Proposal section 72: "Archive / Deactivate instead of permanent
 * deletion whenever appropriate."
 *
 * Each tab reads whichever mechanism that record type already uses to mean
 * "archived" - there is no single shared column across all of them:
 *
 *   Farmers                users.status = 'inactive'   (MembershipApplicationController)
 *   Associations, Crops,   archived_at is not null     (Archivable trait)
 *   Disasters
 *   Technicians / Officers users.status = 'inactive'   (UserManagementController)
 *   Assistance             status = 'inactive'         (AssistanceController)
 *   Alerts                 status = 'archived'         (NotificationBroadcastController)
 *
 * Restoring is therefore also done by the owning controller for each type,
 * not here - this page only lists and links to those actions.
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

        $records = match ($type) {
            'farmers' => Farmer::with(['user', 'association', 'barangay'])
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
                ->with(['disaster', 'crop'])
                ->withCount('allocations')
                ->when($request->filled('search'),
                    fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),

            'alerts' => NotificationBroadcast::where('status', 'archived')
                ->with('createdBy')
                ->withCount('notifications')
                ->when($request->filled('search'),
                    fn ($q) => $q->where('title', 'like', '%' . $request->search . '%'))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        };

        $counts = [
            'farmers'      => Farmer::whereHas('user', fn ($q) => $q->where('status', 'inactive'))->count(),
            'associations' => Association::onlyArchived()->count(),
            'users'        => User::whereIn('role', ['technician', 'association'])->where('status', 'inactive')->count(),
            'crops'        => Crop::onlyArchived()->count(),
            'disasters'    => Disaster::onlyArchived()->count(),
            'assistance'   => Assistance::where('status', 'inactive')->count(),
            'alerts'       => NotificationBroadcast::where('status', 'archived')->count(),
        ];

        return view('mao.archive.index', [
            'type'    => $type,
            'types'   => self::TYPES,
            'records' => $records,
            'counts'  => $counts,
        ]);
    }
}
