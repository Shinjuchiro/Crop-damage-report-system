<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * MAO's own notification bell.
 *
 * Every other role already has one (Farmer/Technician/Association
 * NotificationController): a personal inbox of Notification rows addressed
 * to the signed-in user, separate from anything the office SENDS. MAO never
 * had this, because its "Notification and Alerts" page (see
 * NotificationBroadcastController) has always done double duty as both the
 * compose form and the full send history - useful, but not the same thing
 * as "what has MAO itself been told about". A new farmer registration, for
 * instance, already writes a Notification row for every active MAO user
 * (NotificationBroadcast::recipientIds()'s 'all_mao' case), but until this
 * controller there was nowhere for an MAO user to read it as their own
 * unread item with its own read state.
 *
 * Deliberately its own controller/route/view rather than folded into
 * NotificationBroadcastController: that one manages broadcasts as objects
 * MAO administers (archive, resend, delete); this one reads Notification
 * rows as the recipient, the same shape the other three roles already use.
 * Not in the sidebar either, matching that same pattern (see nav-farmer.
 * blade.php's note on this) - the bell in the top bar is the only door.
 */
class NotificationInboxController extends Controller
{
    /**
     * ?filter=unread narrows the list to what hasn't been opened yet.
     */
    public function index(Request $request)
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $notifications = Notification::query()
            ->with('broadcast.createdBy')
            ->where('user_id', Auth::id())
            ->when($filter === 'unread', fn ($query) => $query->where('is_read', false))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('mao.notifications.inbox.index', [
            'notifications' => $notifications,
            'filter'        => $filter,
            'unread'        => Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->count(),
        ]);
    }

    /**
     * Opening one marks it read. Nothing is ever deleted: the record of what
     * was sent has to survive (section 81).
     *
     * When it's about a specific record (see NotificationBroadcast::
     * linkUrl()), this goes straight there instead of our own message page -
     * "clicking a notification should take the user directly to the
     * relevant module/record".
     */
    public function show(Notification $notification)
    {
        abort_unless($notification->user_id === Auth::id(), 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        if ($url = $notification->broadcast?->linkUrl('mao')) {
            return redirect($url);
        }

        return view('mao.notifications.inbox.show', [
            'notification' => $notification->load('broadcast.createdBy'),
        ]);
    }

    /**
     * "Mark all as read". One update query rather than a loop.
     */
    public function markAllRead()
    {
        $marked = Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('status', $marked === 0
            ? 'There was nothing unread.'
            : $marked . ' ' . Str::plural('notification', $marked) . ' marked as read.');
    }
}
