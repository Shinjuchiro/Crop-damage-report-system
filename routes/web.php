<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Association\AnnouncementController as AssociationAnnouncementController;
use App\Http\Controllers\Association\AssistanceController as AssociationAssistanceController;
use App\Http\Controllers\Association\CropManagementController as AssociationCropManagementController;
use App\Http\Controllers\Association\DashboardController as AssociationDashboardController;
use App\Http\Controllers\Association\MapController as AssociationMapController;
use App\Http\Controllers\Association\MemberController as AssociationMemberController;
use App\Http\Controllers\Association\MembershipApplicationController as AssociationMembershipApplicationController;
use App\Http\Controllers\Association\MonitoringController as AssociationMonitoringController;
use App\Http\Controllers\Association\NotificationController as AssociationNotificationController;
use App\Http\Controllers\Association\ReportController as AssociationReportController;
use App\Http\Controllers\Association\SettingsController as AssociationSettingsController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Farmer\ApplicationController as FarmerApplicationController;
use App\Http\Controllers\Farmer\AssistanceController as FarmerAssistanceController;
use App\Http\Controllers\Farmer\DamageReportController as FarmerDamageReportController;
use App\Http\Controllers\Farmer\DashboardController as FarmerDashboardController;
use App\Http\Controllers\Farmer\NotificationController as FarmerNotificationController;
use App\Http\Controllers\Farmer\PlantingController as FarmerPlantingController;
use App\Http\Controllers\Farmer\ProfileController as FarmerProfileController;
use App\Http\Controllers\Farmer\SettingsController as FarmerSettingsController;
use App\Http\Controllers\Association\ProfileController as AssociationProfileController;
use App\Http\Controllers\Technician\ArchiveController as TechnicianArchiveController;
use App\Http\Controllers\Technician\AssignmentController as TechnicianAssignmentController;
use App\Http\Controllers\Technician\DashboardController as TechnicianDashboardController;
use App\Http\Controllers\Technician\InspectionController as TechnicianInspectionController;
use App\Http\Controllers\Technician\MapController as TechnicianMapController;
// Technician\MonitoringController's two routes were retired in Oct 2026
// (revision section 4) - see the note in the technician group below. The
// import goes with them so a stale reference cannot be revived by accident.
use App\Http\Controllers\Technician\NotificationController as TechnicianNotificationController;
use App\Http\Controllers\Technician\ProfileController as TechnicianProfileController;
use App\Http\Controllers\Technician\SettingsController as TechnicianSettingsController;
use App\Http\Controllers\Technician\SummaryController as TechnicianSummaryController;
use App\Http\Controllers\MAO\ArchiveController;
use App\Http\Controllers\MAO\AssistanceAllocationController;
use App\Http\Controllers\MAO\AssistanceController;
use App\Http\Controllers\MAO\AssociationController;
use App\Http\Controllers\MAO\CropController;
use App\Http\Controllers\MAO\CropManagementController;
use App\Http\Controllers\MAO\CropPlantingMonitorController;
use App\Http\Controllers\MAO\DamageReportMonitorController;
use App\Http\Controllers\MAO\DashboardController as MaoDashboardController;
use App\Http\Controllers\MAO\DisasterController;
use App\Http\Controllers\MAO\FarmerDirectoryController;
use App\Http\Controllers\MAO\MapController;
use App\Http\Controllers\MAO\MembershipApplicationController;
use App\Http\Controllers\MAO\NotificationBroadcastController;
use App\Http\Controllers\MAO\NotificationInboxController;
use App\Http\Controllers\MAO\ProfileController as MaoProfileController;
use App\Http\Controllers\MAO\ReportController;
use App\Http\Controllers\MAO\SettingsController;
use App\Http\Controllers\MAO\SmsHistoryController;
use App\Http\Controllers\MAO\UserManagementController;
use App\Http\Controllers\MAO\ValidationMonitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // ONLY farmers self-register
    Route::get('/register', [RegisterController::class, 'show'])->name('register');

    // The throttle here is coarse on purpose: it keys on the IP address, and
    // bootstrap/app.php has to trust every proxy for Railway's HTTPS
    // termination, so a determined caller can change it per request. There is
    // no stable identifier to key on before validation has run, unlike login
    // and the password-reset screens, which key on the account being targeted.
    // It stops casual signup spam and should not be counted as more than that.
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:10,1');

    /*
     | "Is this email already taken?" asked by the registration wizard while
     | the farmer is still on step 2, so they find out there rather than after
     | filling in all four steps and uploading a certificate. Throttled harder
     | than the form itself because it is cheap to call in a loop; it answers
     | only yes or no, for one value at a time.
     */
    Route::get('/register/availability', [RegisterController::class, 'availability'])
        ->middleware('throttle:30,1')
        ->name('register.availability');

    // Google login - farmers only (enforced inside the controller)
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

    // Forgot password - every role uses this same flow (email, phone number
    // and password all live on `users`, common to all four roles). Email is
    // Laravel's own password-broker link; OTP is the SMS alternative for a
    // farmer without easy email access (App\Models\PasswordResetOtp).
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

    // Throttled (not just for abuse - each send costs the office an actual
    // SMS credit through Semaphore).
    Route::post('/forgot-password/otp', [PasswordResetController::class, 'sendOtp'])
        ->middleware('throttle:5,1')->name('password.otp.send');
    Route::get('/forgot-password/verify', [PasswordResetController::class, 'showVerifyOtpForm'])->name('password.otp.verify');
    Route::post('/forgot-password/verify', [PasswordResetController::class, 'verifyOtpAndReset'])
        ->middleware('throttle:10,1')->name('password.otp.update');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Holding screens for accounts that are not yet usable
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    /* The holding screen is a controller now rather than a closure
       returning a view (revision step 6). It used to tell everybody their
       registration was awaiting the Municipal Agriculture Office, which was
       true when there was one queue and is wrong in three of the four
       stages an application now passes through - and worse than wrong in
       the fourth, where the farmer is the one being waited on. */
    Route::get('/account/pending', [FarmerApplicationController::class, 'pending'])
        ->name('account.pending');
    /* A controller rather than a closure returning the view, for the same
       reason account.pending became one: the page has to say something
       specific to the person reading it. A rejected farmer can sign in and
       this is the only screen they ever reach, so it is the only place the
       office's rejection reason can actually be read - the notification
       carrying it sits behind the 'active' middleware they cannot pass. */
    Route::get('/account/blocked', [FarmerApplicationController::class, 'blocked'])
        ->name('account.blocked');

    /* Correcting a returned application. Deliberately NOT behind 'active':
       these two exist to be reached by a farmer whose account is still
       pending, which is every farmer who has one of these to correct. The
       controller checks that the signed-in user is a farmer, that the
       application is their own, and that it is actually returned - see
       Farmer\ApplicationController::returnedFarmer(). */
    Route::get('/account/application/correct', [FarmerApplicationController::class, 'edit'])
        ->name('farmer.application.correct');
    Route::put('/account/application', [FarmerApplicationController::class, 'update'])
        ->name('farmer.application.resubmit');

    /* ---------- Uploaded files ----------
       Every attachment in the system is read through here and nowhere else
       (revision section 3.4). They used to be served straight off
       public/storage by the web server, with no session and no role check,
       which meant a barangay certificate was readable by anybody holding
       the URL whether they were signed in or not.

       In the plain 'auth' group on purpose, not behind 'active' or a role:
       a farmer correcting a returned application is still 'pending', and
       that screen shows them the certificate they already uploaded.
       AttachmentController authorises every request per FILE, asking the
       same question the page around it asks, so a role gate out here would
       only ever be a second, weaker copy of it.

       GET only. Nothing uploads through these routes. */
    Route::get('/files/farmers/{farmer}/certificate', [AttachmentController::class, 'barangayCertificate'])
        ->name('attachments.certificate');
    Route::get('/files/farmers/documents/{document}', [AttachmentController::class, 'supportingDocument'])
        ->name('attachments.supporting-document');
    Route::get('/files/damage-photos/{photo}', [AttachmentController::class, 'damagePhoto'])
        ->name('attachments.damage-photo');
    Route::get('/files/inspection-photos/{photo}', [AttachmentController::class, 'inspectionPhoto'])
        ->name('attachments.inspection-photo');
    Route::get('/files/planting-photos/{photo}', [AttachmentController::class, 'plantingPhoto'])
        ->name('attachments.planting-photo');
    Route::get('/files/allocation-documents/{document}', [AttachmentController::class, 'allocationDocument'])
        ->name('attachments.allocation-document');
    Route::get('/files/distribution-photos/{photo}', [AttachmentController::class, 'distributionPhoto'])
        ->name('attachments.distribution-photo');

    // Help page. The "Need help?" button in the top bar and "Contact Admin"
    // on the login screen both point here, so neither is a dead end.
    Route::get('/help', fn () => view('help'))->name('help');

    /* ---------- Today's weather over Tanza ----------
       What the dashboard card asks, so the browser never sees the
       OpenWeatherMap key: it asks this application, this application asks
       OpenWeatherMap. Answers with the reading or 204 No Content when there
       is nothing to show (no key, no network, service down), which is what
       keeps the card hidden rather than empty.

       Behind auth with everything else: it is not a public endpoint, and
       the service caches for ten minutes, so a farmer refreshing their
       dashboard costs nothing. */
    Route::get('/weather', function (\App\Services\Weather $weather) {
        $reading = $weather->today();

        return $reading
            ? response()->json($reading)
            : response()->noContent();
    })->name('weather');
});

