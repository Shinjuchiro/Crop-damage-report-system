<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationBroadcast;
use Illuminate\Http\Request;

/**
 * SMS History for an association officer: the texts sent for THIS
 * association's own announcements and nothing else. The office's alerts and
 * other associations' announcements are never in the set being queried, so
 * there is nothing to filter out afterwards.
 *
 * Phone numbers are masked, and "delivered" is not offered: only "sent"
 * (accepted by the provider) and "failed" are ever recorded.
 */
class SmsHistoryController extends Controller
{
    use ResolvesAssociation;

    public function index(Request $request)
    {
        $association = $this->association();

        $mine = fn () => Notification::query()
            ->where('sms_status', '!=', 'not_applicable')
            ->whereHas('broadcast', fn ($b) => $b
                ->where('target_type', 'association_members')
                ->where('target_id', $association->id));

        $history = $mine()
            ->with(['user', 'broadcast'])
            ->when($request->filled('status'), fn ($q) => $q->where('sms_status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->whereHas(
                'broadcast', fn ($b) => $b->where('priority', $request->priority)))
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

        return view('association.sms-history.index', [
            'history'    => $history,
            'counts'     => [
                'sent'    => $mine()->where('sms_status', 'sent')->count(),
                'pending' => $mine()->where('sms_status', 'pending')->count(),
                'failed'  => $mine()->where('sms_status', 'failed')->count(),
            ],
            'priorities' => NotificationBroadcast::PRIORITIES,
        ]);
    }
}
