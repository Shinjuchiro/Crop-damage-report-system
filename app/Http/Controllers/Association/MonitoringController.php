<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\CropPlantingRecord;
use App\Models\DamageReport;
use App\Models\Farmer;
use App\Models\Validation;
use App\Services\RecordListExport;
use App\Support\MonitorStats;
use App\Support\PlantingMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring  (proposal section 60 and 61)
 *
 * Two lists across the whole membership rather than one member at a time:
 * what the members planted, and what damage they reported.
 *
 * Both are read only. The association watches its members' activity; it does
 * not file or edit anything on their behalf. The damage report list in
 * particular is how an officer answers "has anybody inspected Aling Rosa's
 * farm yet" without phoning the office.
 *
 * ONE REPORT, TWO TABS (Oct 2026)
 *
 * These two pages are the association's Crop Management Report, and they
 * now carry the tab strip and the Export Report button the office's three
 * tabs have. Each page also shows its own figures above the strip: those
 * used to be printed on the two cards of a hub page, which is now a
 * redirect (Association\CropManagementController).
 *
 * ARCHIVED AND DELETED RECORDS ARE OUT (Oct 2026)
 *
 * Every query here now starts with active(), which skips anything the
 * office has archived or deleted. It did not before, and the two pages were
 * the only lists in the system still showing such records as though they
 * were live: a report filed by mistake and archived vanished from the
 * office's screen and stayed on the officer's, counted in their figures,
 * waiting for somebody to ring up and ask why nothing was happening with
 * it. Section 16 asks for exactly this.
 *
 * active() covers both columns (see Concerns\Archivable), so one call does
 * it, and it is on the list, the figures, the export and the detail lookup
 * alike - a record that cannot be seen in the list should not be reachable
 * by putting its id in the address bar either.
 *
 * MEMBERSHIP SCOPING IS THE SECURITY BOUNDARY HERE
 *
 * Every query in this class, the exports included, is restricted to farmers
 * whose association_id is this officer's own, through ResolvesAssociation.
 * An export that built its own query would be the easy way to hand an
 * officer a spreadsheet of another association's members, so both exports
 * are built from the same filtered() methods the screens use rather than
 * from a second query that merely looks similar.
 */
class MonitoringController extends Controller
{
    use ResolvesAssociation;

    private function view(Request $request): string
    {
        return $request->query('view') === 'flat' ? 'flat' : 'grouped';
    }

    /**
     * The members' own farmer ids, which is the scope of everything here.
     */
    private function memberIds($association)
    {
        return Farmer::where('association_id', $association->id)->select('id');
    }

    /**
     * Planting records filed by members, filtered but not yet ordered.
     *
     * Pulled out of planting() when Export Report arrived: the file has to
     * cover exactly what the screen is showing, and the only way to be sure
     * of that is for both to be built from one query rather than from two
     * that happen to look alike today.
     */
    private function filteredPlanting(Request $request, $association)
    {
        return CropPlantingRecord::active()
            ->whereIn('farmer_id', $this->memberIds($association))
            // farmer.association for the grouped view's heading; without
            // it the heading lazy-loads one query per member on the page.
            ->with(['farmer.barangay', 'farmer.association', 'crops.crop'])
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            // Month filter, so an officer can answer "what went in this season".
            ->when($request->query('month'), fn ($q, $month) => $q->whereMonth('date_submitted', $month))
            ->when($request->query('year'), fn ($q, $year) => $q->whereYear('date_submitted', $year));
    }

