<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\Barangay;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Read-only directory of every farmer in the system.
 *
 * Approving and rejecting registrations stays in MembershipApplicationController;
 * this is where the MAO looks up an existing farmer and everything attached to them.
 */
class FarmerDirectoryController extends Controller
{
    public function index(Request $request)
    {
        // Section 22's inactivity rule: keep every farmer's Active/Inactive
        // status current before it is displayed, filtered, or counted below
        // - see Farmer::sweepInactive() for why this has to run here.
        Farmer::sweepInactive();

        $farmers = Farmer::query()
            ->notDeleted()
            ->with(['user', 'barangay', 'association'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';

                $query->where(function ($sub) use ($term) {
                    $sub->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('username', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            })
            ->when($request->filled('barangay_id'),
                fn ($query) => $query->where('barangay_id', $request->barangay_id))
            ->when($request->filled('association_id'),
                fn ($query) => $query->where('association_id', $request->association_id))
            ->when($request->filled('ownership_type'),
                fn ($query) => $query->where('ownership_type', $request->ownership_type))
            ->when($request->filled('activity_status'),
                fn ($query) => $query->where('activity_status', $request->activity_status))
            ->when($request->filled('account_status'), fn ($query) => $query
                ->whereHas('user', fn ($user) => $user->where('status', $request->account_status)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        /* WHEN AN ASSOCIATION IS OPEN (Oct 2026)

           The directory is reached from an association's details with
           ?association_id=, and on that screen the page is about that one
           association. Three things follow from it:

           - the association dropdown is not shown (the view hides it), since
             there is nothing to choose;
           - the summary counts that association's members, not the whole
             municipality. It used to say 23 members above a list of two;
           - the barangay dropdown offers only the barangays the association
             governs, from its area coverage (association_barangay). An
             association with no coverage recorded falls back to the
             barangays its own members live in, so the filter is never empty
             for an association that has members.

           Without ?association_id= nothing changes: all barangays, all
           associations, municipality-wide counts. */
        $association = $request->filled('association_id')
            ? Association::with('coverage')->find($request->association_id)
            : null;

        $scope = fn () => Farmer::notDeleted()
            ->when($association, fn ($query) => $query->where('association_id', $association->id));

        $summary = [
            'total'    => $scope()->count(),
            'verified' => User::where('role', 'farmer')->where('status', 'active')->count(),
            'pending'  => User::where('role', 'farmer')->where('status', 'pending')->count(),
            'active'   => $scope()->where('activity_status', 'active')->count(),
            'inactive' => $scope()->where('activity_status', 'inactive')->count(),
        ];

        if ($association) {
            $barangays = $association->coverage->sortBy('name')->values();

            if ($barangays->isEmpty()) {
                $barangays = Barangay::whereIn('id', $scope()->whereNotNull('barangay_id')->select('barangay_id'))
                    ->orderBy('name')->get();
            }
        } else {
            $barangays = Barangay::orderBy('name')->get();
        }

        // List + detail panel (Sept 2026): the row a MAO user clicks "View"
        // on loads inline in the right-hand panel via ?selected=<id>,
        // instead of navigating away to a separate page.
        $selected = $request->filled('selected')
            ? Farmer::query()->notDeleted()->with($this->detailRelations())->find($request->selected)
            : null;

        return view('mao.farmers.index', [
            'farmers'      => $farmers,
            'selected'     => $selected,
            'summary'      => $summary,
            'barangays'    => $barangays,
            'associations' => Association::orderBy('name')->get(),
            'association'  => $association,
        ]);
    }

    /**
     * One farmer's profile.
     *
     * Organised as six tabs rather than one long column (revision section
     * 9): Overview, Crop Planted, Crop Damage, Validation & Assessment,
     * Assistance and History. The tab is a query parameter, not script, so
     * every tab has its own URL and the Back button behaves.
     *
     * Everything is loaded whichever tab is open. That is deliberate: the
     * tab strip shows a count beside each tab, so the page needs all of it
     * anyway, and one farmer's records are not a large query.
     */
    public const TABS = ['overview', 'planting', 'damage', 'validation', 'assistance', 'history'];

    public function show(Request $request, Farmer $farmer)
    {
        $farmer->load($this->detailRelations());

        $tab = $request->query('tab');

        return view('mao.farmers.show', [
            'farmer' => $farmer,
            'tab'    => in_array($tab, self::TABS, true) ? $tab : 'overview',
        ]);
    }

    private function detailRelations(): array
    {
        return [
            'user',
            'barangay',
            'association',
            'mainCrops.crop',
            'plantingRecords.crops.crop',
            'damageReports.crops.crop',
            'damageReports.disasters',
            'damageReports.validation.technician',
            'assistanceDistributions.allocation.assistance',
        ];
    }
}
