<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Services\SemaphoreSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * An association's own announcements to its own members.
 *
 * An officer writes a meeting notice, a reminder or a deadline, and it
 * reaches the verified farmers of that association and nobody else: not
 * another association's members, not the officers of other associations,
 * not the office. The office neither approves these nor sees them - an
 * association's meetings are its own business (see
 * NotificationBroadcast::scopeOfficeAlerts(), which keeps them off MAO's
 * Announcements page and out of its Archive).
 *
 * BUILT ON WHAT IS ALREADY THERE
 *
 * This is not a second notification system. An announcement is an ordinary
 * notification_broadcasts row addressed to the 'association_members'
 * audience, so it reaches members through the same bell, the same read
 * tracking and the same SMS path the office's alerts already use. The only
 * thing this controller adds is who is allowed to write one and who it is
 * allowed to reach, and both are pinned here rather than chosen in the
 * form: target_type and target_id are never taken from the request.
 *
 * DELIVERY
 *
 * Normal and Important are in-app only. Urgent and Critical also send an
 * SMS, exactly as the office's alerts do and through the same provider,
 * because the priority rules in sections 67 and 68 are about how urgent the
 * message is, not about who sent it. The review screen says plainly which
 * of the two is about to happen, and SMS costs real money, so a single
 * careless pick of Urgent should never be possible without reading it.
 */
class AnnouncementController extends Controller
{
    use ResolvesAssociation;

    /**
     * What an association may announce.
     *
     * A deliberately shorter list than the office's. Disaster advisories and
     * system notices are the municipality's to issue - an association
     * announcing a typhoon advisory is how two sources of the same warning
     * start to disagree. Keys are the categories already in the table.
     */
    public const CATEGORIES = [
        'event'              => 'Meeting / Event',
        'announcement'       => 'General Announcement',
        'agricultural_alert' => 'Farming Reminder',
        'assistance'         => 'Assistance',
    ];

    public function index(Request $request)
    {
        $association = $this->association();

        $announcements = NotificationBroadcast::query()
            ->notDeleted()
            ->announcementsFor($association->id)
            ->with('createdBy')
            ->withCount([
                'notifications',
                'notifications as read_count' => fn ($query) => $query->where('is_read', true),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';
                $query->where(fn ($sub) => $sub->where('title', 'like', $term)
                    ->orWhere('message', 'like', $term));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('association.announcements.index', [
            'association'   => $association,
            'announcements' => $announcements,
            'categories'    => self::CATEGORIES,
            // How many people the next announcement will reach, shown on the
            // form so an officer is never guessing at the size of what they
            // are about to send. Same rule as recipientIds() uses.
            'memberCount'   => $this->memberCount($association->id),
            'smsReady'      => app(SemaphoreSmsService::class)->configured(),
        ]);
    }

    public function store(Request $request)
    {
        $association = $this->association();

        $data = $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'message'  => ['required', 'string', 'max:2000'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(NotificationBroadcast::selectablePriorities()))],
        ], [], [
            'category' => 'announcement type',
        ]);

        if ($this->memberCount($association->id) === 0) {
            return back()->withInput()->withErrors([
                'title' => 'This association has no verified members yet, so there is nobody to announce to.',
            ]);
        }

        $announcement = DB::transaction(function () use ($data, $association) {
            /* target_type and target_id are set here, never taken from the
               request: an officer picks what to say, not who hears it. That
               is the whole access rule for this feature, and it cannot be
               bypassed by editing the form. */
            $announcement = NotificationBroadcast::create([
                'title'       => $data['title'],
                'message'     => $data['message'],
                'category'    => $data['category'],
                'priority'    => $data['priority'],
                'target_type' => 'association_members',
                'target_id'   => $association->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $announcement->dispatchToRecipients();

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Association announcement sent (' . $association->name . '): ' . $announcement->title,
                'target_table' => 'notification_broadcasts',
                'target_id'    => $announcement->id,
                'created_at'   => now(),
            ]);

            return $announcement;
        });

        $announcement->refresh();

        if ($announcement->status === 'failed') {
            return back()->withInput()->withErrors([
                'title' => 'Nobody received this announcement, so it was not sent.',
            ]);
        }

        $sent = $announcement->notifications()->count();
        $note = '';

        if ($announcement->sends_sms) {
            if (! app(SemaphoreSmsService::class)->configured()) {
                $note = ' SMS was not sent because the SMS provider is not set up yet - '
                    . 'the in-app announcement still reached everyone.';
            } else {
                /* "Accepted", not "delivered": the provider accepting a
                   text is not the same as a handset receiving it, and
                   revision section 13 says not to claim the second when
                   only the first is known. Same change as
                   MAO\NotificationBroadcastController, where the reasoning
                   is written out in full. */
                $smsSent   = $announcement->notifications()->where('sms_status', 'sent')->count();
                $smsFailed = $announcement->notifications()->where('sms_status', 'failed')->count();
                $note      = ' SMS accepted by the provider for ' . $smsSent . ' of '
                    . ($smsSent + $smsFailed) . ' members.';
            }
        }

        return redirect()->route('association.announcements.index')->with('status',
            'Announcement sent to ' . $sent . ' ' . Str::plural('member', $sent) . '.' . $note);
    }

    /**
     * Archive rather than delete, so what was announced stays on record
     * (section 72). Members keep the notification they already received -
     * this only takes it off the officer's active list.
     */
    public function archive(NotificationBroadcast $announcement)
    {
        $this->assertOurs($announcement);

        $announcement->update(['status' => 'archived']);

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Archived association announcement: ' . $announcement->title,
            'target_table' => 'notification_broadcasts',
            'target_id'    => $announcement->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Announcement archived.');
    }

    public function restore(NotificationBroadcast $announcement)
    {
        $this->assertOurs($announcement);

        $announcement->update([
            'status' => $announcement->notifications()->exists() ? 'sent' : 'draft',
        ]);

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Restored association announcement: ' . $announcement->title,
            'target_table' => 'notification_broadcasts',
            'target_id'    => $announcement->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Announcement restored.');
    }

    /**
     * An officer may only touch an announcement addressed to their own
     * members. Typing another id into the URL gets a 403, not somebody
     * else's announcement - and the office's own alerts live in this same
     * table, so without this check the route would reach those too.
     */
    private function assertOurs(NotificationBroadcast $announcement): void
    {
        abort_unless(
            $announcement->target_type === 'association_members'
                && (int) $announcement->target_id === $this->association()->id,
            403
        );
    }

    /** Verified, active members - exactly who an announcement reaches. */
    private function memberCount(int $associationId): int
    {
        return Farmer::where('association_id', $associationId)
            ->notDeleted()
            ->whereHas('user', fn ($user) => $user->where('status', 'active'))
            ->count();
    }
}
