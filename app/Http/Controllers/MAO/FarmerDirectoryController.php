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
        $farmers = Farmer::query()
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
            'total'    => Farmer::count(),
            'verified' => User::where('role', 'farmer')->where('status', 'active')->count(),
            'pending'  => User::where('role', 'farmer')->where('status', 'pending')->count(),
            'active'   => Farmer::where('activity_status', 'active')->count(),
            'inactive' => Farmer::where('activity_status', 'inactive')->count(),
        ];

        return view('mao.farmers.index', [
            'farmers'      => $farmers,
            'summary'      => $summary,
            'barangays'    => Barangay::orderBy('name')->get(),
            'associations' => Association::orderBy('name')->get(),
        ]);
    }

    public function show(Farmer $farmer)
    {
        $farmer->load([
            'user',
            'barangay',
            'association',
            'mainCrops.crop',
            'plantingRecords.crops.crop',
            'damageReports.crops.crop',
            'damageReports.disasters',
            'damageReports.validation.technician',
            'assistanceDistributions.allocation.assistance',
        ]);

        return view('mao.farmers.show', compact('farmer'));
    }
}
