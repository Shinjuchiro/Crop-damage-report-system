<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletable;
use App\Services\SemaphoreSmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationBroadcast extends Model
{
    use SoftDeletable;

    /**
     * The kinds of alert the MAO sends, with the label shown in the interface.
     * Keys are the values stored in the category column.
     */
    public const CATEGORIES = [
        'disaster_alert'     => 'Disaster Advisory',
        'agricultural_alert' => 'Agricultural Advisory',
        'event'              => 'Event / Deadline',
        'system'             => 'Validation & System',
        'assistance'         => 'Assistance',
        'announcement'       => 'General Announcement',
    ];

    /**
     * Priority decides how an alert is delivered.
     * Normal and Important are in-app only. Urgent and Critical are also queued
     * for SMS, because those are the ones a farmer must see today.
     */
    public const PRIORITIES = [
        'normal'    => ['label' => 'Normal',    'sms' => false],
        'important' => ['label' => 'Important', 'sms' => false],
        'urgent'    => ['label' => 'Urgent',    'sms' => true],
        'critical'  => ['label' => 'Critical',  'sms' => true],
    ];

    /** Who an alert can be addressed to. */
    public const AUDIENCES = [
        'all_farmers'          => 'All Farmers',
        'affected_farmers'     => 'Affected Farmers',
        'all_associations'     => 'All Associations',
        'specific_association' => 'Specific Association',
        'specific_farmer'      => 'Specific Farmer',
        'all_technicians'      => 'All Technicians',
        'specific_technician'  => 'Specific Technician',
        'all_mao'              => 'MAO Staff',
    ];

    /**
     * What kind of record link_id (see the Sept 2026 notification-linking
     * migration) can point at. Kept short and internal, the same style as
     * target_type above, rather than a full Eloquent morph class string.
     *
     * damage_report          -> damage_reports.id
     * membership_application -> farmers.id (MAO's membership application
     *                           page is keyed by farmer, not a separate id)
     * assistance_allocation  -> assistance_allocations.id
     */
    public const LINK_TYPES = ['damage_report', 'membership_application', 'assistance_allocation'];

    protected $table = 'notification_broadcasts';

    protected $fillable = [
        'title', 'message', 'category', 'priority',
        'target_type', 'target_id', 'link_type', 'link_id',
        'attachment_path', 'status', 'scheduled_for', 'created_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'       => 'datetime',
            'scheduled_for' => 'datetime',
            'deleted_at'    => 'datetime',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Where clicking this notification should take a recipient of $forRole,
     * or null when there is nothing to open (no link recorded, or that role
     * has no page for this kind of link - a farmer has no membership
     * application review page, for instance).
     *
     * route() is given a bare id rather than the model itself on purpose:
     * every one of these routes resolves its parameter through implicit
     * route-model binding anyway, and building the URL from just the id
     * means this never has to load the record (which may since have been
     * archived or deleted - the bell must still render even then, see the
     * migration's docblock) just to link to it.
     */
    public function linkUrl(string $forRole): ?string
    {
        if (! $this->link_type || ! $this->link_id) {
            return null;
        }

        try {
            return match ($this->link_type) {
                'damage_report' => match ($forRole) {
                    'farmer'      => route('farmer.reports.show', $this->link_id),
                    'technician'  => route('technician.reports.show', $this->link_id),
                    'mao'         => route('mao.damage-reports.show', $this->link_id),
                    // No per-report page on the association side - the
                    // monitoring list is the closest thing it has.
                    'association' => route('association.reports.index'),
                    default       => null,
                },
                'membership_application' => match ($forRole) {
                    // Only MAO reviews applications - nobody else has this page.
                    'mao' => route('mao.membership-applications.show', $this->link_id),
                    default => null,
                },
                'assistance_allocation' => match ($forRole) {
                    'mao'         => route('mao.assistance-allocations.show', $this->link_id),
                    'association' => route('association.assistance.show', $this->link_id),
                    // A farmer never opens an allocation directly, only the
                    // distributions made from it - their own Assistance page.
                    'farmer'      => route('farmer.assistance.index'),
                    default       => null,
                },
                default => null,
            };
        } catch (\Throwable $e) {
            // A malformed or now-invalid id must never break the bell page
            // itself - worst case, the notification just isn't clickable.
            return null;
        }
    }

    /* ------------------------------------------------------------------
     | Convenience
     ------------------------------------------------------------------ */

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority]['label'] ?? ucfirst($this->priority);
    }

    public function getSendsSmsAttribute(): bool
    {
        return self::PRIORITIES[$this->priority]['sms'] ?? false;
    }

    /**
     * A readable description of who this alert went to.
     */
    public function getAudienceLabelAttribute(): string
    {
        $label = self::AUDIENCES[$this->target_type] ?? $this->target_type;

        if (! $this->target_id) {
            return $label;
        }

        $name = match ($this->target_type) {
            'specific_association' => Association::find($this->target_id)?->name,
            'specific_farmer'      => Farmer::find($this->target_id)?->full_name,
            'specific_technician'  => User::find($this->target_id)?->display_name,
            default                => null,
        };

        return $name ? $label . ': ' . $name : $label;
    }

    /**
     * The user IDs this alert is addressed to.
     *
     * Only active accounts receive alerts. A pending or rejected farmer has no
     * business getting an advisory meant for verified members.
     */
    public function recipientIds(): array
    {
        $activeUsers = fn (string $role) => User::where('role', $role)
            ->where('status', 'active')
            ->pluck('id');

        return match ($this->target_type) {
            'all_farmers'      => $activeUsers('farmer')->all(),
            'all_technicians'  => $activeUsers('technician')->all(),
            'all_associations' => $activeUsers('association')->all(),

            // The office itself. Every MAO/Super Admin account, so whoever is
            // on duty sees it - there is no "specific MAO staff member" case
            // because, unlike a technician or association, a new registration
            // or damage report is not routed to one particular person.
            'all_mao' => $activeUsers('mao')->all(),

            'affected_farmers' => DB::table('farmers')
                ->join('users', 'users.id', '=', 'farmers.user_id')
                ->whereIn('farmers.id', DB::table('damage_reports')->select('farmer_id'))
                ->where('users.status', 'active')
                ->pluck('users.id')
                ->all(),

            /*
             | The association's own officer accounts, and nobody else.
             |
             | This used to include every farmer whose association_id matched,
             | as well as anyone listed in association_officers, with no check
             | on what role those accounts actually had. Two things went wrong
             | with that.
             |
             | Every sender of this audience writes to an officer: "allocated
             | to your association, please distribute it to your eligible
             | members" is not an instruction an ordinary member can act on,
             | and "<name> submitted damage report DR-0004" told every member
             | of an association, by name, who had filed a report.
             |
             | And association_officers.user_id is a plain foreign key to
             | users with no role constraint, so any account sitting in that
             | table received association mail regardless of its role. That is
             | how a technician ended up reading an association's
             | notifications.
             |
             | Farmers still receive everything addressed to them directly
             | (specific_farmer, all_farmers, affected_farmers); this only
             | stops them receiving officer instructions about their
             | association.
             */
            'specific_association' => DB::table('users')
                ->where('users.status', 'active')
                ->where('users.role', 'association')
                ->whereIn('users.id', DB::table('association_officers')
                    ->where('association_id', $this->target_id)
                    ->select('user_id'))
                ->pluck('users.id')
                ->all(),

            'specific_farmer' => Farmer::where('id', $this->target_id)
                ->pluck('user_id')
                ->all(),

            'specific_technician' => User::where('id', $this->target_id)
                ->where('role', 'technician')
                ->pluck('id')
                ->all(),

            default => [],
        };
    }

    /**
     * Write one notification row per recipient and mark the alert sent.
     *
     * Urgent and Critical alerts also actually go out as SMS from here (see
     * sendSms() below) once the rows exist to record the result against.
     */
    public function dispatchToRecipients(): int
    {
        $recipients = $this->recipientIds();

        if (empty($recipients)) {
            $this->update(['status' => 'failed']);

            return 0;
        }

        $now       = now();
        $smsStatus = $this->sends_sms ? 'pending' : 'not_applicable';

        $rows = array_map(fn ($userId) => [
            'notification_broadcast_id' => $this->id,
            'user_id'                   => $userId,
            'is_read'                   => false,
            'sms_status'                => $smsStatus,
            'created_at'                => $now,
        ], $recipients);

        foreach (array_chunk($rows, 500) as $chunk) {
            Notification::insert($chunk);
        }

        $this->update(['status' => 'sent', 'sent_at' => $now]);

        if ($this->sends_sms) {
            $this->sendSms($recipients);
        }

        return count($rows);
    }

    /**
     * Hand each recipient's text to the SMS provider and record what
     * happened, one recipient at a time. Kept separate from the bulk insert
     * above so a slow or failing SMS provider never blocks the in-app
     * alert - that part is already saved and marked sent by the time this
     * runs.
     *
     * This runs inline in the request rather than on a queue. That is a
     * deliberate simplicity trade-off for a municipal office sending to at
     * most a few hundred recipients at a time (proposal section 2: keep the
     * system practical, not an enterprise platform) - if the farmer base
     * grows enough that this becomes slow, it is a straightforward move to
     * a queued job later without changing anything about how sms_status is
     * recorded.
     */
    private function sendSms(array $recipientIds): void
    {
        $sms = app(SemaphoreSmsService::class);

        $phones = User::whereIn('id', $recipientIds)->pluck('phone_number', 'id');

        // Semaphore charges per 160-character segment; trimmed so one alert
        // is one text for the common case instead of silently billing more.
        $text = '[' . strtoupper($this->priority) . '] ' . $this->title . ': ' . $this->message;
        if (mb_strlen($text) > 160) {
            $text = mb_substr($text, 0, 157) . '...';
        }

        foreach ($recipientIds as $userId) {
            $phone  = $phones->get($userId);
            $result = $phone
                ? $sms->send($phone, $text)
                : ['success' => false, 'error' => 'No phone number on file.'];

            Notification::where('notification_broadcast_id', $this->id)
                ->where('user_id', $userId)
                ->update(['sms_status' => $result['success'] ? 'sent' : 'failed']);
        }
    }
}
