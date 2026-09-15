<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Farmer Dashboard
 *
 * This is the first page a farmer sees after logging in.
 * It shows their own counts only, never other farmers' data.
 *
 * Important: all the numbers here are counted from the database
 * every time the page loads. We do not save them anywhere.
 * (The prompt says dashboard numbers must not be hardcoded.)
 */
class DashboardController extends Controller
{
    public function index()
    {
        $farmer = $this->farmer();

        // Proposal section 22: if the farmer has no activity for 3 months
        // they become Inactive. We check it here so the status they see
        // is always up to date.
        $farmer->refreshActivityStatus();

        // The one-time "Registration Approved" welcome. Stamped the moment
        // we decide to show it, not on the explicit "Continue to Dashboard"
        // click (dismissApprovalWelcome() below, kept for backward
        // compatibility) - stamping only on that click meant any other way
        // of leaving the dialog (tapping outside it, the phone's back
        // button, closing the tab/app, a fresh login before ever clicking
        // it) left approval_welcome_shown_at null, so it kept reappearing
        // on every login instead of showing exactly once.
        $showApprovalWelcome = $farmer->approval_welcome_shown_at === null;

        if ($showApprovalWelcome) {
            $farmer->forceFill(['approval_welcome_shown_at' => now()])->save();
        }

        // Count the reports grouped by status in one query instead of
        // running a separate count for every status.
        $reportCounts = DB::table('damage_reports')
            ->where('farmer_id', $farmer->id)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // How many times this farmer received assistance.
        $assistanceCount = DB::table('assistance_distributions')
            ->where('farmer_id', $farmer->id)
            ->count();

        return view('farmer.dashboard', [
            'farmer'         => $farmer,
            'plantingCount'  => $farmer->plantingRecords()->count(),
            'reportTotal'    => (int) $reportCounts->sum(),

            // "Still waiting" means nobody has finished inspecting it yet,
            // so we add up the three statuses before Verified.
            'pendingReports' => (int) ($reportCounts['pending'] ?? 0)
                                + (int) ($reportCounts['assigned'] ?? 0)
                                + (int) ($reportCounts['under_verification'] ?? 0),

            // Approved reports were verified first, so we count them here too.
            'verifiedReports' => (int) ($reportCounts['verified'] ?? 0)
                                 + (int) ($reportCounts['approved'] ?? 0),

            'assistanceCount' => $assistanceCount,

            // Only the 5 latest of each, because this is just a preview.
            // The full lists have their own pages.
            'recentPlanting' => $farmer->plantingRecords()
                ->with('crops.crop')          // load crops now to avoid N+1 queries
                ->latest('date_submitted')
                ->limit(5)
                ->get(),

            'recentReports' => $farmer->damageReports()
                ->with('crops.crop', 'disasters')
                ->latest()
                ->limit(5)
                ->get(),

            'unread' => Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->count(),

            // Section 91's "never surprise the person" review/confirm rule is
            // about important transactions, but the same spirit applies here
            // in miniature: the congratulatory dialog only ever appears once,
            // on the very first dashboard load after MAO approves this
            // farmer's registration. Already stamped above the moment this
            // was decided to be true.
            'showApprovalWelcome' => $showApprovalWelcome,
        ]);
    }

    /**
     * Marks the one-time "Registration Approved" welcome dialog as seen.
     * The dashboard itself already stamps this the moment it decides to show
     * the dialog (see index() above), so by the time this runs it is
     * normally already set - this just keeps the "Continue to Dashboard"
     * button's PUT request working the same as before, harmlessly.
     */
    public function dismissApprovalWelcome()
    {
        $this->farmer()->update(['approval_welcome_shown_at' => now()]);

        return redirect()->route('farmer.dashboard');
    }

    /**
     * Get the farmer record of whoever is logged in.
     *
     * We use firstOrFail() so if a farmer account somehow has no farmer
     * row, we get a clear 404 instead of a broken empty dashboard.
     */
    private function farmer(): Farmer
    {
        return Farmer::with('association', 'barangay', 'mainCrops.crop', 'user')
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }
}
