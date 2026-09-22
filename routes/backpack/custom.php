<?php

use App\Http\Controllers\Admin\AccountTransferWorkflowController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AskDataController;
use App\Http\Controllers\Admin\AttendanceCaptureController;
use App\Http\Controllers\Admin\AttendanceRegisterController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BranchCsvImportController;
use App\Http\Controllers\Admin\BroadcastComposerController;
use App\Http\Controllers\Admin\BulkAssignmentController;
use App\Http\Controllers\Admin\ChurchSettingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ErrorLogController;
use App\Http\Controllers\Admin\ExpenseApprovalController;
use App\Http\Controllers\Admin\FileSystemController;
use App\Http\Controllers\Admin\GoogleAuthController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\InitialRegistrationController;
use App\Http\Controllers\Admin\MemberCsvImportController;
use App\Http\Controllers\Admin\MemberTransferController;
use App\Http\Controllers\Admin\OfferingWorkflowController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SetupBulkCreateController;
use App\Http\Controllers\Admin\VisitorConversionController;
use App\Http\Middleware\EnsureAppAdministrator;
use App\Http\Middleware\EnsureSetupAdministrator;
use App\Services\SetupBulkRegistry;
use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::prefix(config('backpack.base.route_prefix', 'admin'))
    ->middleware(config('backpack.base.web_middleware', 'web'))
    ->group(function (): void {
        Route::middleware('guest:backpack')->group(function (): void {
            Route::get('register', [InitialRegistrationController::class, 'create'])->name('backpack.auth.register');
            Route::post('register', [InitialRegistrationController::class, 'store'])->middleware('throttle:5,1');
            Route::get('login', [AuthController::class, 'showLoginForm'])->name('backpack.auth.login');
            Route::post('login', [AuthController::class, 'login']);
            Route::get('login/google', [GoogleAuthController::class, 'redirect'])->name('backpack.auth.google.redirect');
            Route::get('login/google/callback', [GoogleAuthController::class, 'callback'])
                ->middleware('throttle:10,1')
                ->name('backpack.auth.google.callback');
        });

        Route::match(['get', 'post'], 'logout', [AuthController::class, 'logout'])
            ->middleware('auth:backpack')
            ->name('backpack.auth.logout');
    });

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::get('dashboard', DashboardController::class)->name('admin.dashboard');
    Route::get('help', HelpController::class)->name('admin.help');
    Route::get('ask-data', [AskDataController::class, 'index'])->name('admin.ask-data.index');
    Route::post('ask-data', [AskDataController::class, 'answer'])->middleware('throttle:30,1')->name('admin.ask-data.answer');
    Route::crud('zones', 'ZoneCrudController');
    Route::crud('zone-leaders', 'ZoneLeaderCrudController');
    Route::get('branches/import', [BranchCsvImportController::class, 'create'])->name('admin.branches.import.create');
    Route::post('branches/import', [BranchCsvImportController::class, 'store'])->name('admin.branches.import.store');
    Route::get('branches/import/sample', [BranchCsvImportController::class, 'sample'])->name('admin.branches.import.sample');
    Route::crud('branches', 'BranchCrudController');
    Route::crud('users', 'UserCrudController');
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('admin.users.impersonate');
    Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])->name('admin.impersonation.stop');
    Route::crud('roles', 'RoleCrudController');
    Route::crud('role-assignments', 'UserRoleCrudController');
    Route::get('members/import', [MemberCsvImportController::class, 'create'])->name('admin.members.import.create');
    Route::post('members/import', [MemberCsvImportController::class, 'store'])->name('admin.members.import.store');
    Route::get('members/import/sample', [MemberCsvImportController::class, 'sample'])->name('admin.members.import.sample');
    Route::crud('members', 'MemberCrudController');
    Route::crud('visitors', 'VisitorCrudController');
    Route::crud('branch-leaders', 'BranchLeaderCrudController');
    Route::crud('member-branches', 'MemberBranchCrudController');
    Route::crud('households', 'HouseholdCrudController');
    Route::get('members/{member}/transfer', [MemberTransferController::class, 'create'])->name('admin.members.transfer.create');
    Route::post('members/{member}/transfer', [MemberTransferController::class, 'store'])->name('admin.members.transfer.store');
    Route::get('visitors/{visitor}/convert', [VisitorConversionController::class, 'create'])->name('admin.visitors.convert.create');
    Route::post('visitors/{visitor}/convert', [VisitorConversionController::class, 'store'])->name('admin.visitors.convert.store');
    Route::get('attendance/capture', [AttendanceCaptureController::class, 'create'])->name('admin.attendance.create');
    Route::post('attendance/capture', [AttendanceCaptureController::class, 'store'])->name('admin.attendance.store');
    Route::get('attendance/register', [AttendanceRegisterController::class, 'index'])->name('admin.attendance.register');
    Route::post('attendance/register', [AttendanceRegisterController::class, 'store'])->name('admin.attendance.register.store');
    Route::get('bulk-assignments', [BulkAssignmentController::class, 'index'])->name('admin.bulk-assignments');
    Route::post('bulk-assignments/branch-departments', [BulkAssignmentController::class, 'branchDepartments'])->name('admin.bulk.branch-departments');
    Route::post('bulk-assignments/department-members', [BulkAssignmentController::class, 'departmentMembers'])->name('admin.bulk.department-members');
    Route::post('bulk-assignments/group-members', [BulkAssignmentController::class, 'groupMembers'])->name('admin.bulk.group-members');
    Route::post('bulk-assignments/household-members', [BulkAssignmentController::class, 'householdMembers'])->name('admin.bulk.household-members');
    Route::post('bulk-assignments/branch-leader-members', [BulkAssignmentController::class, 'branchLeaderMembers'])->name('admin.bulk.branch-leader-members');
    Route::post('bulk-assignments/shepherd-members', [BulkAssignmentController::class, 'shepherdMembers'])->name('admin.bulk.shepherd-members');
    Route::get('offerings', [OfferingWorkflowController::class, 'index'])->name('admin.offerings.index');
    Route::get('offerings/capture', [OfferingWorkflowController::class, 'create'])->name('admin.offerings.create');
    Route::post('offerings', [OfferingWorkflowController::class, 'store'])->name('admin.offerings.store');
    Route::post('offerings/{collection}/verify', [OfferingWorkflowController::class, 'verify'])->name('admin.offerings.verify');
    Route::post('offerings/{collection}/post', [OfferingWorkflowController::class, 'post'])->name('admin.offerings.post');
    Route::get('expense-approvals/queue', [ExpenseApprovalController::class, 'index'])->name('admin.expenses.approvals');
    Route::post('expenses/{expense}/decision', [ExpenseApprovalController::class, 'decide'])->name('admin.expenses.decide');
    Route::post('expenses/{expense}/pay', [ExpenseApprovalController::class, 'pay'])->name('admin.expenses.pay');
    Route::get('account-transfers', [AccountTransferWorkflowController::class, 'index'])->name('admin.transfers.index');
    Route::post('account-transfers', [AccountTransferWorkflowController::class, 'store'])->name('admin.transfers.store');
    Route::post('account-transfers/{transfer}/approve', [AccountTransferWorkflowController::class, 'approve'])->name('admin.transfers.approve');
    Route::post('account-transfers/{transfer}/complete', [AccountTransferWorkflowController::class, 'complete'])->name('admin.transfers.complete');
    Route::get('reports/finance', [ReportsController::class, 'finance'])->name('admin.reports.finance');
    Route::get('reports/attendance', [ReportsController::class, 'attendance'])->name('admin.reports.attendance');
    Route::get('reports/members', [ReportsController::class, 'members'])->name('admin.reports.members');
    Route::get('reports/attendance-records', [ReportsController::class, 'attendanceRecords'])->name('admin.reports.attendance-records');
    Route::get('reports/attendance-leaders', [ReportsController::class, 'attendanceLeaderRanking'])->name('admin.reports.attendance-leaders');
    Route::get('reports/attendance-churches', [ReportsController::class, 'attendanceChurchRanking'])->name('admin.reports.attendance-churches');
    Route::get('reports/giving-records', [ReportsController::class, 'givingRecords'])->name('admin.reports.giving-records');
    Route::get('reports/giving-leaders', [ReportsController::class, 'givingLeaderRanking'])->name('admin.reports.giving-leaders');
    Route::get('reports/absence-follow-up', [ReportsController::class, 'absenceFollowUp'])->name('admin.reports.absence-follow-up');
    Route::get('broadcasts/compose', [BroadcastComposerController::class, 'create'])->name('admin.broadcasts.create');
    Route::post('broadcasts', [BroadcastComposerController::class, 'store'])->name('admin.broadcasts.store');
    Route::post('broadcasts/{broadcast}/send', [BroadcastComposerController::class, 'send'])->name('admin.broadcasts.send');
    Route::middleware(EnsureSetupAdministrator::class)->group(function (): void {
        Route::get('setup/{resource}/bulk-create', [SetupBulkCreateController::class, 'create'])
            ->whereIn('resource', app(SetupBulkRegistry::class)->resources())
            ->name('admin.setup.bulk.create');
        Route::post('setup/{resource}/bulk-create', [SetupBulkCreateController::class, 'store'])
            ->whereIn('resource', app(SetupBulkRegistry::class)->resources())
            ->name('admin.setup.bulk.store');
        Route::get('church-settings', [ChurchSettingsController::class, 'edit'])->name('admin.church-settings.edit');
        Route::put('church-settings', [ChurchSettingsController::class, 'update'])->name('admin.church-settings.update');
        Route::crud('leadership-titles', 'LeadershipTitleCrudController');
        Route::crud('household-relationships', 'HouseholdRelationshipCrudController');

        foreach (['service-types', 'departments', 'department-roles', 'payment-methods', 'funds', 'giving-types', 'financial-accounts', 'expense-types', 'asset-types', 'asset-conditions', 'asset-statuses'] as $entity) {
            Route::crud($entity, 'ConfiguredCrudController');
        }
    });
    Route::middleware(EnsureAppAdministrator::class)->group(function (): void {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
        Route::get('error-logs', [ErrorLogController::class, 'index'])->name('admin.error-logs.index');
        Route::get('error-logs/{log}', [ErrorLogController::class, 'show'])->name('admin.error-logs.show');
        Route::get('error-logs/{log}/download', [ErrorLogController::class, 'download'])->name('admin.error-logs.download');
        Route::get('file-system', [FileSystemController::class, 'index'])->name('admin.file-system.index');
        Route::get('file-system/download', [FileSystemController::class, 'download'])->name('admin.file-system.download');
        Route::get('backups', [BackupController::class, 'index'])->name('admin.backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('admin.backups.store');
        Route::get('backups/download', [BackupController::class, 'download'])->name('admin.backups.download');
        Route::crud('audit-logs', 'ConfiguredCrudController');
    });
    foreach (['permissions', 'services', 'events', 'branch-departments', 'department-members', 'groups', 'group-members', 'income', 'expenses', 'expense-approvals', 'pledges', 'assets'] as $entity) {
        Route::crud($entity, 'ConfiguredCrudController');
    }
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
