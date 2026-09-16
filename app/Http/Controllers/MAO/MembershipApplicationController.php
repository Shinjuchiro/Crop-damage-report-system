<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Notifications\FarmerRegistrationApprovedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MAO reviews farmer registrations and approves or rejects them.
 * A farmer cannot use the system properly until approved.
 */
class MembershipApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $applications = Farmer::query()
            ->notDeleted()
            ->with(['user', 'association', 'barangay'])
            ->when(in_array($status, ['pending', 'active', 'rejected', 'inactive'], true),
                fn ($query) => $query->whereHas('user', fn ($user) => $user->where('status', $status)))
            ->when($request->filled('barangay_id'),
                fn ($query) => $query->where('barangay_id', $request->barangay_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';

                $query->where(function ($sub) use ($term) {
                    $sub->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereHas('user', fn ($user) => $user->where('username', 'like', $term));
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // List + detail panel (Sept 2026): "View" loads the application
        // inline in the right-hand panel via ?selected=<id> instead of
        // navigating to a separate review page.
        $selected = $request->filled('selected')
            ? Farmer::query()->notDeleted()->with(['user', 'association', 'barangay', 'mainCrops.crop'])->find($request->selected)
            : null;

        return view('mao.membership-applications.index', [
            'applications' => $applications,
            'selected'     => $selected,
            'status'       => $status,
            'barangays'    => Barangay::orderBy('name')->get(),
        ]);
    }

    public function show(Farmer $farmer)
    {
        $farmer->load(['user', 'association', 'barangay', 'mainCrops.crop']);

        return view('mao.membership-applications.show', compact('farmer'));
    }

    public function approve(Farmer $farmer)
    {
        DB::transaction(function () use ($farmer) {
            $farmer->user->update(['status' => 'active']);

            $farmer->update([
                'activity_status'    => 'active',
                'last_activity_date' => now()->toDateString(),
            ]);

            $this->log('Approved farmer registration: ' . $farmer->full_name, $farmer->id);
        });

        $this->notifyApproved($farmer);

        return redirect()->route('mao.membership-applications.index')
            ->with('status', 'Farmer registration approved successfully.');
    }

    /**
     * Tell the newly-approved farmer on every channel: in-app, SMS, and
     * email. Runs after the approval transaction above has already
     * committed, since none of this is something a failed send should be
     * allowed to roll back - the farmer is approved either way.
     *
     * In-app + SMS reuse the existing NotificationBroadcast pipeline
     * (App\Models\NotificationBroadcast::dispatchToRecipients()) instead of
     * writing to the notifications table directly, because
     * notification_broadcast_id is a required column there - this is the
     * one supported way to create a notifications row. 'urgent' priority is
     * what makes that pipeline also queue the SMS (see
     * NotificationBroadcast::PRIORITIES); it silently no-ops the SMS half
     * if SEMAPHORE_API_KEY is not configured yet, so approval itself is
     * never blocked by SMS being unset up.
     *
     * A failure in either channel is logged, not thrown - the farmer is
     * already approved and this page has already redirected with a success
     * message by the time this runs from approve() above.
     */
    private function notifyApproved(Farmer $farmer): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Registration Approved',
                'message'     => 'Your farmer registration has been reviewed and approved. '
                    . 'Your account is now active - you can log in and start recording your crop planting activities.',
                'category'    => 'system',
                'priority'    => 'urgent',
                'target_type' => 'specific_farmer',
                'target_id'   => $farmer->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not send approval in-app/SMS notification for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }

        try {
            $farmer->user->notify(new FarmerRegistrationApprovedNotification($farmer));
        } catch (\Throwable $e) {
            Log::warning('Could not send approval email for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }

        $this->notifyAssociationOfNewMember($farmer);
    }

    /**
     * Let the farmer's association know a new member just joined them - the
     * association only monitors its members (proposal Role 3), it does not
     * approve them, so this is purely informational and in-app only. It is
     * skipped entirely for a farmer who registered without an association,
     * since there is nobody to tell.
     *
     * 'normal' priority is deliberate: section 68 reserves SMS for Urgent
     * and Critical matters, and a routine membership update is neither.
     * Reuses the same NotificationBroadcast pipeline as notifyApproved()
     * above - 'specific_association' already resolves to every member
     * farmer and officer of that association (see
     * NotificationBroadcast::recipientIds()), so both the association's
     * officers and its existing members see this in their notification bell.
     */
    private function notifyAssociationOfNewMember(Farmer $farmer): void
    {
        if (! $farmer->association_id) {
            return;
        }

        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'New Member Approved',
                'message'     => $farmer->full_name . ' has been approved by the Municipal Agriculture Office '
                    . 'and is now an active member of your association.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'specific_association',
                'target_id'   => $farmer->association_id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify association ' . $farmer->association_id . ' of new member ' . $farmer->id . ': ' . $e->getMessage());
        }
    }

    public function reject(Request $request, Farmer $farmer)
    {
        $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($farmer, $request) {
            $farmer->user->update(['status' => 'rejected']);

            $this->log(
                'Rejected farmer registration: ' . $farmer->full_name
                    . ($request->filled('rejection_reason') ? ' - ' . $request->rejection_reason : ''),
                $farmer->id
            );
        });

        $this->notifyRejected($farmer, $request->input('rejection_reason'));

        return redirect()->route('mao.membership-applications.index')
            ->with('status', 'Farmer registration rejected.');
    }

    /**
     * The rejection counterpart to notifyApproved() above - proposal section
     * 66 lists "Registration rejected" as one of the things a farmer should
     * be told, and this was previously missing (only approval notified
     * anyone). In-app only ('normal' priority, section 68 reserves SMS for
     * urgent/critical matters): disappointing news, but not an emergency.
     * Runs after the transaction above has already committed, and never
     * throws - the rejection itself must never appear to fail just because a
     * notification could not be sent.
     */
    private function notifyRejected(Farmer $farmer, ?string $reason): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Registration Rejected',
                'message'     => 'Your farmer registration has been reviewed and was not approved by the '
                    . 'Municipal Agriculture Office.' . ($reason ? ' Reason: ' . $reason : '')
                    . ' Please contact the office if you have questions.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'specific_farmer',
                'target_id'   => $farmer->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not send rejection notification for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Archiving keeps the record but takes the application out of the queue.
     * Nothing is deleted.
     */
    public function archive(Farmer $farmer)
    {
        $farmer->user->update(['status' => 'inactive']);

        $this->log('Archived farmer application: ' . $farmer->full_name, $farmer->id);

        return back()->with('status', 'Application archived.');
    }

    /**
     * Bring an archived farmer back. Used from the MAO Archive page.
     *
     * Deliberately not the same as approve(): approve() is for a brand new
     * application and also resets activity_status and last_activity_date,
     * which would be wrong here. Restoring only undoes the archive.
     */
    public function restore(Farmer $farmer)
    {
        if ($farmer->is_deleted) {
            return back()->withErrors([
                'farmer' => 'This farmer was permanently deleted and can no longer be restored.',
            ]);
        }

        $farmer->user->update(['status' => 'active']);

        $this->log('Restored farmer: ' . $farmer->full_name, $farmer->id);

        return back()->with('status', 'Farmer restored.');
    }

    /**
     * Take the farmer out of the working system for good. Not a database
     * delete: markDeleted() only stamps deleted_at/deleted_by, so every field
     * on the farmer's profile - and the login account it belongs to - is kept
     * exactly as it was. It just stops appearing anywhere in the ordinary
     * system (the Membership Applications queue, the Farmer Directory, the
     * Archive page's Archived tab) and moves to the Archive page's Deleted
     * tab instead, read-only, together with who deleted it and when.
     */
    public function destroy(Farmer $farmer)
    {
        $farmer->markDeleted(Auth::id());

        $this->log('Deleted farmer: ' . $farmer->full_name, $farmer->id);

        return back()->with('status', 'Farmer deleted. Their information is kept for audit purposes.');
    }

    private function log(string $action, ?int $farmerId): void
    {
        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'target_table' => 'farmers',
            'target_id'    => $farmerId,
            'created_at'   => now(),
        ]);
    }
}
