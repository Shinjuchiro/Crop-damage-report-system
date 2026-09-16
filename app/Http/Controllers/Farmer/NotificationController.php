<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Farmer Notifications (in-app inbox)
 *
 * Proposal section 66. These rows are created by
 * NotificationBroadcast::dispatchToRecipients() on the MAO side, one row
 * per recipient. So a farmer only ever sees what was addressed to them.
 *
 * This is the bell: the full history of what was sent, action-required or
 * not. It is deliberately separate from the sidebar badges on My Reports /
 * Assistance, which count pending work off the actual records instead - see
 * the Sept 2026 notification-system rule (nav-farmer.blade.php,
 * Farmer\DamageReportController, Farmer\AssistanceController). Reading a
 * notification here never changes a badge count.
 */
class NotificationController extends Controller
{
    /**
     * The inbox list. ?filter=unread narrows it to what hasn't been opened
     * yet; anything else (including no filter at all) shows everything.
     */
    public function index(Request $request)
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $notifications = Notification::query()
            ->with('broadcast.createdBy')     // the actual title and message live on the broadcast
            ->where('user_id', Auth::id())    // never show other people's notifications
            ->when($filter === 'unread', fn ($query) => $query->where('is_read', false))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('farmer.notifications.index', [
            'notifications' => $notifications,
            'filter'        => $filter,
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
     *
     * When this notification is about a specific record (a damage report,
     * an allocation - see NotificationBroadcast::linkUrl()), opening it goes
     * straight there instead of to our own message page, per the "clicking
     * a notification should take the user directly to the relevant
     * module/record" rule. A purely informational alert (a general
     * announcement, a disaster advisory) has no such record, so it still
     * falls back to the plain message page below.
     */
    public function show(Notification $notification)
    {
        // Without this check, changing the id in the URL would let a farmer
        // read somebody else's notification.
        abort_unless($notification->user_id === Auth::id(), 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        if ($url = $notification->broadcast?->linkUrl('farmer')) {
            return redirect($url);
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
