<?php

namespace App\Services;

use App\Models\CropPlantingRecordCrop;
use App\Models\DamageReport;

/**
 * PLANTED AGAINST DAMAGED
 *
 * Lines up every crop on a damage report against what the farmer actually
 * recorded planting, and says where the two do not agree.
 *
 * The technician sees this before they start an inspection, so they walk
 * onto the farm already knowing what to look at: a farmer who reports two
 * hectares of corn damaged but only recorded planting one is not
 * necessarily lying, but it is the first thing to check on site.
 *
 * What this is NOT: an eligibility test. Proposal section 62 is deliberate
 * about this. A crop with no planting record on file is still fully
 * available to inspect, and nothing here blocks, rejects or scores a
 * report. Every warning below is a "look at this", never a verdict. The
 * technician's own findings are what decide the outcome, recorded on the
 * inspection form afterwards.
 *
 * Used by the Validation briefing page, and again on the Inspection
 * History detail page, where the same comparison is shown as part of the
 * record of what the technician was looking at when they went out.
 */
class PlantingComparison
{
    /**
     * How far the planting date on the damage report can sit from the date
     * on the planting record before it is worth mentioning.
     *
     * A week, because farmers fill the damage report in from memory, often
     * days after a typhoon, and "around the middle of June" is a perfectly
     * honest answer. A month apart is a different matter.
     */
    private const DATE_GAP_DAYS = 7;

    /**
     * Small tolerance on the area check, so floating point and honest
     * rounding ("1.5 ha" written down as "1.50") never raise a warning.
     */
    private const AREA_TOLERANCE = 0.01;

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     planted_total: float,
     *     damaged_total: float,
     *     warning_count: int,
     * }
     */
    public static function build(DamageReport $report): array
    {
        $report->loadMissing(['crops.crop']);

        /*
         | Every crop this farmer has on file, fetched once rather than one
         | query per crop on the report. Archived planting records are left
         | out: an archived record is one the farmer or the office has taken
         | out of the working system, so it should not be used as evidence
         | for or against anything.
         |
         | Only records dated on or before the report itself can be
         | relevant. A crop planted AFTER the damage was reported cannot be
         | the crop that was damaged.
         */
        $planted = CropPlantingRecordCrop::query()
            ->whereHas('plantingRecord', fn ($q) => $q
                ->where('farmer_id', $report->farmer_id)
                ->whereNull('archived_at'))
            ->where('date_planted', '<=', $report->created_at)
            ->with(['crop', 'plantingRecord'])
            ->orderByDesc('date_planted')
            ->get();

        $rows          = [];
        $plantedTotal  = 0.0;
        $damagedTotal  = 0.0;
        $warningCount  = 0;

        foreach ($report->crops as $damaged) {
            $match = self::matchFor($damaged, $planted);

            $damagedArea = (float) ($damaged->damaged_area_hectares ?? 0);
            $plantedArea = $match ? (float) ($match->area_hectares ?? 0) : 0.0;

            $damagedTotal += $damagedArea;
            $plantedTotal += $plantedArea;

            $warnings = self::warningsFor($damaged, $match, $damagedArea, $plantedArea);
            $warningCount += count($warnings);

            $rows[] = [
                'label'          => $damaged->crop_specify ?: ($damaged->crop?->name ?? 'Unnamed crop'),
                'damaged_area'   => $damagedArea,
                'damaged_date'   => $damaged->date_planted,
                'damage_percent' => $damaged->estimated_damage_percent,
                'planted_area'   => $match ? $plantedArea : null,
                'planted_date'   => $match?->date_planted,
                'recorded_on'    => $match?->plantingRecord?->date_submitted,
                'matched'        => (bool) $match,
                'warnings'       => $warnings,
            ];
        }

        return [
            'rows'          => $rows,
            'planted_total' => $plantedTotal,
            'damaged_total' => $damagedTotal,
            'warning_count' => $warningCount,
        ];
    }

    /**
     * The planting record this damaged crop most likely refers to.
     *
     * Matched on the crop first. Where the farmer picked a crop from the
     * list both sides have a crop_id and that is an exact match; where they
     * typed their own ("Other"), the typed text is compared instead, case
     * and surrounding spaces ignored, because "Ampalaya" and "ampalaya "
     * are the same crop.
     *
     * When a farmer has planted the same crop more than once, the record
     * whose planting date sits closest to the date written on the damage
     * report wins. That is the fairest reading of which planting the farmer
     * meant. With no date on the report, the most recent planting is used,
     * which is what $planted is already ordered by.
     */
    private static function matchFor($damaged, $planted)
    {
        $candidates = $planted->filter(function ($row) use ($damaged) {
            if ($damaged->crop_id && $row->crop_id) {
                return $row->crop_id === $damaged->crop_id;
            }

            $a = mb_strtolower(trim((string) $damaged->crop_specify));
            $b = mb_strtolower(trim((string) $row->crop_specify));

            return $a !== '' && $a === $b;
        });

        if ($candidates->isEmpty()) {
            return null;
        }

        if (! $damaged->date_planted) {
            return $candidates->first();
        }

        return $candidates
            ->sortBy(fn ($row) => abs($row->date_planted->diffInDays($damaged->date_planted)))
            ->first();
    }

    /**
     * What is worth pointing out about this one crop.
     *
     * Each warning is a short sentence a technician can act on, not a code
     * to look up. They are phrased as observations rather than accusations:
     * the technician is going to stand in the field and find out.
     *
     * @return array<int, array{level: string, text: string}>
     */
    private static function warningsFor($damaged, $match, float $damagedArea, float $plantedArea): array
    {
        $warnings = [];

        if (! $match) {
            return [[
                'level' => 'notice',
                'text'  => 'No planting record on file for this crop. The farmer may have planted it '
                    . 'without recording it. Worth confirming on site.',
            ]];
        }

        if ($damagedArea > $plantedArea + self::AREA_TOLERANCE) {
            $warnings[] = [
                'level' => 'alert',
                'text'  => 'The damaged area is larger than the area the farmer recorded planting ('
                    . rtrim(rtrim(number_format($damagedArea, 2), '0'), '.') . ' ha reported damaged against '
                    . rtrim(rtrim(number_format($plantedArea, 2), '0'), '.') . ' ha planted). Measure this one.',
            ];
        }

        if ($damaged->date_planted && $match->date_planted) {
            $gap = (int) abs($match->date_planted->diffInDays($damaged->date_planted));

            if ($gap > self::DATE_GAP_DAYS) {
                $warnings[] = [
                    'level' => 'notice',
                    'text'  => 'The planting date on the damage report is ' . $gap . ' days from the date on '
                        . 'the planting record. It may be a different planting, or a date written from memory.',
                ];
            }
        }

        return $warnings;
    }
}
