<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * In-app alerts the MAO sends to farmers, associations and technicians.
 *
 * Sending writes one notification row per recipient. Urgent and Critical alerts
 * additionally mark those rows sms_status = 'pending'; actually handing them to
 * an SMS provider is a separate build step, so nothing here claims a text was
 * delivered.
 */
class NotificationBroadcastController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');

        $alerts = NotificationBroadcast::query()
            ->with('createdBy')
            ->withCount([
                'notifications',
                'notifications as read_count' => fn ($query) => $query->where('is_read', true),
            ])
            ->when(array_key_exists($category, NotificationBroadcast::CATEGORIES),
                fn ($query) => $query->where('category', $category))
            ->when($request->filled('status'),
                fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';
                $query->where(fn ($sub) => $sub->where('title', 'like', $term)
                    ->orWhere('message', 'like', $term));
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Counts for the tab row, so each tab shows how much sits behind it
        $perCategory = NotificationBroadcast::query()
            ->select('category', DB::raw('COUNT(*) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');

        return view('mao.notifications.index', [
            'alerts'       => $alerts,
            'category'     => $category,
            'perCategory'  => $perCategory,
            'totalAlerts'  => (int) $perCategory->sum(),
            'audienceSize' => $this->audienceSizes(),
            // An archived association is not a sensible target for a new alert.
            'associations' => Association::active()->orderBy('name')->get(),
            'technicians'  => User::where('role', 'technician')->where('status', 'active')
                                  ->orderBy('full_name')->orderBy('username')->get(),
            'farmers'      => Farmer::whereHas('user', fn ($q) => $q->where('status', 'active'))
                                  ->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'message'       => ['required', 'string', 'max:2000'],
            'category'      => ['required', Rule::in(array_keys(NotificationBroadcast::CATEGORIES))],
            'priority'      => ['required', Rule::in(array_keys(NotificationBroadcast::PRIORITIES))],
            'target_type'   => ['required', Rule::in(array_keys(NotificationBroadcast::AUDIENCES))],
            'target_id'     => [
                Rule::requiredIf(fn () => str_starts_with((string) $request->target_type, 'specific_')),
                'nullable', 'integer',
            ],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ], [], [
            'target_type'   => 'audience',
            'target_id'     => 'recipient',
            'scheduled_for' => 'schedule',
        ]);

        $scheduled = ! empty($data['scheduled_for']);

        $alert = DB::transaction(function () use ($data, $scheduled) {
            $alert = NotificationBroadcast::create([
                'title'         => $data['title'],
                'message'       => $data['message'],
                'category'      => $data['category'],
                'priority'      => $data['priority'],
                'target_type'   => $data['target_type'],
                'target_id'     => str_starts_with($data['target_type'], 'specific_')
                                    ? $data['target_id'] : null,
                'status'        => $scheduled ? 'scheduled' : 'draft',
                'scheduled_for' => $data['scheduled_for'] ?? null,
                'created_by'    => Auth::id(),
            ]);

            if (! $scheduled) {
                $alert->dispatchToRecipients();
            }

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => ($scheduled ? 'Scheduled' : 'Sent') . ' ' . $alert->priority
                    . ' alert: ' . $alert->title,
                'target_table' => 'notification_broadcasts',
                'target_id'    => $alert->id,
                'created_at'   => now(),
            ]);

            return $alert;
        });

        $alert->refresh();

        if ($scheduled) {
            return back()->with('status',
                'Alert scheduled for ' . $alert->scheduled_for->format('M d, Y g:i A') . '.');
        }

        if ($alert->status === 'failed') {
            return back()->withErrors([
                'target_type' => 'Nobody matched that audience, so the alert was not sent. It is saved as failed.',
            ]);
        }

        $sent = $alert->notifications()->count();

        return back()->with('status',
            'Alert sent to ' . $sent . ' ' . \Illuminate\Support\Str::plural('recipient', $sent) . '.'
            . ($alert->sends_sms ? ' SMS for this alert is queued and will go out once the SMS provider is connected.' : ''));
    }

    /**
     * Send a scheduled alert immediately, or resend one that found nobody.
     */
    public function send(NotificationBroadcast $notification)
    {
        if ($notification->status === 'sent') {
            return back()->withErrors(['alert' => 'This alert has already been sent.']);
        }

        $sent = $notification->dispatchToRecipients();

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Sent alert now: ' . $notification->title,
            'target_table' => 'notification_broadcasts',
            'target_id'    => $notification->id,
            'created_at'   => now(),
        ]);

        return $sent === 0
            ? back()->withErrors(['alert' => 'Nobody matched that audience, so nothing was sent.'])
            : back()->with('status', 'Alert sent to ' . $sent . ' recipients.');
    }

    /**
     * Archive rather than delete, so the record of what was announced survives.
     */
    public function archive(NotificationBroadcast $notification)
    {
        $notification->update(['status' => 'archived']);

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Archived alert: ' . $notification->title,
            'target_table' => 'notification_broadcasts',
            'target_id'    => $notification->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Alert archived.');
    }

    /**
     * Bring an archived alert back. It goes back to 'sent' when it already
     * reached recipients, otherwise 'draft', since there is no separate
     * column remembering what it was before it was archived.
     */
    public function restore(NotificationBroadcast $notification)
    {
        $notification->update([
            'status' => $notification->notifications()->exists() ? 'sent' : 'draft',
        ]);

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Restored alert: ' . $notification->title,
            'target_table' => 'notification_broadcasts',
            'target_id'    => $notification->id,
            'created_at'   => now(),
        ]);

        return back()->with('status', 'Alert restored.');
    }

    /**
     * How many people each audience option currently reaches, shown next to the
     * choices so the MAO knows the size of what they are about to send.
     */
    private function audienceSizes(): array
    {
        $activeByRole = User::query()
            ->where('status', 'active')
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        return [
            'all_farmers'      => (int) $activeByRole->get('farmer', 0),
            'all_associations' => (int) $activeByRole->get('association', 0),
            'all_technicians'  => (int) $activeByRole->get('technician', 0),
            'affected_farmers' => DB::table('farmers')
                ->join('users', 'users.id', '=', 'farmers.user_id')
                ->whereIn('farmers.id', DB::table('damage_reports')->select('farmer_id'))
                ->where('users.status', 'active')
                ->count(),
        ];
    }
}