    /**
     * Planting activities filed by members.
     */
    public function planting(Request $request)
    {
        $association = $this->association();
        $view        = $this->view($request);

        if ($request->filled('export')) {
            return $this->exportPlanting($request, $association);
        }

        /* Grouped: a handful of MEMBERS a page, each with every record they
           have filed (App\Support\FarmerGroups), so a member's row can open
           to their whole history. Flat: newest first, fifteen a page.
           Replaces the old "latest three per member" cap. */
        $records = $view === 'grouped'
            ? \App\Support\FarmerGroups::paginate(
                $this->filteredPlanting($request, $association),
                fn ($q) => $q->select('crop_planting_records.farmer_id')
                    ->groupBy('crop_planting_records.farmer_id')
                    ->orderByRaw('MAX(crop_planting_records.date_submitted) DESC')
                    ->orderBy('crop_planting_records.farmer_id'),
                fn ($q, $ids) => $q->whereIn('crop_planting_records.farmer_id', $ids)
                    ->orderByDesc('date_submitted')
                    ->orderByDesc('crop_planting_records.id'),
                fn ($record) => $record->farmer_id
            )
            : $this->filteredPlanting($request, $association)
                ->orderByDesc('date_submitted')
                ->paginate(15)
                ->withQueryString();

        /* The same two figures, defined the same way, as the office's
           dashboard (App\Support\MonitorStats), limited to this
           association's own members. */
        $summary = MonitorStats::planting($association->id);

        $selected = $this->selectedPlanting($request, $association);
        \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'planting', $selected?->id);

