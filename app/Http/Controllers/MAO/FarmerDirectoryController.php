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
        // Section 22's 3-month rule: keep every farmer's Active/Inactive
        // status current before it is displayed, filtered, or counted below
        // - see Farmer::sweepInactive() for why this has to run here.
        Farmer::sweepInactive();

        $farmers = Farmer::query()
            ->notDeleted()
            ->with(['user', 'barangay', 'association'])
            ->withCount(['plantingRecords', 'damageReports'])
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

        $summary = [
            'total'    => Farmer::notDeleted()->count(),
            'verified' => User::where('role', 'farmer')->where('status', 'active')->count(),
            'pending'  => User::where('role', 'farmer')->where('status', 'pending')->count(),
            'active'   => Farmer::notDeleted()->where('activity_status', 'active')->count(),
            'inactive' => Farmer::notDeleted()->where('activity_status', 'inactive')->count(),
        ];

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
            'barangays'    => Barangay::orderBy('name')->get(),
            'associations' => Association::orderBy('name')->get(),
        ]);
    }

    public function show(Farmer $farmer)
    {
        $farmer->load($this->detailRelations());

        return view('mao.farmers.show', compact('farmer'));
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
