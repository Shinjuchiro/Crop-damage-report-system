<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Association\Concerns\ResolvesAssociation;
use App\Http\Controllers\Controller;
use App\Models\CropPlantingRecord;
use App\Models\DamageReport;
use App\Models\Farmer;
use App\Models\Validation;
use App\Services\RecordListExport;
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

    /**
     * How a record list is ordered, which is the whole of the difference
     * between the two views (revision sections 10 to 12).
     *
     * Grouped puts a member's records together, so the page can print the
     * name once above them. Flat is newest first, which is what the office
     * and an officer want when the question is "what came in today" rather
     * than "what has this member filed".
     *
     * Grouped used to be alphabetical. It is now ordered by who filed last,
     * because an officer opening this page is asking what is new, and
     * alphabetical order buried this morning's report behind every member
     * whose surname begins with A.
     *
     * The subquery returns one value per member, so all of a member's rows
     * sort to the same place and their block stays in one piece. The
     * farmer_id after it is a tie-breaker, and it matters: date_submitted
     * on a planting record is a date, not a timestamp, so two members
     * filing on the same day is ordinary. Without it their rows would
     * interleave and both blocks would come apart.
     *
     * Ordering only. No filter, no scope and no column changes: the same
     * rows come back either way, in a different order.
     */
    private function ordered($query, string $view, string $dateColumn, string $foreignTable)
    {
        if ($view === 'flat') {
            return $query->orderByDesc($dateColumn);
        }

        $memberLastFiled = DB::table($foreignTable . ' as latest_of_member')
            ->selectRaw('MAX(latest_of_member.' . $dateColumn . ')')
            ->whereColumn('latest_of_member.farmer_id', $foreignTable . '.farmer_id')
            ->whereNull('latest_of_member.deleted_at')
            ->whereNull('latest_of_member.archived_at');

        return $query
            ->orderByDesc($memberLastFiled)
            ->orderBy($foreignTable . '.farmer_id')
            ->orderByDesc($dateColumn);
    }

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

        $limited = \App\Support\LatestPerFarmer::applies($request, $view);

        $records = $this->ordered(
                $this->filteredPlanting($request, $association)
                    ->when($limited, fn ($q) => $q->whereRaw(
                        '(SELECT COUNT(*) FROM crop_planting_records p2 WHERE p2.farmer_id = crop_planting_records.farmer_id
                            AND p2.deleted_at IS NULL AND p2.archived_at IS NULL
                            AND (p2.date_submitted > crop_planting_records.date_submitted
                                 OR (p2.date_submitted = crop_planting_records.date_submitted AND p2.id > crop_planting_records.id))) < ?',
                        [\App\Support\LatestPerFarmer::LIMIT]
                    )),
                $view,
                'date_submitted',
                'crop_planting_records'
            )
            ->paginate(15)
            ->withQueryString();

        /* The figures that used to sit on the hub's Crop Planted card, now
           on the page itself. Unfiltered on purpose: they answer "how is
           the membership doing", which is not a question the filter row
           above the list is asking. */
        $all = fn () => CropPlantingRecord::active()
            ->whereIn('farmer_id', $this->memberIds($association));

        $summary = [
            'records'    => $all()->count(),
            'this_month' => $all()
                ->whereMonth('date_submitted', now()->month)
                ->whereYear('date_submitted', now()->year)
                ->count(),
            'members'    => $all()->distinct('farmer_id')->count('farmer_id'),
            'area'       => (float) DB::table('crop_planting_record_crops as c')
                ->join('crop_planting_records as r', 'r.id', '=', 'c.crop_planting_record_id')
                ->join('farmers as f', 'f.id', '=', 'r.farmer_id')
                ->where('f.association_id', $association->id)
                ->whereNull('r.archived_at')
                ->whereNull('r.deleted_at')
                ->sum('c.area_hectares'),
        ];

        $selected = $this->selectedPlanting($request, $association);
        \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'planting', $selected?->id);

        return view('association.planting.index', [
            'association' => $association,
            'records'     => $records,
            'view'        => $view,
            'summary'     => $summary,
            'selected'    => $selected,
            'farmerTotals' => $limited ? DB::table('crop_planting_records')->whereNull('deleted_at')->whereNull('archived_at')->select('farmer_id', DB::raw('COUNT(*) as total'))->groupBy('farmer_id')->pluck('total', 'farmer_id') : collect(),
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
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('cause'), fn ($q, $cause) => $q->where('damage_cause', $cause))
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('farmer', function ($farmer) use ($term) {
                    $farmer->where('first_name', 'like', "%{$term}%")
                           ->orWhere('last_name', 'like', "%{$term}%");
                });
            });
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

        $limited = \App\Support\LatestPerFarmer::applies($request, $view);

        $reports = $this->ordered(
                $this->filteredDamage($request, $association)
                    ->when($limited, fn ($q) => \App\Support\LatestPerFarmer::damageReports($q)),
                $view,
                'created_at',
                'damage_reports'
            )
            ->paginate(15)
            ->withQueryString();

        /* The hub's Crop Damage card carried the total and how many were
           still being processed. Both are here, with the two figures an
           officer is actually asked about beside them: how many have been
           verified (which is what makes a member eligible to be considered
           for assistance), and how many distinct members are affected. */
        $all = fn () => DamageReport::active()
            ->whereIn('farmer_id', $this->memberIds($association));

        $summary = [
            'reports'    => $all()->count(),
            'processing' => $all()->whereIn('status', ['pending', 'assigned', 'under_verification'])->count(),
            'verified'   => $all()->whereIn('status', ['verified', 'approved'])->count(),
            'members'    => $all()->distinct('farmer_id')->count('farmer_id'),
        ];

        $selected = $this->selectedReport($request, $association);
        \App\Support\Seen::mark(\Illuminate\Support\Facades\Auth::user(), 'damage_report', $selected?->id);

        return view('association.reports.index', [
            'association' => $association,
            'reports'     => $reports,
            'view'        => $view,
            'summary'     => $summary,
            'selected'    => $selected,
            'farmerTotals' => $limited ? \App\Support\LatestPerFarmer::damageTotals() : collect(),
            'filters'     => $request->only(['status', 'cause', 'q']),
            'causes'      => DamageReport::CAUSES,
            'statuses'    => DamageReport::STATUSES,
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

        $limited = \App\Support\LatestPerFarmer::applies($request, $view);

        $reports = $this->ordered(
                $this->filteredDamage($request, $association)
                    ->whereHas('validation', fn ($v) => $v->whereNotNull('validated_at'))
                    ->when($limited, fn ($q) => \App\Support\LatestPerFarmer::damageReports($q, true)),
                $view,
                'created_at',
                'damage_reports'
            )
            ->paginate(15)
            ->withQueryString();

        $done = fn () => DamageReport::active()
            ->whereIn('farmer_id', $this->memberIds($association))
            ->whereHas('validation', fn ($v) => $v->whereNotNull('validated_at'));

        $summary = [
            'completed' => $done()->count(),
            'approved'  => $done()->where('status', 'approved')->count(),
            'members'   => $done()->distinct('farmer_id')->count('farmer_id'),
        ];

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
            'farmerTotals' => $limited
                ? \App\Support\LatestPerFarmer::damageTotals(true)
                : collect(),
            'filters'      => $request->only(['status', 'q']),
            'statuses'     => DamageReport::STATUSES,
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

        return DamageReport::active()
            ->whereIn('farmer_id', $this->memberIds($association))
            ->with([
                'farmer.barangay', 'farmer.association', 'reportedBarangay',
                'crops.crop', 'disasters', 'photos',
                'validation.technician', 'validation.photos',
                'assignedTechnician',
            ])
            ->find($request->selected);
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
        $rows = $this->filteredDamage($request, $association)
            ->latest()
            ->get()
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
                    DamageReport::STATUSES[$report->status]
                        ?? ucwords(str_replace('_', ' ', (string) $report->status)),
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
                'Status' => $request->filled('status')
                    ? (DamageReport::STATUSES[$request->status] ?? $request->status)
                    : null,
                'Cause'  => $request->filled('cause')
                    ? (DamageReport::CAUSES[$request->cause] ?? $request->cause)
                    : null,
            ],
            [
                'Report ID', 'Member', 'Barangay', 'Crops', 'Damaged Area (ha)',
                'Disasters', 'Member Estimate (%)', 'Status', 'Technician',
                'Assessed Damage (%)', 'Severity', 'Date Filed',
            ],
            $rows,
            'association-damage-reports'
        );
    }
}
