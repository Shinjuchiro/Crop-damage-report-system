<?php

namespace App\Services;

use App\Models\DamageReport;
use App\Support\PlantingMatch;

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
 *
 * ONE RULE FOR "DOES IT MATCH" (Oct 2026)
 *
 * Whether a crop matches a planting record is decided by
 * App\Support\PlantingMatch, the same code the office's and the
 * association's pages use, so the technician cannot be told "matched" for
 * a report the office sees as "no matching record". This class no longer
 * has a rule of its own (it used to accept the nearest planting of the same
 * crop and only mention a date gap); it only turns that result into the
 * sentences a technician reads in the truck.
 */
class PlantingComparison
{
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

        PlantingMatch::annotate([$report]);

        $rows          = [];
        $plantedTotal  = 0.0;
        $damagedTotal  = 0.0;
        $warningCount  = 0;

        foreach ($report->planting_lines as $entry) {
            $damaged = $entry['line'];
            $match   = $entry['planted'];

            $damagedArea = (float) ($damaged->damaged_area_hectares ?? 0);
            $plantedArea = $match ? (float) $entry['planted_area'] : 0.0;

            $damagedTotal += $damagedArea;
            $plantedTotal += $plantedArea;

            $warnings = self::warningsFor($entry, $damagedArea, $plantedArea);
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
     * What is worth pointing out about this one crop.
     *
     * Each warning is a short sentence a technician can act on, not a code
     * to look up. They are phrased as observations rather than accusations:
     * the technician is going to stand in the field and find out.
     *
     * @return array<int, array{level: string, text: string}>
     */
    private static function warningsFor(array $entry, float $damagedArea, float $plantedArea): array
    {
        if ($entry['state'] === PlantingMatch::NONE) {
            return [[
                'level' => 'notice',
                'text'  => 'This farmer has no Crop Planted report on file. The farmer may have planted it '
                    . 'without recording it. Worth confirming on site.',
            ]];
        }

        if ($entry['state'] === PlantingMatch::MISMATCH) {
            return collect($entry['reasons'])->map(fn ($reason) => [
                'level' => 'notice',
                'text'  => $reason . ' It may be a different planting, or details written from memory. '
                    . 'Worth confirming on site.',
            ])->all();
        }

        if ($entry['area_flag']) {
            return [[
                'level' => 'alert',
                'text'  => 'The damaged area is larger than the area the farmer recorded planting ('
                    . rtrim(rtrim(number_format($damagedArea, 2), '0'), '.') . ' ha reported damaged against '
                    . rtrim(rtrim(number_format($plantedArea, 2), '0'), '.') . ' ha planted). Measure this one.',
            ]];
        }

        return [];
    }
}
