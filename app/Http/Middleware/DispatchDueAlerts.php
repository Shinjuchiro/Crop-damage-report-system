<?php

namespace App\Http\Middleware;

use App\Models\NotificationBroadcast;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends scheduled alerts whose time has come, without needing a scheduler.
 *
 * An alert the MAO schedules is only a row with status 'scheduled'; something
 * has to send it when its time arrives. routes/console.php registers the
 * alerts:dispatch-scheduled command, but that only runs if a cron job or
 * `php artisan schedule:work` is running, and on a plain Laragon or Railway
 * setup neither is. This checks at most once a minute, on ordinary page
 * loads, so a scheduled announcement still goes out within about a minute of
 * its time as long as anyone is using the system. It never blocks or breaks
 * the page: any failure is swallowed.
 */
class DispatchDueAlerts
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (Cache::add('alerts-due-check', true, 55)) {
                NotificationBroadcast::dispatchDue();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }
}
