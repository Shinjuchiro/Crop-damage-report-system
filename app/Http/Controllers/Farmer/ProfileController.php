<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use Illuminate\Support\Facades\Auth;

/**
 * Farmer Profile (view only)
 *
 * Proposal section 20 lists what the profile has to show.
 *
 * We made this read only on purpose. The MAO reviews and approves the
 * registration (section 19), so if a farmer could change their own
 * barangay or association afterwards, that approval would not mean
 * anything anymore and their reports would move on the map. Corrections
 * go through the office instead, and the page explains that.
 *
 * Section 20 also says the password must never be displayed. Nothing on
 * this page reads it.
 */
class ProfileController extends Controller
{
    public function show()
    {
        $farmer = Farmer::with([
                'user',              // for email, phone and account status
                'association',
                'barangay',
                'mainCrops.crop',    // section 17: a farmer can have many main crops
            ])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Same 3 month check as the dashboard, so the status shown here
        // matches what the dashboard shows.
        $farmer->refreshActivityStatus();

        return view('farmer.profile', [
            'farmer' => $farmer,
        ]);
    }
}
