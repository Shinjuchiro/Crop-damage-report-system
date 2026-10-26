<?php

namespace App\Support;

use App\Models\CropPlantingRecord;
use App\Models\CropPlantingRecordCrop;
use Illuminate\Support\Collection;

/**
 * Does a damage report have a matching Crop Planted record?
 *
 * THE ONE RULE (Oct 2026)
 *
 * This is the only place in the system that decides whether a damage report
 * matches a planting record. The office's Crop Damage and Validation pages,
 * the association's pages, the technician's briefing and the technician's
 * assignment list all read from it, so the same report can no longer be
 * "Matched" on one screen and "no record" on another.
 *
 * A damage-report crop LINE matches when the same farmer has a planting
 * record (not archived, not deleted) with the same crop type and the same
 * date planted. The date compared is the planting date the farmer wrote on
 * the damage report, never the day the report was submitted. Planting
 * records are not tied to a plot in this system, so a plot or farm is not
 * part of the rule: nothing unavailable is required to match.
 *
 * THREE RESULTS FOR A REPORT
 *
 *   matched   every crop line has a matching planting record
 *   mismatch  the farmer HAS planting records, but at least one crop line
 *             has none that matches (different crop, different date)
 *   none      the farmer has no planting records at all
 *
 * NONE OF THEM REJECTS ANYTHING
 *
 * A missing match never stops a report being filed, never rejects it and
 * never disqualifies the farmer from assistance. A farmer may simply not
 * have recorded the planting, or wrote the date from memory; the technician
 * checks it in the field. Disagreements are carried as reasons and flags for
 * the technician to look at.
 *
 * It is worked out from the data every time it is shown, never stored. A
 * farmer who records the planting AFTER filing the report turns the report
 * to Matched by themselves, nothing is duplicated, and there is no stored
 * result to fall out of step.
 */
class PlantingMatch
{
    public const MATCHED  = 'matched';
    public const MISMATCH = 'mismatch';
    public const NONE     = 'none';

    /** Honest rounding ("1.5" written down as "1.50") never raises a flag. */
    private const AREA_TOLERANCE = 0.01;

    public static function label(?string $state): string
    {
        return match ($state) {
            self::MATCHED  => 'Matching Record Found',
            self::MISMATCH => 'No Matching Planting Record',
            default        => 'No Planting Record Found',
        };
    }

    /**
     * Fill in, on each report:
     *
     *   planting_match    'matched', 'mismatch' or 'none' (see above)
     *   planting_lines    one entry per damage-report crop line:
     *                       line          the DamageReportCrop
     *                       planted       the first planting crop row that
     *                                     matched it, or null
     *                       matches       every planting crop row that matched
     *                       state         'matched', 'mismatch' or 'none'
     *                       reasons       why it did not match (strings)
     *                       planted_area  total hectares across the matches
     *                       area_flag     damaged area larger than planted area
     *   planting_records  how many planting records the farmer has on file
     *   planting_flags    how many lines carry an area flag
     *
     * One pair of queries for the whole list. Needs each report's `crops`
     * (and ideally `crops.crop`) loaded.
     */
    public static function annotate(iterable $reports): void
    {
        $reports = collect($reports);

        if ($reports->isEmpty()) {
            return;
        }

        $farmerIds = $reports->pluck('farmer_id')->unique()->values()->all();

        $recordCounts = CropPlantingRecord::query()
            ->active()
            ->whereIn('farmer_id', $farmerIds)
            ->selectRaw('farmer_id, COUNT(*) as total')
            ->groupBy('farmer_id')
            ->pluck('total', 'farmer_id');

        $planted = CropPlantingRecordCrop::query()
            ->with(['crop', 'plantingRecord'])
            ->whereHas('plantingRecord', fn ($record) => $record
                ->whereIn('farmer_id', $farmerIds)
                ->whereNull('archived_at')
                ->whereNull('deleted_at'))
            ->get()
            ->groupBy(fn ($row) => $row->plantingRecord->farmer_id);

        foreach ($reports as $report) {
            $mine       = $planted->get($report->farmer_id, collect());
            $hasRecords = (int) ($recordCounts[$report->farmer_id] ?? 0) > 0;

            $lines = $report->crops->map(fn ($line) => self::evaluateLine($line, $mine, $hasRecords));

            $report->planting_lines   = $lines;
            $report->planting_records = (int) ($recordCounts[$report->farmer_id] ?? 0);
            $report->planting_flags   = $lines->where('area_flag', true)->count();

            if (! $hasRecords) {
                $report->planting_match = self::NONE;
            } elseif ($lines->isNotEmpty() && $lines->every(fn ($row) => $row['state'] === self::MATCHED)) {
                $report->planting_match = self::MATCHED;
            } else {
                $report->planting_match = self::MISMATCH;
            }
        }
    }

