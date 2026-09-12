<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationBroadcast;
use Illuminate\Http\Request;

/**
 * Proposal section 71: every text the system has sent or tried to send.
 *
 * There is no separate sms_notifications table. The notifications table
 * already carries an sms_status column that covers the whole lifecycle
 * (pending/sent/delivered/failed) - this screen just reads it, joined back
 * to the alert and the recipient, instead of duplicating that data
 * somewhere else. See NotificationBroadcast::sendSms() for where these
 * rows get their final status.
 */
class SmsHistoryController extends Controller
{
    public function index(Request $request)
    {
        $history = Notification::query()
            ->where('sms_status', '!=', 'not_applicable')
            ->with(['user', 'broadcast'])
            ->when($request->filled('status'), fn ($q) => $q->where('sms_status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->whereHas(
                'broadcast',
                fn ($b) => $b->where('priority', $request->priority)
            ))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(fn ($sub) => $sub
                    ->whereHas('user', fn ($u) => $u->where('full_name', 'like', $term)
                        ->orWhere('username', 'like', $term))
                    ->orWhereHas('broadcast', fn ($b) => $b->where('title', 'like', $term)));
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('mao.sms-history.index', [
            'history' => $history,
            'counts'  => [
                'sent'      => Notification::where('sms_status', 'sent')->count(),
                'delivered' => Notification::where('sms_status', 'delivered')->count(),
                'pending'   => Notification::where('sms_status', 'pending')->count(),
                'failed'    => Notification::where('sms_status', 'failed')->count(),
            ],
            'priorities' => NotificationBroadcast::PRIORITIES,
        ]);
    }
}
