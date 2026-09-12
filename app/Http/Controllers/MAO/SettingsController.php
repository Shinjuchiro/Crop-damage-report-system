<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Concerns\ManagesAccountSettings;
use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\Association;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\Disaster;
use Illuminate\Support\Facades\Auth;

/**
 * Hub for the reference data the rest of the system depends on, PLUS the
 * MAO account's own login email, phone number and password.
 *
 * The account-settings half is deliberately the same shared trait every
 * other role uses (ManagesAccountSettings - see that file's own docblock),
 * so MAO's password rule, review-before-save behaviour and audit logging
 * never drift from Farmer/Technician/Association's. Only updateProfile()
 * and updatePassword() come from the trait; index() stays fully overridden
 * here because MAO's "Settings" is a different kind of page (the reference
 * -data hub below), not a small standalone account page like the other
 * three roles have.
 *
 * mao/profile.blade.php already tells the MAO user "your login email, phone
 * number and password are managed on the Account Settings page" - this is
 * what makes that claim true.
 */
class SettingsController extends Controller
{
    use ManagesAccountSettings;

    public function index()
    {
        return view('mao.settings.index', [
            'user'   => Auth::user(),
            'counts' => [
                'associations' => Association::count(),
                'assistance'   => Assistance::count(),
                'crops'        => Crop::count(),
                'disasters'    => Disaster::count(),
                'barangays'    => Barangay::count(),
            ],
        ]);
    }

    /**
     * Required by the trait but never actually rendered - index() above is
     * fully overridden and never calls view($this->settingsView(...)).
     * Kept accurate rather than a dummy value in case that ever changes.
     */
    protected function settingsView(string $page): string
    {
        return "mao.settings.$page";
    }
}