        return view('association.planting.index', [
            'association' => $association,
            'records'     => $records,
            'view'        => $view,
            'summary'     => $summary,
            'selected'    => $selected,
            'relatedDamage' => $selected ? PlantingMatch::damageFor($selected) : collect(),
            'farmerTotals' => collect(),
            'filters'     => $request->only(['q', 'month', 'year']),
        ]);
    }

    /**
     * The one record opened with ?selected=<id>, or null.
     *
     * THE scope IS THE AUTHORISATION
     *
     * find() is called ON the membership-scoped query, not on the model.
     * An officer who edits the id in the address bar to a record belonging
     * to another association gets null and an unopened panel, because the
     * record was never in the set being searched. Written the other way
     * round - find the record, then check who it belongs to - the check
     * becomes a separate line that a later edit can forget, which is
     * exactly the hole section 3.5 asks to be tested for.
     */
    private function selectedPlanting(Request $request, $association)
    {
        if (! $request->filled('selected')) {
            return null;
        }

        return CropPlantingRecord::active()
            ->whereIn('farmer_id', $this->memberIds($association))
            ->with(['farmer.barangay', 'farmer.association', 'crops.crop', 'photos'])
            ->find($request->selected);
    }

    /**
     * Export Report: the members' planting records, as currently filtered.
     *
     * One row per record, which is what this page lists, with the crops
     * summarised in a column and their areas totalled. Contact numbers are
     * deliberately not in the file: an officer can see a member's contact
     * details on the member's own page, where opening it is a deliberate
     * act, and section 3.4 asks that a spreadsheet not carry personal
     * details it has no use for.
     */
    private function exportPlanting(Request $request, $association)
    {
        $rows = $this->filteredPlanting($request, $association)
            ->orderByDesc('date_submitted')
            ->get()
            ->map(fn ($record) => [
                'REC-' . str_pad((string) $record->id, 4, '0', STR_PAD_LEFT),
                $record->farmer?->full_name ?? 'Unknown',
                $record->farmer?->barangay?->name ?? '-',
                $record->crops
                    ->map(fn ($crop) => $crop->crop_specify ?: $crop->crop?->name)
                    ->filter()
                    ->join(', ') ?: '-',
                round((float) $record->crops->sum('area_hectares'), 2),
                $record->crops->min('date_planted')?->format('d M Y') ?? '-',
                $record->date_submitted?->format('d M Y') ?? '-',
            ])
            ->all();

        return RecordListExport::download(
            $request->query('export') === 'pdf' ? 'pdf' : 'xlsx',
            $association->name . ' - Crop Planting Records',
            [
                'Member'  => $request->query('q'),
                'Month'   => $request->filled('month')
                    ? \Carbon\Carbon::create(null, (int) $request->month, 1)->format('F')
                    : null,
                'Year'    => $request->query('year'),
            ],
            [
                'Record No.', 'Member', 'Barangay', 'Crops',
                'Total Area (ha)', 'Earliest Planting', 'Date Submitted',
            ],
            $rows,
            'association-planting-records'
        );
    }

    /**
     * Damage reports filed by members, filtered but not yet ordered.
     */
    private function filteredDamage(Request $request, $association)
    {
        return DamageReport::active()
            ->whereIn('farmer_id', $this->memberIds($association))
            ->with([
                'farmer.barangay', 'farmer.association', 'reportedBarangay',
                'crops.crop', 'disasters', 'validation', 'assignedTechnician',
            ])
            ->withCount('photos')
            // 'pending_review' is a submitted assessment nobody has decided yet.
            ->when($request->query('status'), fn ($q, $status) => $status === 'pending_review'
                ? $q->whereIn('status', DamageReport::PENDING_REVIEW)
                : $q->where('status', $status))
            // Technician assignment, kept apart from the report's status.
            ->when($request->query('assignment'), fn ($q, $which) => $q->assignment($which === 'assigned' ? 'assigned' : 'unassigned'))
            ->when(in_array($request->query('planting_match'), ['matched', 'mismatch', 'none'], true),
                fn ($q) => $q->plantingResult($request->query('planting_match')))
            ->when($request->query('cause'), fn ($q, $cause) => $q->where('damage_cause', $cause))
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            });
    }

    /**
     * Damage reports (or inspected ones) paged by MEMBER, most recent filer
     * first, each page carrying every report its members have filed. One
     * helper for both tabs because the only difference between them is the
     * query handed in. See App\Support\FarmerGroups.
     */
    private function groupedDamage($query)
    {
        return \App\Support\FarmerGroups::paginate(
            $query,
            fn ($q) => $q->select('damage_reports.farmer_id')
                ->groupBy('damage_reports.farmer_id')
                ->orderByRaw('MAX(damage_reports.created_at) DESC')
                ->orderBy('damage_reports.farmer_id'),
            fn ($q, $ids) => $q->whereIn('damage_reports.farmer_id', $ids)
                ->latest()->orderByDesc('damage_reports.id'),
            fn ($report) => $report->farmer_id
        );
    }

    /**
     * Damage reports filed by members, with where each one has got to.
     */
    public function damageReports(Request $request)
    {
        $association = $this->association();
        $view        = $this->view($request);

        if ($request->filled('export')) {
            return $this->exportDamage($request, $association);
        }

        $reports = $view === 'grouped'
            ? $this->groupedDamage($this->filteredDamage($request, $association))
            : $this->filteredDamage($request, $association)->orderByDesc('created_at')->paginate(15)->withQueryString();

        PlantingMatch::annotate($reports->getCollection());

        // Same definitions as the office's dashboard, for this association only.
        $summary = MonitorStats::damage($association->id);

        $selected = $this->selectedReport($request, $association);
        \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'damage_report', $selected?->id);

        return view('association.reports.index', [
            'association' => $association,
            'reports'     => $reports,
            'view'        => $view,
            'summary'     => $summary,
            'selected'    => $selected,
            'farmerTotals' => collect(),
            'filters'     => $request->only(['assignment', 'planting_match', 'cause', 'q']),
            'causes'      => DamageReport::CAUSES,
        ]);
    }

    /**
     * Validation Monitoring, the association's side: its own members'
     * completed inspections only. Same list as the office's, read only.
     */
    public function validations(Request $request)
    {
        $association = $this->association();
        $view        = $this->view($request);

        $inspected = $this->filteredDamage($request, $association)
            ->whereHas('validation', fn ($v) => $v->whereNotNull('validated_at'));

        $reports = $view === 'grouped'
            ? $this->groupedDamage($inspected)
            : $inspected->orderByDesc('created_at')->paginate(15)->withQueryString();

        PlantingMatch::annotate($reports->getCollection());

        // Same definitions as the office's dashboard, for this association only.
        $summary = MonitorStats::validation($association->id);

        $selected = $this->selectedReport($request, $association);
        if ($selected && $selected->validation?->validated_at) {
            \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'validation', $selected->id);
        }

        return view('association.validations.index', [
            'association'  => $association,
            'reports'      => $reports,
            'view'         => $view,
            'summary'      => $summary,
            'selected'     => $selected,
            'farmerTotals' => collect(),
            'filters'      => $request->only(['status', 'planting_match', 'q']),
        ]);
    }

    /**
     * The one report opened with ?selected=<id>, or null. Scoped to the
     * membership the same way, and for the same reason, as
     * selectedPlanting() above.
     *
     * The technician's verified coordinates are deliberately not loaded:
     * an officer needs to know an inspection happened and what it found,
     * not where the inspector stood to within seven decimal places
     * (section 12.4). The barangay is in the panel; the exact pin is the
     * office's and the technician's.
     */
    private function selectedReport(Request $request, $association)
    {
        if (! $request->filled('selected')) {
            return null;
        }

        $report = DamageReport::active()
            ->whereIn('farmer_id', $this->memberIds($association))
            ->with([
                'farmer.barangay', 'farmer.association', 'farmer.mainCrops.crop', 'reportedBarangay',
                'crops.crop', 'disasters', 'photos',
                'validation.technician', 'validation.photos',
                'assignedTechnician', 'reviewedBy', 'approvedBy',
            ])
            ->find($request->selected);

        if ($report) {
            PlantingMatch::annotate([$report]);
        }

        return $report;
    }

    /**
     * Export Report: the members' damage reports, as currently filtered.
     *
     * The farmer's own estimate and the technician's assessment are separate
     * columns, never one merged figure (section 9.4). Coordinates are not in
     * the file at all: a farm's exact location is the most sensitive thing
     * on a damage report, an officer has no need of it in a spreadsheet, and
     * section 12.4 asks that it not travel further than it must.
     */
    private function exportDamage(Request $request, $association)
    {
        $reports = $this->filteredDamage($request, $association)
            ->latest()
            ->get();

        PlantingMatch::annotate($reports);

        $rows = $reports
            ->map(function ($report) {
                $validation = $report->validation;

                return [
                    $report->full_reference,
                    $report->farmer?->full_name ?? 'Unknown',
                    $report->farmer?->barangay?->name ?? '-',
                    $report->crops
                        ->map(fn ($crop) => $crop->crop_specify ?: $crop->crop?->name)
                        ->filter()->join(', ') ?: '-',
                    round((float) $report->crops->sum('damaged_area_hectares'), 2),
                    $report->disasters->pluck('name')->filter()->join(', ') ?: '-',
                    $report->crops->max('estimated_damage_percent') !== null
                        ? (float) $report->crops->max('estimated_damage_percent')
                        : '',
                    $report->assignment_label,
                    PlantingMatch::label($report->planting_match),
                    $validation?->validated_at
                        ? match ($report->review_state) {
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            default    => 'Pending Review',
                        }
                        : '-',
                    $report->assignedTechnician?->full_name
                        ?: ($report->assignedTechnician?->username ?? 'Not assigned'),
                    $validation?->assessed_damage_percent !== null
                        ? (float) $validation->assessed_damage_percent
                        : '',
                    Validation::severityLabel($validation?->severity) ?? '-',
                    $report->created_at?->format('d M Y') ?? '-',
                ];
            })
            ->all();

        return RecordListExport::download(
            $request->query('export') === 'pdf' ? 'pdf' : 'xlsx',
            $association->name . ' - Crop Damage Reports',
            [
                'Member' => $request->query('q'),
                'Assignment' => match ($request->query('assignment')) {
                    'assigned'   => 'Assigned',
                    'unassigned' => 'Not Assigned',
                    default      => null,
                },
                'Crop Planting' => in_array($request->query('planting_match'), ['matched', 'mismatch', 'none'], true)
                    ? PlantingMatch::label($request->query('planting_match'))
                    : null,
                'Cause'  => $request->filled('cause')
                    ? (DamageReport::CAUSES[$request->cause] ?? $request->cause)
                    : null,
            ],
            [
                'Report ID', 'Member', 'Barangay', 'Crops', 'Damaged Area (ha)',
                'Disasters', 'Member Estimate (%)', 'Assignment', 'Crop Planting', 'Assessment Status', 'Technician',
                'Assessed Damage (%)', 'Severity', 'Date Filed',
            ],
            $rows,
            'association-damage-reports'
        );
    }
}
