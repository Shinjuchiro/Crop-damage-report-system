<?php

use App\Http\Controllers\Association\AssistanceController as AssociationAssistanceController;
use App\Http\Controllers\Association\DashboardController as AssociationDashboardController;
use App\Http\Controllers\Association\MapController as AssociationMapController;
use App\Http\Controllers\Association\MemberController as AssociationMemberController;
use App\Http\Controllers\Association\MonitoringController as AssociationMonitoringController;
use App\Http\Controllers\Association\NotificationController as AssociationNotificationController;
use App\Http\Controllers\Association\ReportController as AssociationReportController;
use App\Http\Controllers\Association\SettingsController as AssociationSettingsController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
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
use App\Http\Controllers\Technician\MonitoringController as TechnicianMonitoringController;
use App\Http\Controllers\Technician\NotificationController as TechnicianNotificationController;
use App\Http\Controllers\Technician\ProfileController as TechnicianProfileController;
use App\Http\Controllers\Technician\SettingsController as TechnicianSettingsController;
use App\Http\Controllers\Technician\SummaryController as TechnicianSummaryController;
use App\Http\Controllers\MAO\ArchiveController;
use App\Http\Controllers\MAO\AssistanceAllocationController;
use App\Http\Controllers\MAO\AssistanceController;
use App\Http\Controllers\MAO\AssociationController;
use App\Http\Controllers\MAO\CropController;
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
    Route::post('/register', [RegisterController::class, 'store']);

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
    Route::get('/account/pending', fn () => view('auth.pending'))->name('account.pending');
    Route::get('/account/blocked', fn () => view('auth.blocked'))->name('account.blocked');

    // Help page. The "Need help?" button in the top bar and "Contact Admin"
    // on the login screen both point here, so neither is a dead end.
    Route::get('/help', fn () => view('help'))->name('help');
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

        /* ---------- Crop planting activity ---------- */
        Route::get('/planting', [FarmerPlantingController::class, 'index'])->name('planting.index');
        Route::get('/planting/create', [FarmerPlantingController::class, 'create'])->name('planting.create');
        Route::post('/planting', [FarmerPlantingController::class, 'store'])->name('planting.store');
        Route::get('/planting/{planting}', [FarmerPlantingController::class, 'show'])->name('planting.show');
        Route::get('/planting/{planting}/edit', [FarmerPlantingController::class, 'edit'])->name('planting.edit');
        Route::put('/planting/{planting}', [FarmerPlantingController::class, 'update'])->name('planting.update');

        /* ---------- Crop damage reporting ---------- */
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

        Route::get('/history', [TechnicianAssignmentController::class, 'history'])
            ->name('history.index');

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

        /* ---------- Barangay-wide monitoring (read only) ----------
           Wider than "my assignments": every planting record and damage
           report filed in the barangays this technician has been sent to,
           not just the ones on their own desk right now. */
        Route::get('/planting', [TechnicianMonitoringController::class, 'planting'])
            ->name('planting.index');

        Route::get('/damage-reports', [TechnicianMonitoringController::class, 'damageReports'])
            ->name('damage.index');

        /* ---------- Personal working map ----------
           Only this technician's own assigned reports, not the whole
           municipality (that view belongs to the MAO map). */
        Route::get('/map', [TechnicianMapController::class, 'index'])->name('map.index');

        /* ---------- Personal performance summary ----------
           On screen only. Not to be confused with the MAO's Monthly Reports
           module, which covers the whole office and exports PDF/Excel. */
        Route::get('/summary', [TechnicianSummaryController::class, 'index'])->name('summaries.index');

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

        /* ---------- Monitoring ---------- */
        Route::get('/planting', [AssociationMonitoringController::class, 'planting'])
            ->name('planting.index');
        Route::get('/damage-reports', [AssociationMonitoringController::class, 'damageReports'])
            ->name('reports.index');

        /* ---------- Assistance ----------
           The missing link in the chain: MAO allocates to the association
           here, and the association records giving it to a member. Until
           these routes existed, assistance_distributions could never fill up
           and a farmer's Assistance page was always empty. */
        Route::get('/assistance', [AssociationAssistanceController::class, 'index'])
            ->name('assistance.index');
        Route::get('/assistance/{allocation}', [AssociationAssistanceController::class, 'show'])
            ->name('assistance.show');
        Route::get('/assistance/{allocation}/distribute', [AssociationAssistanceController::class, 'distribute'])
            ->name('assistance.distribute');
        Route::post('/assistance/{allocation}/distribute', [AssociationAssistanceController::class, 'store'])
            ->name('assistance.store');

        /* ---------- Notifications ---------- */
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
        Route::put('/membership-applications/{farmer}/archive', [MembershipApplicationController::class, 'archive'])
            ->name('membership-applications.archive');
        Route::put('/membership-applications/{farmer}/restore', [MembershipApplicationController::class, 'restore'])
            ->name('membership-applications.restore');
        Route::delete('/membership-applications/{farmer}', [MembershipApplicationController::class, 'destroy'])
            ->name('membership-applications.destroy');

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
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
            ->name('users.destroy');

        /* ---------- Farmer directory ---------- */
        Route::get('/farmers', [FarmerDirectoryController::class, 'index'])->name('farmers.index');
        Route::get('/farmers/{farmer}', [FarmerDirectoryController::class, 'show'])->name('farmers.show');

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
        Route::delete('/crop-planting/{plantingRecord}', [CropPlantingMonitorController::class, 'destroy'])
            ->name('crop-planting.destroy');

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
        Route::delete('/damage-reports/{damageReport}', [DamageReportMonitorController::class, 'destroy'])
            ->name('damage-reports.destroy');

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
        Route::get('/assistance-allocations/eligible-beneficiaries', [AssistanceAllocationController::class, 'eligibleBeneficiaries'])
            ->name('assistance-allocations.eligible-beneficiaries');

        // Every distribution a farmer marked "not received", office-wide -
        // see AssistanceAllocationController::disputes().
        Route::get('/assistance-allocations/disputes', [AssistanceAllocationController::class, 'disputes'])
            ->name('assistance-allocations.disputes');

        Route::get('/assistance-allocations/{allocation}', [AssistanceAllocationController::class, 'show'])
            ->name('assistance-allocations.show');
        Route::put('/assistance-allocations/{allocation}/status', [AssistanceAllocationController::class, 'updateStatus'])
            ->name('assistance-allocations.status');

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
        Route::delete('/notifications/{notification}', [NotificationBroadcastController::class, 'destroy'])
            ->name('notifications.destroy');

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

        Route::resource('associations', AssociationController::class)->except(['show']);
        Route::put('/associations/{association}/archive', [AssociationController::class, 'archive'])
            ->name('associations.archive');
        Route::put('/associations/{association}/restore', [AssociationController::class, 'restore'])
            ->name('associations.restore');

        Route::resource('assistance', AssistanceController::class)->except(['show'])
            ->parameters(['assistance' => 'assistance']);
        Route::put('/assistance/{assistance}/archive', [AssistanceController::class, 'archive'])
            ->name('assistance.archive');
        Route::put('/assistance/{assistance}/restore', [AssistanceController::class, 'restore'])
            ->name('assistance.restore');

        Route::resource('crops', CropController::class)->except(['show']);
        Route::put('/crops/{crop}/archive', [CropController::class, 'archive'])->name('crops.archive');
        Route::put('/crops/{crop}/restore', [CropController::class, 'restore'])->name('crops.restore');

        Route::resource('disasters', DisasterController::class)->except(['show']);
        Route::put('/disasters/{disaster}/archive', [DisasterController::class, 'archive'])->name('disasters.archive');
        Route::put('/disasters/{disaster}/restore', [DisasterController::class, 'restore'])->name('disasters.restore');

        /* ---------- Archive ----------
           One page that lists everything the office has archived across
           every module and links to each type's own restore action. */
        Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
    });
