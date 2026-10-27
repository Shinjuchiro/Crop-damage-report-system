<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * My Reports (Oct 2026): one plain history of everything this farmer has
 * filed, planting records and damage reports together, newest first.
 *
 * A look-up page and nothing else. A farmer cannot generate, print or export
 * reports from here or anywhere (reporting belongs to the other roles), and
 * there is no analytics for them. Every row is the farmer's own: the lists are
 * built from $farmer->plantingRecords() and $farmer->damageReports(), so no
 * other farmer's record can appear however the URL is edited.
 */
class MyReportsController extends Controller
{
    private const TYPES = ['damage' => 'Crop Damage', 'planting' => 'Crop Planting'];

    /** What the farmer reads for each damage report status. */
    private const DAMAGE_STATUS = [
        'pending'            => ['Pending Verification', 'warning'],
        'assigned'           => ['Pending Verification', 'warning'],
        'under_verification' => ['Pending Verification', 'warning'],
        'flagged'            => ['Under Review', 'info'],
        'verified'           => ['Verified', 'success'],
        'approved'           => ['Verified', 'success'],
        'rejected'           => ['Rejected', 'danger'],
    ];

    private const RECEIPT = [
        'confirmed_received'   => 'Assistance received',
        'pending_confirmation' => 'Assistance awaiting your confirmation',
        'not_received'         => 'Marked as not received',
    ];

    public function index(Request $request)
    {
        $farmer = Farmer::where('user_id', Auth::id())->firstOrFail();

        $type = array_key_exists((string) $request->query('type'), self::TYPES) ? $request->query('type') : null;

        $rows = collect();

        if ($type !== 'planting') {
            $reports = $farmer->damageReports()->active()->get();

            $assistance = DB::table('assistance_distributions')
                ->whereIn('damage_report_id', $reports->pluck('id'))
                ->where('farmer_id', $farmer->id)
                ->pluck('receipt_status', 'damage_report_id');

            foreach ($reports as $report) {
                [$label, $tone] = self::DAMAGE_STATUS[$report->status] ?? [ucfirst((string) $report->status), 'default'];

                $rows->push([
                    'id'     => $report->reference,
                    'type'   => 'Crop Damage',
                    'date'   => $report->submitted_at ?? $report->created_at,
                    'status' => $label,
                    'tone'   => $tone,
                    'note'   => self::RECEIPT[$assistance[$report->id] ?? null] ?? null,
                    'url'    => route('farmer.reports.show', $report),
                ]);
            }
        }

        if ($type !== 'damage') {
            foreach ($farmer->plantingRecords()->active()->get() as $record) {
                $rows->push([
                    'id'     => 'CP-' . str_pad((string) $record->id, 4, '0', STR_PAD_LEFT),
                    'type'   => 'Crop Planting',
                    'date'   => $record->date_submitted,
                    'status' => 'Submitted',
                    'tone'   => 'primary',
                    'note'   => null,
                    'url'    => route('farmer.planting.show', $record),
                ]);
            }
        }

        $rows    = $rows->sortByDesc(fn ($row) => $row['date']?->timestamp ?? 0)->values();
        $perPage = 15;
        $page    = max(1, (int) $request->query('page', 1));

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('farmer.my-reports', ['rows' => $paginator, 'type' => $type, 'types' => self::TYPES]);
    }
}
