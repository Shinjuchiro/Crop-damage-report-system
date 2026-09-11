<?php

namespace App\Console\Commands;

use App\Models\NotificationBroadcast;
use Illuminate\Console\Command;

/**
 * Sends any alert whose scheduled time has arrived.
 *
 * Register it in routes/console.php so it runs regularly, for example:
 *     Schedule::command('alerts:dispatch-scheduled')->everyMinute();
 * and keep `php artisan schedule:work` running during a demo.
 */
class DispatchScheduledAlerts extends Command
{
    protected $signature = 'alerts:dispatch-scheduled';

    protected $description = 'Send scheduled MAO alerts whose time has come';

    public function handle(): int
    {
        $due = NotificationBroadcast::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled alerts are due.');

            return self::SUCCESS;
        }

        foreach ($due as $alert) {
            $sent = $alert->dispatchToRecipients();

            $sent === 0
                ? $this->warn("Alert #{$alert->id} matched nobody and was marked failed.")
                : $this->info("Alert #{$alert->id} sent to {$sent} recipients.");
        }

        return self::SUCCESS;
    }
}
