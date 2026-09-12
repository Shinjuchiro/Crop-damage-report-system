<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Concerns\ManagesProfilePhoto;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * An association officer's own profile: name, role and their profile
 * photo. Login email, phone number and password stay on the separate
 * Settings page (Association/SettingsController, ManagesAccountSettings) -
 * see Technician/ProfileController for why this is a second, small page
 * rather than folded into that one.
 */
class ProfileController extends Controller
{
    use ManagesProfilePhoto;

    public function index()
    {
        return view('association.profile', [
            'user' => Auth::user(),
        ]);
    }
}
