<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Account Settings, shared by every role that has it (Farmer, Technician,
 * Association - MAO's own "Settings" is a different page, the reference-data
 * hub in MAO/SettingsController, and keeps its own name).
 *
 * This was previously a dead end: the sidebar rendered "Settings" as a
 * disabled, greyed-out item for every role except MAO, because there was
 * nothing to link it to. There is deliberately no per-role profile editing
 * here (a Farmer's name, address, farm details, etc. stay read-only per the
 * proposal - see Farmer/ProfileController) - only the two things every
 * account genuinely needs to be able to change itself: its login email and
 * phone number, and its password. Both live on `users`, not on any
 * role-specific table, so the same two actions work unchanged for all three
 * roles that use this trait.
 *
 * Each controller using this trait only has to say where its view lives.
 */
trait ManagesAccountSettings
{
    public function index()
    {
        return view($this->settingsView('index'), [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Update the two account-level contact fields. Proposal section 91.3:
     * Edit -> Review Changes -> Confirm -> Save. The review step is the
     * form's own data-confirm attributes (see the shared settings view);
     * this method only runs after that confirmation.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'email'        => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $user->update($data);

        AuditLog::create([
            'user_id'      => $user->id,
            'action'       => 'Updated account contact details',
            'target_table' => 'users',
            'target_id'    => $user->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Account details updated successfully.');
    }

    /**
     * Change password. `current_password` is Laravel's built-in rule - it
     * re-checks the hash against the signed-in guard, so this can never be
     * used to take over an account someone is merely browsing as.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'          => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update(['password' => $data['password']]);

        AuditLog::create([
            'user_id'      => $user->id,
            'action'       => 'Changed account password',
            'target_table' => 'users',
            'target_id'    => $user->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Password updated successfully.');
    }

    /**
     * Which Blade view renders this role's settings page, e.g.
     * "farmer.settings.index". Kept as a single method rather than a
     * hardcoded string so the shared logic above never has to know which
     * role called it.
     */
    abstract protected function settingsView(string $page): string;
}
