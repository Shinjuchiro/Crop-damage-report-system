<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Technician Notifications  (proposal section 66)
 *
 * Same shape as the Farmer and Association inboxes - the last one of the
 * four roles to get it, which is why the top bar bell rendered inert for a
 * technician until now. Rows are written by
 * NotificationBroadcast::dispatchToRecipients() on the MAO side, one per
 * recipient (the `all_technicians` / `specific_technician` audiences already
 * existed - MAO could already send these, there was just nowhere for a
 * technician to read them), so a technician only ever sees what was
 * addressed to them.
 *
 * This is the bell: full history, action-required or not. Kept deliberately
 * separate from the "Assigned Reports" sidebar badge, which counts reports
 * not yet inspected off the reports themselves - see the Sept 2026
 * notification-system rule (nav-technician.blade.php).
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

        return view('technician.notifications.index', [
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
     * When it's about a specific report (see NotificationBroadcast::
     * linkUrl()), this goes straight to that report instead of our own
     * message page - "clicking a notification should take the user directly
     * to the relevant module/record".
     */
    public function show(Notification $notification)
    {
        abort_unless($notification->user_id === Auth::id(), 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        if ($url = $notification->broadcast?->linkUrl('technician')) {
            return redirect($url);
        }

        return view('technician.notifications.show', [
            'notification' => $notification->load('broadcast.createdBy'),
        ]);
    }
}
