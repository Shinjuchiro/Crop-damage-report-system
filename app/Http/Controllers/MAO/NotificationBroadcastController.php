<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Disaster;
use App\Models\Farmer;
use App\Models\NotificationBroadcast;
use App\Models\User;
use App\Services\SemaphoreSmsService;
use Illuminate\Http\Request;
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

        /* officeAlerts(): an association's announcements to its own members
           live in this same table and are that association's business, not
           the office's. They are excluded here and from the counts below so
           this page stays what it says it is - what the OFFICE has sent. */
        $alerts = NotificationBroadcast::query()
            ->notDeleted()
            ->officeAlerts()
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
            ->notDeleted()
            ->officeAlerts()
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
            'priority'      => ['required', Rule::in(array_keys(NotificationBroadcast::selectablePriorities()))],
            'target_type'   => ['required', Rule::in(array_keys(NotificationBroadcast::AUDIENCES))],
            'target_id'     => [
                Rule::requiredIf(fn () => str_starts_with((string) $request->target_type, 'specific_')),
                'nullable', 'integer',
            ],
            'scheduled_for' => ['nullable', 'date', 'after:now'],

            /* The official disaster record. A Disaster Advisory must carry all
               three; any other alert may name a hazard, and if it gives a date
               it must give the name too (and the other way round). */
            'hazard'        => [
                Rule::requiredIf(fn () => $request->category === 'disaster_alert'),
                'nullable', Rule::in(array_keys(NotificationBroadcast::HAZARDS)),
            ],
            // The disaster name is optional (not every event has an official
            // name). Without one the record is named after the hazard and date.
            'event_name'    => ['nullable', 'string', 'max:255'],
            'disaster_date' => [
                Rule::requiredIf(fn () => $request->category === 'disaster_alert' || $request->filled('event_name')),
                'nullable', 'date', 'after_or_equal:2000-01-01',
            ],
        ], [
            'hazard.required'        => 'Choose the hazard this Disaster Advisory is about.',
            'disaster_date.required' => 'Enter the official disaster date.',
        ], [
            'target_type'   => 'audience',
            'target_id'     => 'recipient',
            'scheduled_for' => 'schedule',
            'disaster_date' => 'official disaster date',
            'event_name'    => 'disaster name',
        ]);

        $scheduled = ! empty($data['scheduled_for']);

        $alert = DB::transaction(function () use ($data, $scheduled) {
            /* The structured record behind the advisory. The same event is
               reused if the office announces it again (same type, name and
               date), so a follow-up advisory does not create a duplicate. */
            $event = null;

            if (! empty($data['hazard']) && ! empty($data['disaster_date'])) {
                $type = NotificationBroadcast::HAZARD_EVENT_TYPE[$data['hazard']];
                $date = \Illuminate\Support\Carbon::parse($data['disaster_date']);

                // No official name given: "Flood (October 15, 2026)".
                $eventName = trim((string) ($data['event_name'] ?? ''))
                    ?: NotificationBroadcast::HAZARDS[$data['hazard']][0] . ' (' . $date->format('F j, Y') . ')';

                $event = Disaster::active()
                    ->where('type', $type)
                    ->where('name', $eventName)
                    ->whereDate('date_start', $data['disaster_date'])
                    ->first()
                    ?? Disaster::create([
                        'type'       => $type,
                        'name'       => $eventName,
                        'date_start' => $data['disaster_date'],
                    ]);

                if ($event->wasRecentlyCreated) {
                    AuditLog::create([
                        'user_id'      => Auth::id(),
                        'action'       => 'Recorded disaster event from advisory: ' . $event->name
                            . ' (' . $event->date_start->toDateString() . ')',
                        'target_table' => 'disasters',
                        'target_id'    => $event->id,
                        'created_at'   => now(),
                    ]);
                }
            }

            $alert = NotificationBroadcast::create([
                'hazard'        => $data['hazard'] ?? null,
                'disaster_id'   => $event?->id,
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

        $smsNote = '';

        if ($alert->sends_sms) {
            if (! app(SemaphoreSmsService::class)->configured()) {
                $smsNote = ' SMS was not sent because the SMS provider is not configured yet '
                    . '(add SEMAPHORE_API_KEY to .env) - the in-app alert above still went out.';
            } else {
                /* "Accepted by the SMS provider", not "delivered" (Oct
                   2026). This said "SMS delivered to 12 of 14 recipients",
                   and the system has no way to know that: sendSms() records
                   'sent' when the provider ACCEPTS the request, and a text
                   accepted by Semaphore can still fail on the way to a
                   handset that is off, out of coverage or no longer in
                   service. Revision section 13: "Do not claim delivery
                   unless confirmed by the relevant service."

                   This matters more here than it looks. The office sends
                   these for urgent disaster advisories, and a message
                   telling them twelve farmers were reached is a message
                   telling them not to follow up by other means. */
                $smsSent   = $alert->notifications()->where('sms_status', 'sent')->count();
                $smsFailed = $alert->notifications()->where('sms_status', 'failed')->count();
                $smsNote   = ' SMS accepted by the provider for ' . $smsSent . ' of '
                    . ($smsSent + $smsFailed) . ' recipients. Acceptance is not proof of '
                    . 'delivery to the handset. See SMS History for details.';
            }
        }

        return back()->with('status',
            'Alert sent to ' . $sent . ' ' . \Illuminate\Support\Str::plural('recipient', $sent) . '.' . $smsNote);
    }

    /**
     * Send a scheduled alert immediately, or resend one that found nobody.
     */
    public function send(NotificationBroadcast $notification)
    {
        if ($notification->is_deleted) {
            return back()->withErrors(['alert' => 'This alert was permanently deleted.']);
        }

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
        if ($notification->is_deleted) {
            return back()->withErrors([
                'alert' => 'This alert was permanently deleted and can no longer be restored.',
            ]);
        }

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
