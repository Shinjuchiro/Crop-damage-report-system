<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Association Notifications  (proposal section 66)
 *
 * Same shape as the farmer inbox. Rows are written by
 * NotificationBroadcast::dispatchToRecipients() on the MAO side, one per
 * recipient, so an officer only ever sees what was addressed to them.
 */
class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::query()
            ->with('broadcast.createdBy')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('association.notifications.index', [
            'notifications' => $notifications,
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
     */
    public function show(Notification $notification)
    {
        abort_unless($notification->user_id === Auth::id(), 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return view('association.notifications.show', [
            'notification' => $notification->load('broadcast.createdBy'),
        ]);
    }
}