    /**
     * Search ALL of the farmer's planting rows before saying nothing matches
     * (a non-matching row never stops the search), and describe the gap when
     * there is one.
     */
    private static function evaluateLine($line, Collection $mine, bool $hasRecords): array
    {
        $matches = $mine->filter(fn ($row) => self::sameCrop($line, $row) && self::sameDate($line, $row))
            ->values();

        if ($matches->isNotEmpty()) {
            $plantedArea = (float) $matches->sum('area_hectares');
            $damagedArea = (float) ($line->damaged_area_hectares ?? 0);

            return [
                'line'         => $line,
                'planted'      => $matches->first(),
                'matches'      => $matches,
                'state'        => self::MATCHED,
                'reasons'      => [],
                'planted_area' => $plantedArea,
                // Damage can cover only part of a planting, so smaller is
                // normal. Larger is the one inconsistency worth a look.
                'area_flag'    => $damagedArea > $plantedArea + self::AREA_TOLERANCE,
            ];
        }

        return [
            'line'         => $line,
            'planted'      => null,
            'matches'      => collect(),
            'state'        => $hasRecords ? self::MISMATCH : self::NONE,
            'reasons'      => $hasRecords ? self::reasonsFor($line, $mine) : [],
            'planted_area' => null,
            'area_flag'    => false,
        ];
    }

    /**
     * Why a line found nothing, in words a person can act on. Never worded
     * as an accusation: a date mismatch only means no record matches the
     * reported details, not that the crop was not planted.
     *
     * @return array<int, string>
     */
    private static function reasonsFor($line, Collection $mine): array
    {
        $name = $line->crop_specify ?: ($line->crop?->name ?? 'this crop');
        $date = $line->date_planted?->format('M d, Y');

        if (! $date) {
            return ['The report gives no planting date for ' . $name . ', so it cannot be compared.'];
        }

        $sameCrop = $mine->filter(fn ($row) => self::sameCrop($line, $row));

        if ($sameCrop->isNotEmpty()) {
            $dates = $sameCrop->map(fn ($row) => $row->date_planted?->format('M d, Y'))
                ->filter()->unique()->take(3)->implode(', ');

            return ['Different planting date: the planting records for ' . $name . ' show ' . $dates
                . ', but the report says ' . $date . '.'];
        }

        $others = $mine->map(fn ($row) => $row->crop_specify ?: $row->crop?->name)
            ->filter()->unique()->take(4)->implode(', ');

        return ['Different crop type: the planting records are for '
            . ($others !== '' ? $others : 'other crops') . ', not ' . $name . '.'];
    }

    private static function sameCrop($line, $row): bool
    {
        $a = mb_strtolower(trim((string) $line->crop_specify));
        $b = mb_strtolower(trim((string) $row->crop_specify));

        if ($line->crop_id && $row->crop_id) {
            // Same listed crop. When both also carry typed text ("Other"),
            // the text has to agree as well.
            return (int) $line->crop_id === (int) $row->crop_id
                && ($a === '' || $b === '' || $a === $b);
        }

        return $a !== '' && $a === $b;
    }

    private static function sameDate($line, $row): bool
    {
        return $line->date_planted !== null
            && $row->date_planted !== null
            && $line->date_planted->toDateString() === $row->date_planted->toDateString();
    }

    /**
     * The damage reports each crop of a planting record backs up, keyed by
     * the planting crop's id: the same rule read from the planting's side
     * (same farmer, same crop type, same date planted). It lets a reader of
     * a planting see whether anything was later reported damaged.
     *
     * Only the record's own farmer's live reports are looked at, so this
     * can never reach another farmer's data.
     */
    public static function damageFor(CropPlantingRecord $record): Collection
    {
        $reports = \App\Models\DamageReport::query()
            ->active()
            ->where('farmer_id', $record->farmer_id)
            ->with('crops')
            ->get();

        return $record->crops->mapWithKeys(fn ($planted) => [
            $planted->id => $reports->filter(fn ($report) => $report->crops->contains(
                fn ($line) => self::sameCrop($line, $planted) && self::sameDate($line, $planted)
            ))->values(),
        ]);
    }

    /**
     * SQL fragment: this damage_report_crops row has NO matching planting
     * row. Used inside whereHas('crops', ...) by the scopes on DamageReport,
     * and written to agree with sameCrop()/sameDate() above.
     */
    public static function unmatchedLineSql(): string
    {
        return 'NOT EXISTS (
            SELECT 1 FROM crop_planting_record_crops pc
            JOIN crop_planting_records pr ON pr.id = pc.crop_planting_record_id
            JOIN damage_reports dr ON dr.id = damage_report_crops.damage_report_id
            WHERE pr.farmer_id = dr.farmer_id
              AND pr.archived_at IS NULL
              AND pr.deleted_at IS NULL
              AND pc.crop_id = damage_report_crops.crop_id
              AND pc.date_planted = damage_report_crops.date_planted
              AND (COALESCE(TRIM(pc.crop_specify), \'\') = \'\'
                   OR COALESCE(TRIM(damage_report_crops.crop_specify), \'\') = \'\'
                   OR LOWER(TRIM(pc.crop_specify)) = LOWER(TRIM(damage_report_crops.crop_specify)))
        )';
    }

    /** SQL fragment: this damage report's farmer has at least one live planting record. */
    public static function farmerHasRecordsSql(): string
    {
        return 'EXISTS (
            SELECT 1 FROM crop_planting_records pr2
            WHERE pr2.farmer_id = damage_reports.farmer_id
              AND pr2.archived_at IS NULL
              AND pr2.deleted_at IS NULL
        )';
    }
}
