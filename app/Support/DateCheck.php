<?php

namespace App\Support;

use App\Models\DamageReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Do a damage report's dates agree with the official disaster date?
 *
 * THE TWO RULES
 *
 * The official disaster date is the date_start of the disaster event the
 * report is linked to (an event a Disaster Advisory created, or one the MAO
 * entered in Disaster Management). Against it:
 *
 *   planting date     must be BEFORE the disaster date (strictly earlier)
 *   crop damage date  must be AFTER the disaster date (strictly later)
 *
 * The disaster date itself fails both. Only the recorded disaster date is
 * used: never today's date, nor when an advisory was written or sent.
 *
 * WHICH DISASTER: THE MAIN EVENT
 *
 * A report links to its disasters through damage_report_disasters, which can
 * hold several events. When it has one, that is the disaster. When it has
 * several, the one marked is_primary (chosen by the farmer on the form, or by
 * the office when it corrects the links) is the main event and the dates are
 * checked against it. No date is ever picked arbitrarily: with several events
 * and none marked, the dates are not matched to any of them and the report is
 * flagged for the MAO to look at instead. It is still accepted.
 *
 * NEW SUBMISSIONS ARE PREVENTED, OLD ONES ARE FLAGGED
 *
 * The form and DamageReportController refuse a typed date that breaks a rule.
 * A report that already exists, or one built from a Crop Planted record whose
 * date breaks the rule, is never rejected and never edited. It is kept
 * exactly as it was and this class reports what does not agree, so the MAO
 * can review it. It is worked out when shown, never stored, so correcting a
 * disaster date in Disaster Management corrects every flag at once.
 *
 * A report with no linked event has no disaster date to check against, so it
 * has no flags from this class (the only check left is that the damage date
 * is not before the planting date).
 */
class DateCheck
{
    public const CONSISTENT  = 'consistent';
    public const REVIEW      = 'review';       // flagged, nobody has reviewed it yet
    public const REVIEWED    = 'reviewed';     // flagged, and the MAO has reviewed it
    public const NOT_CHECKED = 'not_checked';

    public static function label(?string $state): string
    {
        return match ($state) {
            self::REVIEW      => 'Dates Need Review',
            self::REVIEWED    => 'Dates Reviewed',
            self::CONSISTENT  => 'Dates Consistent',
            default           => 'Not Checked',
        };
    }

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

    /**
     * Annotate each report with:
     *
     *   official_date   Carbon|null   the date the rules are checked against
     *   date_flags      Collection    plain-language reasons, empty when fine
     *   date_check      'consistent' | 'review' | 'not_checked'
     */
    public static function annotate(iterable $reports): void
    {
        $reports = collect($reports);

        if ($reports->isEmpty()) {
            return;
        }

        $reports->each(fn ($report) => $report->loadMissing('disasters'));

        /* NO FLAGGING (Oct 2026, client decision)
           Reports are no longer marked or queued for review because of their
           dates. Invalid dates are stopped when a NEW report is made (the
           calendar and the server both refuse them), and reports saved earlier
           are simply kept as they are. Only the official date is worked out,
           for display. */
        foreach ($reports as $report) {
            $official = self::officialDate($report);

            $report->official_date     = $official;
            $report->date_flags        = collect();
            $report->date_review       = null;
            $report->date_review_stale = false;
            $report->date_check        = $official !== null ? self::CONSISTENT : self::NOT_CHECKED;
        }
    }

    private static function cropName($line): string
    {
        return $line->crop_specify ?: ($line->crop?->name ?? 'a crop');
    }

    /**
     * The same test as a SQL condition, for list filters: reports linked to several
     * events with no main one chosen, or whose official event date a planting date is
     * not before or a damage date is not after.
     * Applied through DamageReport::scopeDateReview().
     */
    public static function reviewSql(): string
    {
        // The report's official date: its one linked event, or its main event
        // when several are linked. NULL when several and none is marked.
        $official = '(COALESCE(
                        (SELECT MIN(dd.date_start) FROM damage_report_disasters rd
                           JOIN disasters dd ON dd.id = rd.disaster_id
                           WHERE rd.damage_report_id = damage_reports.id
                           HAVING COUNT(*) = 1),
                        (SELECT MIN(dp.date_start) FROM damage_report_disasters rp
                           JOIN disasters dp ON dp.id = rp.disaster_id
                           WHERE rp.damage_report_id = damage_reports.id AND rp.is_primary = 1)
                     ))';

        $unmatched = '((SELECT COUNT(*) FROM damage_report_disasters rm
                         WHERE rm.damage_report_id = damage_reports.id) > 1
                       AND NOT EXISTS (SELECT 1 FROM damage_report_disasters rn
                         WHERE rn.damage_report_id = damage_reports.id AND rn.is_primary = 1))';

        return "({$unmatched} OR ({$official} IS NOT NULL AND (
                    EXISTS (SELECT 1 FROM damage_report_crops dc
                            WHERE dc.damage_report_id = damage_reports.id AND dc.date_planted >= {$official})
                    OR damage_reports.date_damaged <= {$official}
                )))";
    }
}
