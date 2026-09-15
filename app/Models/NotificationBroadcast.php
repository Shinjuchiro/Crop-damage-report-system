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

    protected $table = 'notification_broadcasts';

    protected $fillable = [
        'title', 'message', 'category', 'priority',
        'target_type', 'target_id', 'attachment_path', 'status',
        'scheduled_for', 'created_by', 'sent_at',
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

            'specific_association' => DB::table('users')
                ->where('users.status', 'active')
                ->where(function ($query) {
                    $query->whereIn('users.id', DB::table('farmers')
                            ->where('association_id', $this->target_id)
                            ->select('user_id'))
                        ->orWhereIn('users.id', DB::table('association_officers')
                            ->where('association_id', $this->target_id)
                            ->select('user_id'));
                })
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
