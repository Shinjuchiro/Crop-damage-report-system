<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Farmer Notifications (in-app inbox)
 *
 * Proposal section 66. These rows are created by
 * NotificationBroadcast::dispatchToRecipients() on the MAO side, one row
 * per recipient. So a farmer only ever sees what was addressed to them.
 */
class NotificationController extends Controller
{
    /**
     * The inbox list.
     */
    public function index()
    {
        $notifications = Notification::query()
            ->with('broadcast.createdBy')     // the actual title and message live on the broadcast
            ->where('user_id', Auth::id())    // never show other people's notifications
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('farmer.notifications.index', [
            'notifications' => $notifications,
            'unread'        => Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->count(),
        ]);
    }

    /**
     * Open one notification.
     *
     * Opening it marks it as read. We never delete notifications, because
     * the record of what the office announced has to survive (section 81).
     */
    public function show(Notification $notification)
    {
        // Without this check, changing the id in the URL would let a farmer
        // read somebody else's notification.
        abort_unless($notification->user_id === Auth::id(), 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return view('farmer.notifications.show', [
            'notification' => $notification->load('broadcast.createdBy'),
        ]);
    }

    /**
     * "Mark all as read" button.
     *
     * One update query for everything instead of looping row by row.
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
