<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\Disaster;
use Illuminate\Support\Facades\Auth;

/**
 * Add, correct, or remove which disaster events a damage report is linked
 * to - a real sync(), not an append-only log (see migration 2024_01_09_000001
 * and decision 19).
 *
 * This used to live only in Technician\InspectionController, written so a
 * technician could fix a report's disaster links during their own field
 * inspection. That design left a real gap: the technician can only touch
 * these links while the inspection is still open
 * (InspectionController::edit() refuses once validated_at is set), so a
 * disaster the office declares only AFTER a report has already been
 * verified - a sudden drought, say, declared days after farmers already
 * reported and a technician already signed off - could never be linked by
 * anyone. And per AssistanceAllocationController::qualifiedReports(), a
 * report with no link to the target disaster can never count as a
 * qualified beneficiary for it, so MAO would be stuck allocating drought
 * assistance to an association with zero eligible members even though the
 * damage was real and verified.
 *
 * Pulling this into a trait lets MAO\DamageReportMonitorController offer
 * the same correction from a verified report's own details page, tagging
 * every newly-added link with the role passed in (rather than always
 * 'technician') so the attribution trail stays honest about who actually
 * linked it.
 */
trait SyncsDisasterLinks
{
    private function syncDisasterLinks(DamageReport $report, array $disasterIds, string $role): void
    {
        $disasterIds = array_map('intval', $disasterIds);

        $existing = $report->disasters()->get()->keyBy('id');
        $before   = $existing->keys()->all();

        sort($before);
        $sortedNew = $disasterIds;
        sort($sortedNew);

        if ($before === $sortedNew) {
            return;   // nothing actually changed, nothing to log
        }

        $syncData = [];

        foreach ($disasterIds as $id) {
            $syncData[$id] = $existing->has($id)
                // Kept: carry its existing attribution over unchanged.
                ? [
                    'linked_by'      => $existing[$id]->pivot->linked_by,
                    'linked_by_role' => $existing[$id]->pivot->linked_by_role,
                    'created_at'     => $existing[$id]->pivot->created_at,
                ]
                // New: whoever is making this call just linked it.
                : [
                    'linked_by'      => Auth::id(),
                    'linked_by_role' => $role,
                    'created_at'     => now(),
                ];
        }

        $report->disasters()->sync($syncData);

        $added   = Disaster::whereIn('id', array_diff($disasterIds, $before))->pluck('name');
        $removed = Disaster::whereIn('id', array_diff($before, $disasterIds))->pluck('name');

        $summary = collect([
            $added->isNotEmpty()   ? 'linked ' . $added->join(', ')     : null,
            $removed->isNotEmpty() ? 'unlinked ' . $removed->join(', ') : null,
        ])->filter()->join('; ');

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'Updated disaster events on ' . $report->reference . ': ' . $summary,
            'target_table' => 'damage_report_disasters',
            'target_id'    => $report->id,
            'created_at'   => now(),
        ]);
    }
}