/*
|--------------------------------------------------------------------------
| Role-protected areas
| 'active' = must be approved (blocks pending farmers)
| 'role:x' = backend enforcement, not just a hidden menu item
|--------------------------------------------------------------------------
*/

// ---------- FARMER ----------
Route::middleware(['auth', 'active', 'role:farmer'])
    ->prefix('farmer')->name('farmer.')->group(function () {

        Route::get('/dashboard', [FarmerDashboardController::class, 'index'])->name('dashboard');
        Route::put('/dashboard/welcome-dismiss', [FarmerDashboardController::class, 'dismissApprovalWelcome'])
            ->name('dashboard.welcome-dismiss');

        Route::get('/profile', [FarmerProfileController::class, 'show'])->name('profile');
        Route::put('/profile/photo', [FarmerProfileController::class, 'updatePhoto'])
            ->name('profile.photo.update');
        Route::delete('/profile/photo', [FarmerProfileController::class, 'removePhoto'])
            ->name('profile.photo.remove');

        /* ---------- My Membership Application ----------
           Revision section 4 lists this in the farmer's navigation. Read
           only: which association they belong to, who verified them, who
           approved it, and the application's history.

           Distinct from the two screens already in the 'auth' group above.
           Those are for a farmer who CANNOT use the system yet - pending,
           returned for correction, or rejected - and this one is behind
           'active', so only an approved farmer ever opens it. The three do
           not overlap. */
        Route::get('/application', [FarmerApplicationController::class, 'status'])
            ->name('application.index');

        /* ---------- Crop planting activity ---------- */
        Route::get('/planting', [FarmerPlantingController::class, 'index'])->name('planting.index');
        Route::get('/planting/create', [FarmerPlantingController::class, 'create'])->name('planting.create');
        Route::post('/planting', [FarmerPlantingController::class, 'store'])->name('planting.store');
        Route::get('/planting/{planting}', [FarmerPlantingController::class, 'show'])->name('planting.show');
        Route::get('/planting/{planting}/edit', [FarmerPlantingController::class, 'edit'])->name('planting.edit');
        Route::put('/planting/{planting}', [FarmerPlantingController::class, 'update'])->name('planting.update');

        /* ---------- Crop damage reporting ---------- */
        Route::get('/my-reports', [\App\Http\Controllers\Farmer\MyReportsController::class, 'index'])->name('my-reports.index');
        Route::get('/reports', [FarmerDamageReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/create', [FarmerDamageReportController::class, 'create'])->name('reports.create');
        Route::post('/reports', [FarmerDamageReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}', [FarmerDamageReportController::class, 'show'])->name('reports.show');

        /* ---------- Assistance received ---------- */
        Route::get('/assistance', [FarmerAssistanceController::class, 'index'])->name('assistance.index');

        // The farmer's own answer to "did you actually receive this". Kept
        // separate from the association's record on purpose, so the two can
        // disagree and the office can see that they do.
        Route::put('/assistance/{distribution}/confirm', [FarmerAssistanceController::class, 'confirmReceipt'])
            ->name('assistance.confirm');

        // The same answer, given straight from the "selected for assistance"
        // card (Oct 2026: the MAO gives the assistance itself and nobody
        // records a hand-over first, so the farmer's confirmation is what
        // creates the hand-over record).
        Route::put('/assistance/selection/{selection}/confirm', [FarmerAssistanceController::class, 'confirmSelection'])
            ->name('assistance.confirm-selection');

        /* ---------- Notifications ---------- */
        Route::get('/notifications', [FarmerNotificationController::class, 'index'])
            ->name('notifications.index');
        Route::put('/notifications/read-all', [FarmerNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::get('/notifications/{notification}', [FarmerNotificationController::class, 'show'])
            ->name('notifications.show');

        /* ---------- Settings ----------
           Account email, phone and password only - everything else on a
           farmer's profile stays read-only (Farmer/ProfileController). */
        Route::get('/settings', [FarmerSettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [FarmerSettingsController::class, 'updateProfile'])->name('settings.update');
        Route::put('/settings/password', [FarmerSettingsController::class, 'updatePassword'])
            ->name('settings.password');
    });

// ---------- TECHNICIAN ----------
Route::middleware(['auth', 'active', 'role:technician'])
    ->prefix('technician')->name('technician.')->group(function () {

        // Technical Dashboard: tiles, the top of the queue, the summary donut.
        Route::get('/dashboard', [TechnicianDashboardController::class, 'index'])->name('dashboard');

        /* ---------- Profile (photo only - see ManagesProfilePhoto) ---------- */
        Route::get('/profile', [TechnicianProfileController::class, 'index'])->name('profile');
        Route::put('/profile/photo', [TechnicianProfileController::class, 'updatePhoto'])
            ->name('profile.photo.update');
        Route::delete('/profile/photo', [TechnicianProfileController::class, 'removePhoto'])
            ->name('profile.photo.remove');

        /* ---------- The sidebar list pages ----------
           Declared before /reports/{report} so "reports" is never mistaken
           for a report id. */
        Route::get('/reports', [TechnicianAssignmentController::class, 'index'])
            ->name('reports.index');

        Route::get('/validation', [TechnicianAssignmentController::class, 'validation'])
            ->name('validation.index');

        /* The inspection briefing: what the farmer recorded planting set
           against what they are reporting damaged, and the one place
           Start Inspection lives. Declared after /validation so the word
           "validation" is never read as a report id. */
        Route::get('/validation/{report}', [TechnicianInspectionController::class, 'briefing'])
            ->name('validation.show');

        Route::get('/history', [TechnicianAssignmentController::class, 'history'])
            ->name('history.index');

        /* One completed inspection end to end: planting record, then the
           farmer's damage report, then what the technician assessed. */
        Route::get('/history/{validation}', [TechnicianAssignmentController::class, 'historyShow'])
            ->name('history.show');
        Route::get('/history/{validation}/pdf', [TechnicianSummaryController::class, 'assessmentPdf'])
            ->name('history.pdf');

        /* ---------- Field inspection ----------
           The technician works ON an existing damage report, so every route
           here is keyed by the report, not by a separate inspection record
           (proposal section 29). */
        Route::get('/reports/{report}', [TechnicianInspectionController::class, 'show'])
            ->name('reports.show');

        Route::post('/reports/{report}/start', [TechnicianInspectionController::class, 'start'])
            ->name('inspection.start');

        Route::get('/reports/{report}/inspect', [TechnicianInspectionController::class, 'edit'])
            ->name('inspection.edit');

        Route::put('/reports/{report}/inspect', [TechnicianInspectionController::class, 'update'])
            ->name('inspection.update');

        /* ---------- RETIRED: barangay-wide monitoring (Oct 2026) ----------

           Two routes used to live here, 'planting.index' and 'damage.index',
           serving Technician\MonitoringController: every planting record and
           damage report filed in any barangay this technician had ever been
           sent to, whoever was handling each case. They were removed from the
           technician sidebar in the step-1 nav rewrite and the pages stayed
           reachable by URL, which is not a restriction - revision section 9
           is explicit that hiding a control is not an access rule.

           Revision section 4 settles it: "Do not give technicians access to
           unrelated planting-report management functions." A technician's job
           is the reports assigned to them, which Assigned Reports, Validation
           and Inspection History already cover.

           This also answers the data-minimisation question raised by the
           privacy sweep (BUILD-STATUS 58.9): those pages showed one
           technician the planting records and damage reports of every farmer
           in their barangays, including production costs and farmgate prices,
           for cases that were never theirs. The spec's answer is that they
           should not see them at all.

           Technician\MonitoringController and the two views under
           resources/views/technician/{planting,damage}/ are orphans now and
           can be git rm'd. Left in place rather than deleted here, as with
           the other retired screens, so removing a file is a separate and
           reversible decision. 'technician.damage.index' also came out of
           InspectionController::backToList()'s map, which passes every name
           to route() and would have thrown on a retired one. */

        /* ---------- Personal working map ----------
           Only this technician's own assigned reports, not the whole
           municipality (that view belongs to the MAO map). */
        Route::get('/map', [TechnicianMapController::class, 'index'])->name('map.index');

        /* ---------- Personal performance summary ----------
           On screen only. Not to be confused with the MAO's Monthly Reports
           module, which covers the whole office and exports PDF/Excel. */
        Route::get('/summary', [TechnicianSummaryController::class, 'index'])->name('summaries.index');
        Route::get('/summary/export', [TechnicianSummaryController::class, 'exportSummary'])->name('summaries.export');

        /* ---------- Archive ----------
           Reports no longer in this technician's queue: reassigned away
           from them after they inspected it, or later rejected by the MAO. */
        Route::get('/archive', [TechnicianArchiveController::class, 'index'])->name('archive.index');

        /* ---------- Notifications ---------- */
        Route::get('/notifications', [TechnicianNotificationController::class, 'index'])
            ->name('notifications.index');
        Route::put('/notifications/read-all', [TechnicianNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::get('/notifications/{notification}', [TechnicianNotificationController::class, 'show'])
            ->name('notifications.show');

        /* ---------- Settings ----------
           Account email, phone and password only. */
        Route::get('/settings', [TechnicianSettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [TechnicianSettingsController::class, 'updateProfile'])->name('settings.update');
        Route::put('/settings/password', [TechnicianSettingsController::class, 'updatePassword'])
            ->name('settings.password');
    });

// ---------- ASSOCIATION ----------
Route::middleware(['auth', 'active', 'role:association'])
    ->prefix('association')->name('association.')->group(function () {

        Route::get('/dashboard', [AssociationDashboardController::class, 'index'])->name('dashboard');

        /* ---------- Profile (photo only - see ManagesProfilePhoto) ---------- */
        Route::get('/profile', [AssociationProfileController::class, 'index'])->name('profile');
        Route::put('/profile/photo', [AssociationProfileController::class, 'updatePhoto'])
            ->name('profile.photo.update');
        Route::delete('/profile/photo', [AssociationProfileController::class, 'removePhoto'])
            ->name('profile.photo.remove');

        /* ---------- Members ---------- */
        Route::get('/members', [AssociationMemberController::class, 'index'])->name('members.index');
        Route::get('/members/{member}', [AssociationMemberController::class, 'show'])->name('members.show');

        /* ---------- Membership Applications (revision step 6) ----------
           The one place in this role that is not read-only. The association
           reads a new registration before the office does, because the
           association is the body that knows whether the applicant is one
           of its members.

           Three PUTs and no POST or DELETE: each one moves an existing
           application to a different stage, which is an update. The
           association cannot approve or reject - those stay with the office
           (see Association\MembershipApplicationController). */
        Route::get('/membership-applications', [AssociationMembershipApplicationController::class, 'index'])
            ->name('membership-applications.index');
        Route::put('/membership-applications/{farmer}/start-review', [AssociationMembershipApplicationController::class, 'startReview'])
            ->name('membership-applications.start-review');
        Route::put('/membership-applications/{farmer}/forward', [AssociationMembershipApplicationController::class, 'forward'])
            ->name('membership-applications.forward');
        Route::put('/membership-applications/{farmer}/return', [AssociationMembershipApplicationController::class, 'returnForCorrection'])
            ->name('membership-applications.return');

        /* ---------- Crop Management Report ----------
           Same hub as the office has, with two cards: this role has no
           validation screen of its own (revision section 2). */
        Route::get('/crop-management', [AssociationCropManagementController::class, 'index'])
            ->name('crop-management.index');

        /* ---------- Monitoring ---------- */
        Route::get('/planting', [AssociationMonitoringController::class, 'planting'])
            ->name('planting.index');
        Route::get('/damage-reports', [AssociationMonitoringController::class, 'damageReports'])
            ->name('reports.index');
        Route::get('/validations', [AssociationMonitoringController::class, 'validations'])
            ->name('validations.index');

        /* ---------- Assistance (read-only since Oct 2026) ----------
           Two GETs and nothing else. The association used to record each
           hand-out here, and those two routes are gone: the Municipal
           Agriculture Office hands assistance to the farmer and records it
           (mao.assistance-allocations.distribute). The association monitors,
           which is the only thing section 61 ever gave it; the Distribute
           screen was added later because without it nothing in the system
           could write an assistance_distributions row at all.

           The two removed names were association.assistance.distribute and
           association.assistance.store. Nothing links to them any more. */
        Route::get('/assistance', [AssociationAssistanceController::class, 'index'])
            ->name('assistance.index');
        Route::get('/assistance/{allocation}', [AssociationAssistanceController::class, 'show'])
            ->name('assistance.show');

        /* ---------- Announcements ----------
           What this association tells its OWN members: a meeting, a
           reminder, a deadline. Written here, delivered to the members'
           bells, and invisible to the office and to every other
           association (see Association\AnnouncementController).

           Separate from Notifications below, which is this officer's own
           inbox - what has been sent TO them. The bell in the top bar is
           the only door to that one, for every role. */
        Route::get('/announcements', [AssociationAnnouncementController::class, 'index'])
            ->name('announcements.index');
        Route::post('/announcements', [AssociationAnnouncementController::class, 'store'])
            ->name('announcements.store');
        Route::put('/announcements/{announcement}/archive', [AssociationAnnouncementController::class, 'archive'])
            ->name('announcements.archive');
        Route::put('/announcements/{announcement}/restore', [AssociationAnnouncementController::class, 'restore'])
            ->name('announcements.restore');

        /* ---------- Notifications ---------- */
        Route::get('/sms-history', [\App\Http\Controllers\Association\SmsHistoryController::class, 'index'])
            ->name('sms-history.index');
        Route::get('/notifications', [AssociationNotificationController::class, 'index'])
            ->name('notifications.index');
        Route::put('/notifications/read-all', [AssociationNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::get('/notifications/{notification}', [AssociationNotificationController::class, 'show'])
            ->name('notifications.show');

        /* ---------- Map ----------
           This association's own members only - never the MAO's
           per-association-bubble map (see AssociationMapController). */
        Route::get('/map', [AssociationMapController::class, 'index'])->name('map.index');

        /* ---------- Monthly reports ----------
           A scoped-down copy of MAO's Monthly Reports page (sections 74-76):
           same MonthlyReportBuilder, same Excel layout, filtered to this
           association's own members. See AssociationReportController. */
        Route::get('/reports', [AssociationReportController::class, 'index'])->name('summaries.index');
        Route::post('/reports', [AssociationReportController::class, 'store'])->name('summaries.store');
        Route::get('/reports/{year}/{month}', [AssociationReportController::class, 'show'])
            ->whereNumber(['year', 'month'])->name('summaries.show');
        Route::get('/reports/{year}/{month}/pdf', [AssociationReportController::class, 'downloadPdf'])
            ->whereNumber(['year', 'month'])->name('summaries.pdf');
        Route::get('/reports/{year}/{month}/excel', [AssociationReportController::class, 'downloadExcel'])
            ->whereNumber(['year', 'month'])->name('summaries.excel');

        /* ---------- Settings ----------
           Account email, phone and password only. */
        Route::get('/settings', [AssociationSettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [AssociationSettingsController::class, 'updateProfile'])->name('settings.update');
        Route::put('/settings/password', [AssociationSettingsController::class, 'updatePassword'])
            ->name('settings.password');
    });

// ---------- MAO / SUPER ADMIN ----------
Route::middleware(['auth', 'active', 'role:mao'])
    ->prefix('mao')->name('mao.')->group(function () {

        Route::get('/dashboard', [MaoDashboardController::class, 'index'])->name('dashboard');

        /* ---------- Profile (photo only - see ManagesProfilePhoto) ---------- */
        Route::get('/profile', [MaoProfileController::class, 'index'])->name('profile');
        Route::put('/profile/photo', [MaoProfileController::class, 'updatePhoto'])
            ->name('profile.photo.update');
        Route::delete('/profile/photo', [MaoProfileController::class, 'removePhoto'])
            ->name('profile.photo.remove');

        /* ---------- Farmer registration review ---------- */
        Route::get('/membership-applications', [MembershipApplicationController::class, 'index'])
            ->name('membership-applications.index');
        Route::get('/membership-applications/{farmer}', [MembershipApplicationController::class, 'show'])
            ->name('membership-applications.show');
        Route::put('/membership-applications/{farmer}/approve', [MembershipApplicationController::class, 'approve'])
            ->name('membership-applications.approve');
        Route::put('/membership-applications/{farmer}/reject', [MembershipApplicationController::class, 'reject'])
            ->name('membership-applications.reject');
        /* Returning is not rejecting: the account stays pending, nothing the
           farmer entered is lost, and the corrected application goes back to
           the farmers' association rather than straight back here (revision
           step 6). */
        Route::put('/membership-applications/{farmer}/return', [MembershipApplicationController::class, 'returnForCorrection'])
            ->name('membership-applications.return');
        Route::put('/membership-applications/{farmer}/archive', [MembershipApplicationController::class, 'archive'])
            ->name('membership-applications.archive');
        Route::put('/membership-applications/{farmer}/restore', [MembershipApplicationController::class, 'restore'])
            ->name('membership-applications.restore');

        /* ---------- User management ---------- */
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/technicians/create', [UserManagementController::class, 'createTechnician'])
            ->name('users.technicians.create');
        Route::post('/users/technicians', [UserManagementController::class, 'storeTechnician'])
            ->name('users.technicians.store');
        Route::get('/users/association-officers/create', [UserManagementController::class, 'createAssociationOfficer'])
            ->name('users.association-officers.create');
        Route::post('/users/association-officers', [UserManagementController::class, 'storeAssociationOfficer'])
            ->name('users.association-officers.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/status', [UserManagementController::class, 'updateStatus'])
            ->name('users.status');

        /* ---------- Farmer directory ---------- */
        Route::get('/farmers', [FarmerDirectoryController::class, 'index'])->name('farmers.index');
        Route::get('/farmers/{farmer}', [FarmerDirectoryController::class, 'show'])->name('farmers.show');

        /* ---------- Crop Management Report ----------
           The hub the sidebar now points at. Crop Planting Monitoring, Crop
           Damage Monitoring and Validation Monitoring below are unchanged;
           this page is simply the one door to all three, so the menu lists
           jobs rather than tables (revision sections 1 and 5). */
        Route::get('/crop-management', [CropManagementController::class, 'index'])
            ->name('crop-management.index');

        /* ---------- Crop planting monitoring ---------- */
        Route::get('/crop-planting', [CropPlantingMonitorController::class, 'index'])
            ->name('crop-planting.index');
        Route::get('/crop-planting/{plantingRecord}', [CropPlantingMonitorController::class, 'show'])
            ->name('crop-planting.show');
        // Edit/update were removed (Sept 2026): MAO must not rewrite a
        // farmer-submitted planting record - only view, archive or restore it,
        // same rule already applied to every other farmer-submitted record.
        Route::put('/crop-planting/{plantingRecord}/archive', [CropPlantingMonitorController::class, 'archive'])
            ->name('crop-planting.archive');
        Route::put('/crop-planting/{plantingRecord}/restore', [CropPlantingMonitorController::class, 'restore'])
            ->name('crop-planting.restore');

        /* ---------- Crop damage monitoring ---------- */
        Route::get('/damage-reports', [DamageReportMonitorController::class, 'index'])
            ->name('damage-reports.index');
        Route::get('/damage-reports/{damageReport}', [DamageReportMonitorController::class, 'show'])
            ->name('damage-reports.show');
        Route::put('/damage-reports/{damageReport}/decide', [DamageReportMonitorController::class, 'decide'])
            ->name('damage-reports.decide');
        Route::put('/damage-reports/{damageReport}/disasters', [DamageReportMonitorController::class, 'updateDisasters'])
            ->name('damage-reports.disasters.update');
        Route::put('/damage-reports/{damageReport}/archive', [DamageReportMonitorController::class, 'archive'])
            ->name('damage-reports.archive');
        Route::put('/damage-reports/{damageReport}/restore', [DamageReportMonitorController::class, 'restore'])
            ->name('damage-reports.restore');

        /* ---------- Validation monitoring ---------- */
        Route::get('/validations', [ValidationMonitorController::class, 'index'])
            ->name('validations.index');
        Route::put('/validations/{damageReport}/assign', [ValidationMonitorController::class, 'assign'])
            ->name('validations.assign');

        /* ---------- Maps and visualization ---------- */
        Route::get('/map', [MapController::class, 'index'])->name('map.index');

        /* ---------- Assistance allocation ---------- */
        Route::get('/assistance-allocations', [AssistanceAllocationController::class, 'index'])
            ->name('assistance-allocations.index');
        Route::get('/assistance-allocations/create', [AssistanceAllocationController::class, 'create'])
            ->name('assistance-allocations.create');
        Route::post('/assistance-allocations', [AssistanceAllocationController::class, 'store'])
            ->name('assistance-allocations.store');
        Route::post('/assistance-allocations/draft', [AssistanceAllocationController::class, 'saveDraft'])
            ->name('assistance-allocations.draft.save');
        Route::delete('/assistance-allocations/draft/{draft}', [AssistanceAllocationController::class, 'destroyDraft'])
            ->name('assistance-allocations.draft.destroy');
        Route::put('/assistance-allocations/{allocation}/schedule', [AssistanceAllocationController::class, 'schedule'])
            ->name('assistance-allocations.schedule');

        // Every literal-segment route below must stay ahead of the
        // {allocation} wildcard, or Laravel's implicit route-model binding
        // tries to look up a model by that literal string as its id and
        // 404s instead of ever reaching the intended method.
        Route::get('/assistance-allocations/history', [AssistanceAllocationController::class, 'history'])
            ->name('assistance-allocations.history');
        Route::get('/assistance-allocations/distribution-tracking', [AssistanceAllocationController::class, 'distributionTracking'])
            ->name('assistance-allocations.distribution-tracking');
        Route::get('/assistance-allocations/export', [AssistanceAllocationController::class, 'exportOverview'])
            ->name('assistance-allocations.export');
        /* assistance-allocations.eligible-beneficiaries was removed in Oct
           2026. It served the allocation modal's checklist over JSON once an
           association had been chosen. The checklist is not scoped to an
           association any more, so the whole eligible list renders with the
           page and there is nothing to fetch. */

        // Every distribution a farmer marked "not received", office-wide -
        // see AssistanceAllocationController::disputes().
        Route::get('/assistance-allocations/disputes', [AssistanceAllocationController::class, 'disputes'])
            ->name('assistance-allocations.disputes');

        /* The office's assistance decision on one case: designated, or not
           (revision section 11.1). Keyed on the damage report, because that
           is the case being decided, which is also why it is declared above
           /{allocation} - "assistance-decisions" must never be read as an
           allocation id. */
        Route::put('/assistance-decisions/{damageReport}', [AssistanceAllocationController::class, 'decide'])
            ->name('assistance-decisions.store');

        Route::get('/assistance-allocations/{allocation}', [AssistanceAllocationController::class, 'show'])
            ->name('assistance-allocations.show');
        Route::put('/assistance-allocations/{allocation}/status', [AssistanceAllocationController::class, 'updateStatus'])
            ->name('assistance-allocations.status');

        /* Handing assistance to one designated farmer (Oct 2026). This was
           the association's job; the office does it now. Declared after
           /{allocation} like the other parameterised routes in this group,
           and both carry a literal segment after the id, so neither can be
           mistaken for the show route. */
        Route::get('/assistance-allocations/{allocation}/distribute', [AssistanceAllocationController::class, 'distribute'])
            ->name('assistance-allocations.distribute');
        Route::post('/assistance-allocations/{allocation}/distribute', [AssistanceAllocationController::class, 'storeDistribution'])
            ->name('assistance-allocations.distribute.store');

        /* ---------- Notifications and alerts ---------- */
        Route::get('/notifications', [NotificationBroadcastController::class, 'index'])
            ->name('notifications.index');
        Route::post('/notifications', [NotificationBroadcastController::class, 'store'])
            ->name('notifications.store');
        Route::put('/notifications/{notification}/send', [NotificationBroadcastController::class, 'send'])
            ->name('notifications.send');
        Route::put('/notifications/{notification}/archive', [NotificationBroadcastController::class, 'archive'])
            ->name('notifications.archive');
        Route::put('/notifications/{notification}/restore', [NotificationBroadcastController::class, 'restore'])
            ->name('notifications.restore');

        /* ---------- Notification inbox (the bell) ----------
           Separate from "Notifications and alerts" above, which is where MAO
           composes and reviews everything it has SENT. This is the mirror of
           the Farmer/Technician/Association bell: things addressed TO the
           signed-in MAO user (new registrations, new reports, and so on -
           see the Sept 2026 notification-system rule), on its own path so it
           never collides with the compose/manage routes just above. */
        Route::get('/notifications-inbox', [NotificationInboxController::class, 'index'])
            ->name('notifications.inbox');
        Route::put('/notifications-inbox/read-all', [NotificationInboxController::class, 'markAllRead'])
            ->name('notifications.inbox.read-all');
        Route::get('/notifications-inbox/{notification}', [NotificationInboxController::class, 'show'])
            ->name('notifications.inbox.show');

        /* ---------- SMS history ----------
           Proposal section 71: every text the system has sent or tried to
           send. Reads the same notifications.sms_status rows the alert
           composer above already writes - see SmsHistoryController. */
        Route::get('/sms-history', [SmsHistoryController::class, 'index'])->name('sms-history.index');

        /* ---------- Monthly reports ----------
           Proposal sections 74-76: pick a month/year, review, generate, then
           view on screen or download as PDF/Excel. The figures are always
           rebuilt live from the database (see MonthlyReportBuilder), and
           report_generations only keeps a small audit trail for history. */
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/{year}/{month}', [ReportController::class, 'show'])
            ->whereNumber(['year', 'month'])->name('reports.show');
        Route::get('/reports/{year}/{month}/pdf', [ReportController::class, 'downloadPdf'])
            ->whereNumber(['year', 'month'])->name('reports.pdf');
        Route::get('/reports/{year}/{month}/excel', [ReportController::class, 'downloadExcel'])
            ->whereNumber(['year', 'month'])->name('reports.excel');

        /* ---------- Settings and reference data ----------
           /settings itself is the reference-data hub (associations/crops/
           disasters/etc). The two PUT routes below are the MAO account's
           own login email/phone and password, via the same shared
           ManagesAccountSettings trait Farmer/Technician/Association use -
           see MAO/SettingsController and resources/views/settings/account.
           blade.php, included from mao/settings/index.blade.php. */
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [SettingsController::class, 'updateProfile'])->name('settings.update');
        Route::put('/settings/password', [SettingsController::class, 'updatePassword'])
            ->name('settings.password');

        Route::resource('associations', AssociationController::class)->except(['show', 'destroy']);
        Route::put('/associations/{association}/archive', [AssociationController::class, 'archive'])
            ->name('associations.archive');
        Route::put('/associations/{association}/restore', [AssociationController::class, 'restore'])
            ->name('associations.restore');

        Route::resource('assistance', AssistanceController::class)->except(['show', 'destroy'])
            ->parameters(['assistance' => 'assistance']);
        Route::put('/assistance/{assistance}/archive', [AssistanceController::class, 'archive'])
            ->name('assistance.archive');
        Route::put('/assistance/{assistance}/restore', [AssistanceController::class, 'restore'])
            ->name('assistance.restore');

        Route::resource('crops', CropController::class)->except(['show', 'destroy']);
        Route::put('/crops/{crop}/archive', [CropController::class, 'archive'])->name('crops.archive');
        Route::put('/crops/{crop}/restore', [CropController::class, 'restore'])->name('crops.restore');

        Route::resource('disasters', DisasterController::class)->except(['show', 'destroy']);
        Route::put('/disasters/{disaster}/archive', [DisasterController::class, 'archive'])->name('disasters.archive');
        Route::put('/disasters/{disaster}/restore', [DisasterController::class, 'restore'])->name('disasters.restore');

        /* ---------- Archive ----------
           One page that lists everything the office has archived across
           every module and links to each type's own restore action. */
        Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
    });
