<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Profile photo upload/removal, shared by every role.
 *
 * The proposal only asked for a profile picture on top of the read-only
 * profile and account-settings pages that already exist - nothing else
 * about a profile becomes editable through this trait. One photo per
 * account, stored on the same public disk as damage report and inspection
 * photos (see Farmer/DamageReportController::PHOTO_DISK), replacing the
 * initials avatar used everywhere in the sidebar, dropdown and profile
 * header.
 *
 * Every controller using this trait only needs Auth::user() to know which
 * account to change - there is no "which view" method to declare, unlike
 * ManagesAccountSettings, because this trait never renders a page itself.
 */
trait ManagesProfilePhoto
{
    private const PHOTO_DISK = 'public';
    private const PHOTO_DIR  = 'profile-photos';

    /**
     * Proposal 91.7: preview, then confirm, before anything is saved. The
     * confirmation is the small dialog on the photo widget that shows the
     * chosen image before it submits (resources/views/components/profile-
     * photo.blade.php) - this method only runs after that confirmation.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [], ['photo' => 'photo']);

        $user = Auth::user();
        $old  = $user->profile_photo_path;

        $path = $request->file('photo')->store(self::PHOTO_DIR . '/' . $user->id, self::PHOTO_DISK);

        $user->update(['profile_photo_path' => $path]);

        // Delete the old file only after the new one is safely saved and
        // the record updated, so a failure above never leaves the account
        // with no photo file at all.
        if ($old) {
            Storage::disk(self::PHOTO_DISK)->delete($old);
        }

        AuditLog::create([
            'user_id'      => $user->id,
            'action'       => 'Updated profile photo',
            'target_table' => 'users',
            'target_id'    => $user->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Profile photo updated successfully.');
    }

    /**
     * Proposal 91.10-style confirm-before-remove: the "Remove photo" link
     * on the widget carries the shared data-confirm dialog, so this method
     * only ever runs after that confirmation too.
     */
    public function removePhoto()
    {
        $user = Auth::user();

        if ($user->profile_photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);

            AuditLog::create([
                'user_id'      => $user->id,
                'action'       => 'Removed profile photo',
                'target_table' => 'users',
                'target_id'    => $user->id,
                'created_at'   => now(),
            ]);
        }

        return back()->with('status', 'Profile photo removed.');
    }
}
