<?php

namespace App\Services;

use App\Models\AssistanceAllocation;
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
    public static function forPeriod(int $year, int $month, ?int $associationId = null): array
    {
        // Section 22's 3-month rule: Active/Inactive Farmers below is a
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
        ];
    }

    /* ================= Farmer summary ================= */
    private static function farmerSummary(Carbon $start, Carbon $end, ?int $associationId): array
    {
        return [
            // Point-in-time snapshots, evaluated as of now.
            'total_farmers'    => Farmer::when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'verified_farmers' => Farmer::whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'pending_farmers'  => Farmer::whereHas('user', fn ($q) => $q->where('status', 'pending'))
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'rejected_farmers' => Farmer::whereHas('user', fn ($q) => $q->where('status', 'rejected'))
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'active_farmers'   => Farmer::where('activity_status', 'active')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'inactive_farmers' => Farmer::where('activity_status', 'inactive')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'land_owners'      => Farmer::where('ownership_type', 'land_owner')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'tenants'          => Farmer::where('ownership_type', 'tenant')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            // These two are scoped to the selected month.
            'new_registrations' => Farmer::whereBetween('created_at', [$start, $end])
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'affected_farmers'  => DamageReport::whereBetween('created_at', [$start, $end])
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
            ->when($associationId, fn ($q) => $q->where('farmers.association_id', $associationId))
            ->select('crops.name', DB::raw('COUNT(*) as total'))
            ->groupBy('crops.name')
            ->orderByDesc('total')
            ->get();

        $byBarangay = Barangay::withCount(['farmers' => fn ($q) => $q
                ->when($associationId, fn ($farmer) => $farmer->where('association_id', $associationId))])
            ->orderByDesc('farmers_count')
            ->get(['id', 'name']);

        return [
            'total_farms'     => Farmer::whereNotNull('farm_size_hectares')
                ->when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->count(),
            'total_farm_area' => (float) Farmer::when($associationId, fn ($q) => $q->where('association_id', $associationId))
                ->sum('farm_size_hectares'),
            'main_crops'      => $mainCrops,
            'by_barangay'     => $byBarangay,
        ];
    }

    /* ================= Planting summary ================= */
    private static function plantingSummary(Carbon $start, Carbon $end, ?int $associationId): array
    {
        $recordIds = CropPlantingRecord::whereBetween('date_submitted', [$start, $end])
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
        $reportIds = DamageReport::whereBetween('created_at', [$start, $end])
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
        $byTechnician = Validation::whereBetween('validated_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('damageReport.farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
            ->join('users', 'users.id', '=', 'validations.technician_id')
            ->select('users.full_name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.full_name')
            ->orderByDesc('total')
            ->get();

        return [
            'reports_assigned'      => DamageReport::whereBetween('assigned_at', [$start, $end])
                ->when($associationId, fn ($q) => $q
                    ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
                ->count(),
            'inspections_completed' => Validation::whereBetween('validated_at', [$start, $end])
                ->when($associationId, fn ($q) => $q
                    ->whereHas('damageReport.farmer', fn ($farmer) => $farmer->where('association_id', $associationId)))
                ->count(),
            'verified_locations'    => Validation::whereBetween('validated_at', [$start, $end])
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
        // Allocations are made directly to an association (assistance_
        // allocations.association_id) - there is no farmer_id on this table,
        // see AssistanceAllocation. Distributions are the per-farmer records
        // paid out of an allocation, and do carry their own farmer_id, so
        // they are scoped through that farmer's own association_id instead
        // of the parent allocation, matching how every other section here
        // scopes "belongs to this association".
        $baseAllocations = AssistanceAllocation::whereBetween('allocated_at', [$start, $end])
            ->when($associationId, fn ($q) => $q->where('association_id', $associationId));

        $cashAllocated = (clone $baseAllocations)
            ->whereHas('assistance', fn ($q) => $q->where('type', 'cash'))
            ->sum('allocated_quantity');

        $inKindAllocated = (clone $baseAllocations)
            ->whereHas('assistance', fn ($q) => $q->where('type', 'in_kind'))
            ->sum('allocated_quantity');

        $distributions = AssistanceDistribution::whereBetween('distributed_at', [$start, $end])
            ->when($associationId, fn ($q) => $q
                ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId)));

        return [
            'cash_allocated'    => (float) $cashAllocated,
            'in_kind_allocated' => (float) $inKindAllocated,
            'total_allocated'   => (clone $baseAllocations)->count(),

            'total_distributed_quantity' => (float) (clone $distributions)->sum('quantity'),
            'distributions_recorded'     => (clone $distributions)->count(),
            'beneficiaries'              => (clone $distributions)->distinct('farmer_id')->count('farmer_id'),
            'confirmed_received'         => (clone $distributions)->where('receipt_status', 'confirmed_received')->count(),
            'not_received'               => (clone $distributions)->where('receipt_status', 'not_received')->count(),
        ];
    }
}
