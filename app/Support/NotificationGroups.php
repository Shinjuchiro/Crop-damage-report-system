<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Splits a newest-first list of notifications into the groups the bell panel
 * and the full Notifications page both show: Today, Yesterday, Earlier This
 * Week (the rest of the last seven days), then Older. Empty groups are left out.
 */
class NotificationGroups
{
    public const LABELS = ['Today', 'Yesterday', 'Earlier This Week', 'Older'];

    /** @return array<string, Collection> label => notifications, in display order */
    public static function group(iterable $items): array
    {
        $today     = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $weekStart = $today->copy()->subDays(6);   // the last seven days, so Sunday's notice is not "Older" on Tuesday

        $groups = collect(self::LABELS)->mapWithKeys(fn ($label) => [$label => collect()])->all();

        foreach ($items as $row) {
            $day = $row->created_at?->copy()->startOfDay();

            $label = match (true) {
                ! $day                    => 'Older',
                $day->equalTo($today)     => 'Today',
                $day->equalTo($yesterday) => 'Yesterday',
                $day->gte($weekStart)     => 'Earlier This Week',
                default                   => 'Older',
            };

            $groups[$label]->push($row);
        }

        return array_filter($groups, fn ($rows) => $rows->isNotEmpty());
    }
}
