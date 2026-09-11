<?php

use App\Http\Controllers\Association\AssistanceController as AssociationAssistanceController;
use App\Http\Controllers\Association\DashboardController as AssociationDashboardController;
use App\Http\Controllers\Association\MemberController as AssociationMemberController;
use App\Http\Controllers\Association\MonitoringController as AssociationMonitoringController;
use App\Http\Controllers\Association\NotificationController as AssociationNotificationController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Farmer\AssistanceController as FarmerAssistanceController;
use App\Http\Controllers\Farmer\DamageReportController as FarmerDamageReportController;
use App\Http\Controllers\Farmer\DashboardController as FarmerDashboardController;
use App\Http\Controllers\Farmer\NotificationController as FarmerNotificationController;
use App\Http\Controllers\Farmer\PlantingController as FarmerPlantingController;
use App\Http\Controllers\Farmer\ProfileController as FarmerProfileController;
use App\Http\Controllers\Technician\AssignmentController as TechnicianAssignmentController;
use App\Http\Controllers\Technician\DashboardController as TechnicianDashboardController;
use App\Http\Controllers\Technician\InspectionController as TechnicianInspectionController;
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
use App\Http\Controllers\MAO\SettingsController;
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

        Route::get('/profile', [FarmerProfileController::class, 'show'])->name('profile');

        /* ---------- Crop planting activity ---------- */
        Route::get('/planting', [FarmerPlantingController::class, 'index'])->name('planting.index');
        Route::get('/planting/create', [FarmerPlantingController::class, 'create'])->name('planting.create');
        Route::post('/planting', [FarmerPlantingController::class, 'store'])->name('planting.store');
        Route::get('/planting/{planting}', [FarmerPlantingController::class, 'show'])->name('planting.show');

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
    });

// ---------- TECHNICIAN ----------
Route::middleware(['auth', 'active', 'role:technician'])
    ->prefix('technician')->name('technician.')->group(function () {

        // Technical Dashboard: tiles, the top of the queue, the summary donut.
        Route::get('/dashboard', [TechnicianDashboardController::class, 'index'])->name('dashboard');

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
    });

// ---------- ASSOCIATION ----------
Route::middleware(['auth', 'active', 'role:association'])
    ->prefix('association')->name('association.')->group(function () {

        Route::get('/dashboard', [AssociationDashboardController::class, 'index'])->name('dashboard');

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
    });

// ---------- MAO / SUPER ADMIN ----------
Route::middleware(['auth', 'active', 'role:mao'])
    ->prefix('mao')->name('mao.')->group(function () {

        Route::get('/dashboard', [MaoDashboardController::class, 'index'])->name('dashboard');

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

        /* ---------- Crop planting monitoring ---------- */
        Route::get('/crop-planting', [CropPlantingMonitorController::class, 'index'])
            ->name('crop-planting.index');
        Route::get('/crop-planting/{plantingRecord}', [CropPlantingMonitorController::class, 'show'])
            ->name('crop-planting.show');

        /* ---------- Crop damage monitoring ---------- */
        Route::get('/damage-reports', [DamageReportMonitorController::class, 'index'])
            ->name('damage-reports.index');
        Route::get('/damage-reports/{damageReport}', [DamageReportMonitorController::class, 'show'])
            ->name('damage-reports.show');
        Route::put('/damage-reports/{damageReport}/decide', [DamageReportMonitorController::class, 'decide'])
            ->name('damage-reports.decide');

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

        /* ---------- Settings and reference data ---------- */
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

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
