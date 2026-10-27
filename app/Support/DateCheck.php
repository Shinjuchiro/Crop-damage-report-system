<?php

namespace App\Support;

use App\Models\DamageReport;
use Illuminate\Support\Carbon;

/**
 * The official disaster date of a damage report, for display.
 *
 * The official disaster date is the date_start of the disaster event the
 * report is linked to (an event a Disaster Advisory created, or one the MAO
 * entered in Disaster Management). When a report links several events, the one
 * marked is_primary is the main event; with several and none marked, there is
 * no single date to show.
 *
 * NO FLAGGING (Oct 2026, client decision)
 *
 * Reports are never marked, queued or warned about because of their dates.
 * Invalid dates are stopped when a NEW report is made (the calendar and the
 * server both refuse a planting date that is not before the disaster date and
 * a damage date that is not after it, DamageReportController). Reports saved
 * earlier are kept exactly as they are. This class only works out the date to
 * show.
 */
class DateCheck
{
    /**
     * The official disaster date for a report: the start date of its ONE linked
     * event, or of its main event when several are linked. Null when it has
     * none, or when it has several and none is marked as the main one.
     */
    public static function officialDate(DamageReport $report): ?Carbon
    {
        $report->loadMissing('disasters');

        if ($report->disasters->count() === 1) {
            return $report->disasters->first()->date_start;
        }

        return $report->disasters->first(fn ($d) => (bool) $d->pivot->is_primary)?->date_start;
    }

    /** Sets $report->official_date on each report, for display. */
    public static function annotate(iterable $reports): void
    {
        foreach (collect($reports) as $report) {
            $report->official_date = self::officialDate($report);
        }
    }
}
