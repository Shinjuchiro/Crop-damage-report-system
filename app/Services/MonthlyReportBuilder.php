<?php

namespace App\Services;

use App\Models\AssistanceAllocation;
use App\Models\AssistanceAllocationBeneficiary;
use App\Models\AssistanceDistribution;
use App\Models\Barangay;
use App\Models\CropPlantingRecord;
use App\Models\DamageReport;
use App\Models\Farmer;
use App\Models\Validation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds every figure in proposal section 75 ("Monthly Report Content") for
 * one calendar month, straight from the live tables. Nothing here is cached:
 * the same array feeds the on-screen report, the PDF and the Excel export,
 * so the three can never disagree with each other.
 *
 * Each section filters on the date column that actually means "this
 * happened in the selected month" for that kind of record: farmers on
 * registration date, plantings on the date submitted, damage on the date
 * the report was filed, verification on assignment/validation dates,
 * assistance on allocation/distribution dates. A few figures are
 * point-in-time snapshots instead (Total Farmers, Active/Inactive, farm
 * area) because a report generated today should show where things stand
 * today, not just what changed in the chosen month — they are noted below.
 *
 * ARCHIVED AND DELETED RECORDS ARE EXCLUDED (Oct 2026)
 *
 * Every figure and every listing here now skips records the office has
 * archived or soft-deleted. It did not before, and the effect was a report
 * that disagreed with the screens it was supposed to summarise: archive a
 * damage report filed twice by mistake and the Crop Damage list dropped to
 * 19 while the month's PDF went on saying 20.
 *
 * The decision is deliberate and worth stating, because the other answer is
 * arguable: a report filed in September did happen in September, and an
 * office that wants its printed history frozen would count it. This system
 * takes the consistent view instead. The report is a summary of the records
 * that still stand, so it matches what the office and the associations see
 * on screen, and no one has to explain why two numbers for the same month
 * differ depending on where they read it.
 *
 * In practice that is CropPlantingRecord::active() and
 * DamageReport::active() (both columns, see Concerns\Archivable),
 * Farmer::notDeleted(), and self::inspections() for validations, which have
 * no archived state of their own and inherit their report's. The damage and
 * planting summaries filter the id set they are all built from, so the
 * breakdowns by barangay, crop, cause, disaster, status and severity follow
 * without each needing its own filter. Assistance allocations and
 * distributions have no archived state at all: goods that were handed over
 * were handed over.
 *
 * Pass $associationId to scope every figure to one Farmers' Association's
 * own members only - this is what Association\ReportController uses so an
 * officer's Monthly Report never shows another association's (or the
 * office's whole) numbers. Leave it null for MAO's own office-wide report.
 * A farmer's own association_id (App\Models\Farmer) is the source of truth
 * for "belongs to this association" everywhere below; records that are not
 * directly on the farmers table (damage reports, planting records,
 * inspections, distributions) are scoped by following the relation back to
 * their farmer.
 */
class MonthlyReportBuilder
{
    /** The report types a person can choose. 'all' is every one of them plus the farmer and farm summaries. */
    public const TYPES = [
        'planting'   => 'Crop Planting',
        'damage'     => 'Crop Damage',
        'validation' => 'Validation / Inspections',
        'assistance' => 'Assistance',
    ];

    /**
     * Which of the four types were chosen. Nothing chosen, or 'all' among
     * them, means everything.
     *
     * @return array<int, string>
     */
    public static function normalizeTypes(?array $types): array
    {
        $types = array_values(array_intersect(array_keys(self::TYPES), $types ?? []));

        return $types === [] ? array_keys(self::TYPES) : $types;
    }

    public static function forPeriod(int $year, int $month, ?int $associationId = null, ?array $types = null): array
    {
        $everything = $types === null || $types === [] || in_array('all', $types, true);
        $types      = $everything ? array_keys(self::TYPES) : self::normalizeTypes($types);
        // Section 22's inactivity rule: Active/Inactive Farmers below is a
        // point-in-time snapshot, so it has to be current as of the moment
        // the report is generated, not whenever a farmer last happened to
        // open their own dashboard - see Farmer::sweepInactive().
        Farmer::sweepInactive();

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = Carbon::create($year, $month, 1)->endOfMonth();

        return [
            'year'         => $year,
            'month'        => $month,
            'period_label' => $start->format('F Y'),
            'generated_at' => now(),
            'farmer'       => self::farmerSummary($start, $end, $associationId),
            'farm'         => self::farmSummary($associationId),
            'planting'     => self::plantingSummary($start, $end, $associationId),
            'damage'       => self::damageSummary($start, $end, $associationId),
            'verification' => self::verificationSummary($start, $end, $associationId),
            'assistance'   => self::assistanceSummary($start, $end, $associationId),

            // The records behind every figure above, named. See records().
            'records'      => self::filterRecords(self::records($start, $end, $associationId), $types, $everything),

            // What this report was asked to contain.
            'types'        => $types,
            'everything'   => $everything,
            'type_label'   => $everything ? 'All' : collect($types)->map(fn ($t) => self::TYPES[$t])->join(', '),
            'show'         => [
                'farmer'       => $everything,
                'farm'         => $everything,
                'planting'     => in_array('planting', $types, true),
                'damage'       => in_array('damage', $types, true),
                'verification' => in_array('validation', $types, true),
                'assistance'   => in_array('assistance', $types, true),
            ],
        ];
    }

    /** Keep only the listings that belong to the chosen types. */
    private static function filterRecords(array $records, array $types, bool $everything): array
    {
        $keep = [];
        if ($everything) {
            $keep[] = 'farmers';
        }
        in_array('planting', $types, true)   && $keep[] = 'planting';
        in_array('damage', $types, true)     && $keep[] = 'damage';
        in_array('validation', $types, true) && $keep[] = 'inspections';
        in_array('assistance', $types, true) && array_push($keep, 'allocations', 'distributions');

        return array_intersect_key($records, array_flip($keep));
    }

    /* ================= The records themselves =================
     |
     | WHY THIS EXISTS
     |
     | Everything above this point is a count. "Total Damage Reports: 24" is
     | the right thing on a cover page and the wrong thing on its own: the
     | office cannot act on it, cannot check it, and cannot answer "which
     | twenty-four?" Proposal section 76 asks for exactly this - separate
     | sheets for Damage Reports, Planting Activities, Technician
     | Inspections, Assistance and Distribution - and until now the export
     | wrote one sheet per SUMMARY instead, so no name appeared anywhere in
     | the whole document.
     |
     | Every row here names the person. Who registered, who planted what,
     | who reported damage and what it cost them, which technician inspected
     | it and what they found, who was allocated assistance and who signed
     | for it.
     |
     | WHAT A ROW IS
     |
     | Plain arrays of scalars, not models. The PDF, the Excel workbook and
     | the on-screen report all read the same rows, so the three can never
     | disagree, and a spreadsheet cell cannot hold an Eloquent object
     | anyway. Each listing is [headings, rows] so a sheet or a table can be
     | written without the writer knowing what it is looking at.
     |
     | THE SAME SCOPING AS EVERY FIGURE ABOVE
     |
     | Same date column per record type, same $associationId rule: a farmer's
     | own association_id is the source of truth, and records that do not
     | carry one are scoped by following the relation back to their farmer.
     | A listing that disagreed with the count above it would be worse than
     | no listing at all.
     */
    private static function records(Carbon $start, Carbon $end, ?int $associationId): array
    {
        return [
            'farmers'       => self::farmerRecords($start, $end, $associationId),
            'planting'      => self::plantingRecords($start, $end, $associationId),
            'damage'        => self::damageRecords($start, $end, $associationId),
            'inspections'   => self::inspectionRecords($start, $end, $associationId),
            'allocations'   => self::allocationRecords($start, $end, $associationId),
            'distributions' => self::distributionRecords($start, $end, $associationId),
        ];
    }

    /** Farmers who registered during the month. */
    private static function farmerRecords(Carbon $start, Carbon $end, ?int $associationId): array
    {
        $rows = Farmer::notDeleted()
            ->with(['user', 'barangay', 'association', 'mainCrops.crop'])
            ->whereBetween('created_at', [$start, $end])
            ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
            ->orderBy('last_name')->orderBy('first_name')
            ->get()
            ->map(fn ($farmer) => [
                $farmer->full_name,
                $farmer->user?->phone_number ?: '-',
                $farmer->barangay?->name ?? '-',
                $farmer->association?->name ?? '-',
                $farmer->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner',
                $farmer->farm_size_hectares ? round((float) $farmer->farm_size_hectares, 2) : '',
                $farmer->mainCrops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->filter()->implode('; ') ?: '-',
                $farmer->created_at?->format('d M Y') ?? '-',
                ucfirst((string) ($farmer->user?->status ?? '-')),
            ])->all();

        return [
            'title'    => 'Farmers Registered',
            'headings' => ['Farmer', 'Contact', 'Barangay', 'Association', 'Ownership',
                           'Farm Size (ha)', 'Main Crops', 'Date Registered', 'Account Status'],
            'rows'     => $rows,
        ];
    }

    /** Planting activities submitted during the month, one row per crop. */
    private static function plantingRecords(Carbon $start, Carbon $end, ?int $associationId): array
    {
        $rows = CropPlantingRecord::active()
            ->with(['farmer.barangay', 'farmer.association', 'crops.crop'])
            ->whereBetween('date_submitted', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->orderBy('date_submitted')
            ->get()
            ->flatMap(fn ($record) => $record->crops->map(fn ($crop) => [
                $record->farmer?->full_name ?? 'Unknown',
                $record->farmer?->barangay?->name ?? '-',
                $record->farmer?->association?->name ?? '-',
                $crop->crop_specify ?: ($crop->crop?->name ?? '-'),
                $crop->date_planted?->format('d M Y') ?? '-',
                round((float) $crop->area_hectares, 2),
                $record->date_submitted?->format('d M Y') ?? '-',
            ]))->all();

        return [
            'title'    => 'Planting Activities',
            'headings' => ['Farmer', 'Barangay', 'Association', 'Crop',
                           'Date Planted', 'Area (ha)', 'Date Submitted'],
            'rows'     => $rows,
        ];
    }

    /** Damage reports filed during the month, with what the farmer stood to lose. */
    private static function damageRecords(Carbon $start, Carbon $end, ?int $associationId): array
    {
        $rows = DamageReport::active()
            ->with([
                'farmer.user', 'farmer.barangay', 'farmer.association',
                'crops.crop', 'disasters', 'assignedTechnician', 'validation',
            ])
            ->whereBetween('created_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->orderBy('created_at')
            ->get()
            ->map(function ($report) {
                $production = (float) $report->crops->sum('production_cost');
                $damageCost = (float) $report->crops->sum('total_damage_cost');

                return [
                    $report->full_reference,
                    $report->farmer?->full_name ?? 'Unknown',
                    $report->farmer?->user?->phone_number ?: '-',
                    $report->farmer?->barangay?->name ?? '-',
                    $report->farmer?->association?->name ?? '-',
                    $report->created_at?->format('d M Y') ?? '-',
                    DamageReport::CAUSES[$report->damage_cause]
                        ?? ucwords(str_replace('_', ' ', (string) $report->damage_cause)),
                    $report->disasters->pluck('name')->implode('; ') ?: '-',
                    $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->filter()->implode('; ') ?: '-',
                    round((float) $report->crops->sum('damaged_area_hectares'), 2),
                    $report->crops->max('estimated_damage_percent') !== null
                        ? (float) $report->crops->max('estimated_damage_percent') : '',
                    round($production, 2),
                    round($damageCost, 2),
                    DamageReport::STATUSES[$report->status]
                        ?? ucwords(str_replace('_', ' ', (string) $report->status)),
                    $report->assignedTechnician?->full_name
                        ?: ($report->assignedTechnician?->username ?? 'Not assigned'),
                    $report->validation?->assessed_damage_percent !== null
                        ? (float) $report->validation->assessed_damage_percent : '',
                    Validation::severityLabel($report->validation?->severity) ?? '-',
                ];
            })->all();

        return [
            'title'    => 'Damage Reports',
            'headings' => ['Report ID', 'Farmer', 'Contact', 'Barangay', 'Association',
                           'Date Reported', 'Cause', 'Disaster Event', 'Crops',
                           'Damaged Area (ha)', 'Farmer Estimate (%)',
                           'Production Cost', 'Estimated Damage Cost',
                           'Status', 'Technician', 'Assessed Damage (%)', 'Severity'],
            'rows'     => $rows,
        ];
    }

    /** Inspections a technician completed during the month. */
    private static function inspectionRecords(Carbon $start, Carbon $end, ?int $associationId): array
    {
        $rows = self::inspections()
            ->with([
                'damageReport.farmer.barangay', 'damageReport.farmer.association',
                'damageReport.crops', 'technician',
            ])
            ->whereBetween('validated_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('damageReport.farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->orderBy('validated_at')
            ->get()
            ->map(function ($validation) {
                $report = $validation->damageReport;

                return [
                    $report?->full_reference ?? '-',
                    $report?->farmer?->full_name ?? 'Unknown',
                    $report?->farmer?->barangay?->name ?? '-',
                    $validation->technician?->full_name ?: ($validation->technician?->username ?? '-'),
                    $validation->inspection_started_at?->format('d M Y g:i A') ?? '-',
                    $validation->validated_at?->format('d M Y g:i A') ?? '-',
                    $report?->crops->max('estimated_damage_percent') !== null
                        ? (float) $report->crops->max('estimated_damage_percent') : '',
                    $validation->assessed_damage_percent !== null
                        ? (float) $validation->assessed_damage_percent : '',
                    Validation::severityLabel($validation->severity) ?? '-',
                    $validation->latitude !== null && $validation->longitude !== null
                        ? $validation->latitude . ', ' . $validation->longitude
                        : 'Not captured',
                    (string) ($validation->notes ?: '-'),
                ];
            })->all();

        return [
            'title'    => 'Technician Inspections',
            'headings' => ['Report ID', 'Farmer', 'Barangay', 'Technician',
                           'Inspection Started', 'Inspection Submitted',
                           'Farmer Estimate (%)', 'Assessed Damage (%)', 'Severity',
                           'Verified Location', 'Notes'],
            'rows'     => $rows,
        ];
    }

    /** Assistance the office allocated during the month. */
    private static function allocationRecords(Carbon $start, Carbon $end, ?int $associationId): array
    {
        /* Scoped through the designated farmers, not through
           assistance_allocations.association_id (Oct 2026): an allocation is
           a batch of farmers chosen across the municipality now and that
           column is null on anything new. The Association column lists the
           associations the batch actually concerns, which can be more than
           one.

           The Assistance, Disaster and Quantity columns are gone with the
           things they named. */
        $rows = AssistanceAllocation::with(['allocatedBy', 'beneficiaries.farmer.association'])
            ->withCount('beneficiaries')
            ->whereBetween('allocated_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('beneficiaries.farmer',
                    fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->orderBy('allocated_at')
            ->get()
            ->map(fn ($allocation) => [
                'AA-' . str_pad((string) $allocation->id, 3, '0', STR_PAD_LEFT),
                $allocation->concerned_associations->pluck('name')->join(', ') ?: '-',
                $allocation->beneficiaries_count,
                $allocation->allocated_at?->format('d M Y') ?? '-',
                $allocation->allocatedBy?->full_name ?: ($allocation->allocatedBy?->username ?? '-'),
                ucfirst((string) $allocation->status),
            ])->all();

        return [
            'title'    => 'Assistance Allocations',
            'headings' => ['Allocation ID', 'Associations Concerned', 'Farmers Selected',
                           'Date Allocated', 'Allocated By', 'Status'],
            'rows'     => $rows,
        ];
    }

    /** What actually reached a farmer during the month, and whether they said so. */
    private static function distributionRecords(Carbon $start, Carbon $end, ?int $associationId): array
    {
        $rows = AssistanceDistribution::with([
                'farmer.barangay', 'farmer.association',
                'allocation',
                'distributedBy', 'damageReport',
            ])
            ->whereBetween('distributed_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->orderBy('distributed_at')
            ->get()
            ->map(fn ($distribution) => [
                'DIST-' . str_pad((string) $distribution->id, 3, '0', STR_PAD_LEFT),
                $distribution->farmer?->full_name ?? 'Unknown',
                $distribution->farmer?->barangay?->name ?? '-',
                // The farmer's own association. It used to prefer the
                // allocation's, which no longer has one.
                $distribution->farmer?->association?->name ?? '-',
                $distribution->allocation
                    ? 'AA-' . str_pad((string) $distribution->assistance_allocation_id, 3, '0', STR_PAD_LEFT)
                    : '-',
                $distribution->damageReport?->full_reference ?? '-',
                $distribution->distributed_at?->format('d M Y') ?? '-',
                $distribution->distributedBy?->full_name ?: ($distribution->distributedBy?->username ?? '-'),
                ucwords(str_replace('_', ' ', (string) $distribution->distribution_status)),
                $distribution->receipt_status === 'pending_confirmation'
                    ? 'Waiting on the farmer'
                    : ucwords(str_replace('_', ' ', (string) $distribution->receipt_status)),
            ])->all();

        return [
            'title'    => 'Assistance Distribution',
            'headings' => ['Distribution ID', 'Farmer', 'Barangay', 'Association',
                           'Allocation', 'Damage Report', 'Date Distributed',
                           'Distributed By', 'Distribution Status', 'Farmer Receipt'],
            'rows'     => $rows,
        ];
    }

    /**
     * Inspections whose damage report still stands.
     *
     * A validations row has no archived_at of its own - it belongs to a
     * damage report, and when the office archives or deletes that report
     * the inspection goes with it. So the filter has to be on the report,
     * through the relation, and this is the one place that says so.
     */
    private static function inspections()
    {
        return Validation::whereHas('damageReport', fn ($report) => $report->active());
    }

    /* ================= Farmer summary ================= */
    private static function farmerSummary(Carbon $start, Carbon $end, ?int $associationId): array
    {
        return [
            // Point-in-time snapshots, evaluated as of now.
            'total_farmers'    => Farmer::notDeleted()->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'verified_farmers' => Farmer::notDeleted()->whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'pending_farmers'  => Farmer::notDeleted()->whereHas('user', fn ($q) => $q->where('status', 'pending'))
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'rejected_farmers' => Farmer::notDeleted()->whereHas('user', fn ($q) => $q->where('status', 'rejected'))
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'active_farmers'   => Farmer::notDeleted()->where('activity_status', 'active')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'inactive_farmers' => Farmer::notDeleted()->where('activity_status', 'inactive')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'land_owners'      => Farmer::notDeleted()->where('ownership_type', 'land_owner')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'tenants'          => Farmer::notDeleted()->where('ownership_type', 'tenant')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            // These two are scoped to the selected month.
            'new_registrations' => Farmer::notDeleted()->whereBetween('created_at', [$start, $end])
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'affected_farmers'  => DamageReport::active()->whereBetween('created_at', [$start, $end])
                ->when($associationId, fn ($q) => $q
                    ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
                ->distinct('farmer_id')->count('farmer_id'),
        ];
    }

    /* ================= Farm summary ================= */
    private static function farmSummary(?int $associationId): array
    {
        // The system records one farm profile per farmer (proposal section
        // 16); there is no separate multi-farm table, so "Total Farms" is
        // the count of farmer records that carry farm information.
        $mainCrops = DB::table('farmer_main_crops')
            ->join('crops', 'crops.id', '=', 'farmer_main_crops.crop_id')
            ->join('farmers', 'farmers.id', '=', 'farmer_main_crops.farmer_id')
            ->whereNull('farmers.deleted_at')
            ->when($associationId, fn ($q) => $q->where('farmers.association_id', $associationId))
            ->select('crops.name', DB::raw('COUNT(*) as total'))
            ->groupBy('crops.name')
            ->orderByDesc('total')
            ->get();

        $byBarangay = Barangay::withCount(['farmers' => fn ($q) => $q
                ->notDeleted()
                ->when($associationId, fn ($farmer) => $farmer->where('association_id', $associationId))])
            ->orderByDesc('farmers_count')
            ->get(['id', 'name']);

        return [
            'total_farms'     => Farmer::notDeleted()->whereNotNull('farm_size_hectares')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'total_farm_area' => (float) Farmer::notDeleted()->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->sum('farm_size_hectares'),
            'main_crops'      => $mainCrops,
            'by_barangay'     => $byBarangay,
        ];
    }

    /* ================= Planting summary ================= */
    private static function plantingSummary(Carbon $start, Carbon $end, ?int $associationId): array
    {
        $recordIds = CropPlantingRecord::active()
            ->whereBetween('date_submitted', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->pluck('id');

        $byCrop = DB::table('crop_planting_record_crops')
            ->join('crops', 'crops.id', '=', 'crop_planting_record_crops.crop_id')
            ->whereIn('crop_planting_record_crops.crop_planting_record_id', $recordIds)
            ->select('crops.name', DB::raw('COUNT(*) as records'), DB::raw('SUM(area_hectares) as area'))
            ->groupBy('crops.name')
            ->orderByDesc('area')
            ->get();

        return [
            'activities'    => $recordIds->count(),
            'crops_planted' => $byCrop->count(),
            'total_area'    => (float) $byCrop->sum('area'),
            'by_crop'       => $byCrop,
        ];
    }

    /* ================= Damage summary ================= */
    private static function damageSummary(Carbon $start, Carbon $end, ?int $associationId): array
    {
        /* Everything else in this method is derived from $reportIds, so
           active() here is what keeps the whole damage summary - by
           barangay, by crop, by cause, by disaster, by status, by severity,
           the affected-farmer count and the area total - off archived and
           deleted reports. */
        $reportIds = DamageReport::active()
            ->whereBetween('created_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->pluck('id');

        $byBarangay = DamageReport::whereIn('damage_reports.id', $reportIds)
            ->join('barangays', 'barangays.id', '=', 'damage_reports.reported_barangay_id')
            ->select('barangays.name', DB::raw('COUNT(*) as total'))
            ->groupBy('barangays.name')
            ->orderByDesc('total')
            ->get();

        $byCrop = DB::table('damage_report_crops')
            ->join('crops', 'crops.id', '=', 'damage_report_crops.crop_id')
            ->whereIn('damage_report_crops.damage_report_id', $reportIds)
            ->select('crops.name', DB::raw('COUNT(*) as reports'), DB::raw('SUM(damaged_area_hectares) as area'))
            ->groupBy('crops.name')
            ->orderByDesc('area')
            ->get();

        // Every report always has a cause (proposal section 8, and the "Not
        // built yet" note in BUILD-STATUS section 12.1), so this breakdown
        // never leaves a report uncounted — unlike a breakdown by declared
        // disaster, which only covers weather events the office happened
        // to declare, and misses pest, disease and heat reports entirely.
        $causeCounts = DamageReport::whereIn('id', $reportIds)
            ->select('damage_cause', DB::raw('COUNT(*) as total'))
            ->groupBy('damage_cause')
            ->pluck('total', 'damage_cause');

        $byCause = collect(DamageReport::CAUSES)->map(fn ($label, $key) => [
            'label' => $label,
            'total' => (int) $causeCounts->get($key, 0),
        ])->values();

        $byDisaster = DB::table('damage_report_disasters')
            ->join('disasters', 'disasters.id', '=', 'damage_report_disasters.disaster_id')
            ->whereIn('damage_report_disasters.damage_report_id', $reportIds)
            ->select('disasters.name', DB::raw('COUNT(*) as total'))
            ->groupBy('disasters.name')
            ->orderByDesc('total')
            ->get();

        $statusCounts = DamageReport::whereIn('id', $reportIds)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $severityCounts = Validation::whereIn('damage_report_id', $reportIds)
            ->whereNotNull('severity')
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $bySeverity = collect(Validation::SEVERITY_SCALE)->map(fn ($row, $key) => [
            'label' => $row['label'],
            'total' => (int) $severityCounts->get($key, 0),
        ])->values();

        return [
            'total_reports'    => $reportIds->count(),
            'affected_farmers' => DamageReport::whereIn('id', $reportIds)
                ->distinct('farmer_id')->count('farmer_id'),
            'total_area'   => (float) $byCrop->sum('area'),
            'by_barangay'  => $byBarangay,
            'by_crop'      => $byCrop,
            'by_cause'     => $byCause,
            'by_disaster'  => $byDisaster,
            'by_status'    => [
                'pending'            => (int) $statusCounts->get('pending', 0),
                'assigned'           => (int) $statusCounts->get('assigned', 0),
                'under_verification' => (int) $statusCounts->get('under_verification', 0),
                'verified'           => (int) $statusCounts->get('verified', 0),
                'flagged'            => (int) $statusCounts->get('flagged', 0),
                'approved'           => (int) $statusCounts->get('approved', 0),
                'rejected'           => (int) $statusCounts->get('rejected', 0),
            ],
            'by_severity' => $bySeverity,
        ];
    }

    /* ================= Verification summary ================= */
    private static function verificationSummary(Carbon $start, Carbon $end, ?int $associationId): array
    {
        $byTechnician = self::inspections()
            ->whereBetween('validated_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('damageReport.farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->join('users', 'users.id', '=', 'validations.technician_id')
            ->select('users.full_name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.full_name')
            ->orderByDesc('total')
            ->get();

        return [
            'reports_assigned'      => DamageReport::active()
                ->whereBetween('assigned_at', [$start, $end])
                ->when($associationId, fn ($q) => $q
                    ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
                ->count(),
            'inspections_completed' => self::inspections()
                ->whereBetween('validated_at', [$start, $end])
                ->when($associationId, fn ($q) => $q
                    ->whereHas('damageReport.farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
                ->count(),
            'verified_locations'    => self::inspections()
                ->whereBetween('validated_at', [$start, $end])
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->when($associationId, fn ($q) => $q
                    ->whereHas('damageReport.farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
                ->count(),
            'by_technician' => $byTechnician,
        ];
    }

    /* ================= Assistance summary ================= */
    private static function assistanceSummary(Carbon $start, Carbon $end, ?int $associationId): array
    {
        /* COUNTS OF PEOPLE, NOT SUMS OF MONEY AND GOODS (Oct 2026)

           This returned cash_allocated, in_kind_allocated and
           total_distributed_quantity, which are the three figures the
           master prompt's section 75 asks for by name. The client asked for
           every amount out of the system, for privacy: how much a named
           farmer received is a financial detail about an identifiable
           person, and a monthly report is the most widely read thing this
           system produces.

           What replaces them answers the same question the office is
           actually asking - how much assistance work happened this month -
           in people and batches rather than pesos and sacks. Chapter 3's
           section 75 needs updating to match; that is recorded in
           BUILD-STATUS rather than left for somebody to notice.

           Both allocations and distributions are scoped through the
           FARMER's own association, which is now the only association an
           allocation has. */
        $baseAllocations = AssistanceAllocation::whereBetween('allocated_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('beneficiaries.farmer',
                    fn ($farmer) => $farmer->where('association_id', $associationId)));

        $designated = AssistanceAllocationBeneficiary::whereHas('allocation',
                fn ($allocation) => $allocation->whereBetween('allocated_at', [$start, $end]))
            ->when($associationId, fn ($q) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)));

        $distributions = AssistanceDistribution::whereBetween('distributed_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)));

        return [
            'total_allocated'      => (clone $baseAllocations)->count(),
            'farmers_designated'   => (clone $designated)->distinct('farmer_id')->count('farmer_id'),

            'distributions_recorded' => (clone $distributions)->count(),
            'beneficiaries'          => (clone $distributions)->distinct('farmer_id')->count('farmer_id'),
            'confirmed_received'     => (clone $distributions)->where('receipt_status', 'confirmed_received')->count(),
            'not_received'           => (clone $distributions)->where('receipt_status', 'not_received')->count(),
        ];
    }
}
