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
        $count = NotificationBroadcast::dispatchDue();

        $this->info($count === 0
            ? 'No scheduled alerts are due.'
            : "{$count} scheduled alert(s) processed.");

        return self::SUCCESS;
    }
}
