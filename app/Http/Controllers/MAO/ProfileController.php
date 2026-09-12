<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Concerns\ManagesProfilePhoto;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in MAO account's own profile: name, role and their profile
 * photo. Deliberately separate from MAO/SettingsController, which is the
 * crop/disaster/association reference-data hub and stays in the sidebar -
 * see Technician/ProfileController for the same split on the other roles.
 */
class ProfileController extends Controller
{
    use ManagesProfilePhoto;

    public function index()
    {
        return view('mao.profile', [
            'user' => Auth::user(),
        ]);
    }
}
