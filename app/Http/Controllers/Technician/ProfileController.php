<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Concerns\ManagesProfilePhoto;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * A technician's own profile: name, role and their profile photo.
 *
 * Their login email, phone number and password live on the separate
 * Settings page (Technician/SettingsController, ManagesAccountSettings).
 * This page only adds the one thing that was still missing - a photo -
 * so the two pages stay small and separate rather than merging into one
 * do-everything account screen.
 */
class ProfileController extends Controller
{
    use ManagesProfilePhoto;

    public function index()
    {
        return view('technician.profile', [
            'user' => Auth::user(),
        ]);
    }
}
