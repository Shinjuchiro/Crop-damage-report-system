<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Association Notifications  (proposal section 66)
 *
 * Same shape as the farmer inbox. Rows are written by
 * NotificationBroadcast::dispatchToRecipients() on the MAO side, one per
 * recipient, so an officer only ever sees what was addressed to them.
 *
 * This is the bell: full history, action-required or not. Kept deliberately
 * separate from the sidebar badges on Damage Reports / Assistance, which
 * count pending work off the records themselves - see the Sept 2026
 * notification-system rule (nav-association.blade.php).
 */
class NotificationController extends Controller
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

        return view('association.notifications.index', [
            'notifications' => $notifications,
            'filter'        => $filter,
            'unread'        => Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->count(),
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

    /**
     * Opening one marks it read. Nothing is ever deleted: the record of what
     * the office announced has to survive (section 81).
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

        if ($url = $notification->broadcast?->linkUrl('association')) {
            return redirect($url);
        }

        return view('association.notifications.show', [
            'notification' => $notification->load('broadcast.createdBy'),
        ]);
    }
}
