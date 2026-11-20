<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessDashboardController;
use App\Http\Controllers\Business\EmployeeController;
use App\Http\Controllers\Business\RoleController;
use App\Http\Controllers\Business\SubscriptionController as BusinessSubscriptionController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\ModuleController;
use App\Http\Controllers\Platform\AdminTeamController;
use App\Http\Controllers\Platform\SettingsController;
use App\Http\Controllers\Platform\BusinessOperationsController;
use App\Http\Controllers\Platform\SubscriptionRegistryController;
use App\Http\Controllers\Platform\ReportsController;
use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\AnnouncementController;
use App\Http\Controllers\Platform\SupportController as PlatformSupportController;
use App\Http\Controllers\Platform\UsersController;
use App\Http\Controllers\Platform\PaymentsController;
use App\Http\Controllers\PlatformUserInvitationController;
use App\Http\Controllers\Business\SupportController as BusinessSupportController;
use App\Http\Controllers\Platform\SubscriptionController as PlatformSubscriptionController;
use App\Http\Controllers\Platform\TrialManagementController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboard;
use App\Http\Controllers\Platform\TenantController as PlatformTenants;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/team/invite/{token}', [StaffInvitationController::class, 'show'])
    ->middleware('throttle:20,1')->name('staff.invites.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/account-invitation/{token}', [PlatformUserInvitationController::class, 'show'])
        ->middleware('throttle:20,1')->name('platform-invite.show');
    Route::post('/account-invitation/{token}', [PlatformUserInvitationController::class, 'accept'])
        ->middleware('throttle:6,1')->name('platform-invite.accept');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendLink'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])
        ->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::view('/email/verify', 'auth.verify-email')
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        $inviteToken = $request->session()->get('pending_staff_invite');
        return redirect()->to($inviteToken ? route('staff.invites.show', $inviteToken) : route('onboarding.create'))
            ->with('status', 'Email verified.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('onboarding.create');
        }

        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'Verification link sent.');
    })->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('/team/invite/{token}/accept', [StaffInvitationController::class, 'accept'])
        ->middleware('throttle:10,1')->name('staff.invites.accept');
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [OnboardingController::class, 'store'])
        ->middleware('throttle:6,1')->name('onboarding.store');

    Route::prefix('app/{tenant:slug}')->name('business.')
        ->middleware('tenant.member')->group(function (): void {
            Route::get('/dashboard', [BusinessDashboardController::class, 'index'])->name('dashboard');
            Route::get('/subscription', [BusinessSubscriptionController::class, 'index'])->name('subscription.index');
            Route::middleware('tenant.permission:settings.manage')->group(function (): void {
                Route::get('/support', [BusinessSupportController::class, 'index'])->name('support.index');
                Route::post('/support', [BusinessSupportController::class, 'store'])
                    ->middleware('throttle:10,1')->name('support.store');
                Route::get('/support/{ticket}', [BusinessSupportController::class, 'show'])->name('support.show');
                Route::post('/support/{ticket}/reply', [BusinessSupportController::class, 'reply'])
                    ->middleware('throttle:15,1')->name('support.reply');
            });
            Route::middleware('tenant.feature:staff.core')->group(function (): void {
                Route::get('/employees', [EmployeeController::class, 'index'])
                    ->middleware('tenant.permission:employees.view')->name('employees.index');
                Route::post('/employees/invite', [EmployeeController::class, 'invite'])
                    ->middleware('tenant.permission:roles.manage')->name('employees.invite');
                Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
                    ->middleware('tenant.permission:roles.manage')->name('employees.update');
                Route::get('/roles', [RoleController::class, 'index'])
                    ->middleware('tenant.permission:employees.view')->name('roles.index');
                Route::post('/roles', [RoleController::class, 'store'])
                    ->middleware('tenant.permission:roles.manage')->name('roles.store');
                Route::put('/roles/{role}', [RoleController::class, 'update'])
                    ->middleware('tenant.permission:roles.manage')->name('roles.update');
            });
        });

    Route::prefix('platform')->name('platform.')
        ->middleware('platform.admin')->group(function (): void {
            Route::get('/dashboard', [PlatformDashboard::class, 'index'])->name('dashboard');

            Route::get('/businesses/{tenant:slug}', [PlatformTenants::class, 'show'])
                ->middleware('platform.role:operations,billing,support')->name('tenants.show');

            Route::get('/users', [UsersController::class, 'index'])
                ->middleware('platform.role:operations,support')->name('users.index');
            Route::get('/users/{user}', [UsersController::class, 'show'])
                ->middleware('platform.role:operations,support')->name('users.show');
            Route::post('/users/invite', [UsersController::class, 'invite'])
                ->middleware(['platform.role:operations', 'throttle:8,1'])->name('users.invite');

            Route::middleware('platform.role:operations')->group(function (): void {
                Route::get('/businesses', [PlatformTenants::class, 'index'])->name('tenants.index');
                Route::get('/businesses/create', [PlatformTenants::class, 'create'])->name('tenants.create');
                Route::post('/businesses', [PlatformTenants::class, 'store'])->name('tenants.store');
                Route::put('/businesses/{tenant:slug}/profile', [BusinessOperationsController::class, 'profile'])
                    ->name('tenants.profile');
                Route::post('/businesses/{tenant:slug}/branches', [BusinessOperationsController::class, 'storeBranch'])
                    ->name('tenants.branches.store');
                Route::patch('/businesses/{tenant:slug}/branches/{branch}', [BusinessOperationsController::class, 'updateBranch'])
                    ->name('tenants.branches.update');
                Route::patch('/businesses/{tenant:slug}/branches/{branch}/default', [BusinessOperationsController::class, 'setDefaultBranch'])
                    ->name('tenants.branches.default');
                Route::patch('/businesses/{tenant:slug}/status', [PlatformTenants::class, 'status'])
                    ->name('tenants.status');
                Route::post('/businesses/{tenant:slug}/trial/extend', [PlatformTenants::class, 'extendTrial'])
                    ->name('tenants.trial.extend');
                Route::get('/trials', [TrialManagementController::class, 'index'])->name('trials.index');
                Route::put('/trials/settings', [TrialManagementController::class, 'settings'])
                    ->name('trials.settings');
            });

            Route::middleware('platform.role:billing')->group(function (): void {
                Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
                Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
                Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
                Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
                Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
                Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
                Route::get('/modules/create', [ModuleController::class, 'create'])->name('modules.create');
                Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
                Route::get('/modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
                Route::put('/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
                Route::get('/subscriptions', [SubscriptionRegistryController::class, 'index'])->name('subscriptions.index');
                Route::get('/payment-setup', [PaymentsController::class, 'index'])->name('payments.index');
                Route::post('/businesses/{tenant:slug}/plan', [PlatformSubscriptionController::class, 'activate'])
                    ->name('tenants.plan.activate');
                Route::put('/businesses/{tenant:slug}/modules', [PlatformSubscriptionController::class, 'grant'])
                    ->name('tenants.modules.grant');
                Route::delete('/businesses/{tenant:slug}/modules/{module}', [PlatformSubscriptionController::class, 'revoke'])
                    ->name('tenants.modules.revoke');
            });

            Route::middleware('platform.role:auditor,operations,billing')->group(function (): void {
                Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
            });

            Route::middleware('platform.role:auditor')->group(function (): void {
                Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
                Route::get('/audit/export', [AuditController::class, 'export'])->name('audit.export');
            });

            Route::middleware('platform.role:support,operations')->group(function (): void {
                Route::get('/support', [PlatformSupportController::class, 'index'])->name('support.index');
                Route::get('/support/{ticket}', [PlatformSupportController::class, 'show'])->name('support.show');
                Route::patch('/support/{ticket}', [PlatformSupportController::class, 'update'])->name('support.update');
                Route::post('/support/{ticket}/reply', [PlatformSupportController::class, 'reply'])->name('support.reply');
                Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
                Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
                Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
                Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
                Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
            });

            Route::middleware('platform.permanent')->group(function (): void {
                Route::get('/administrators', [AdminTeamController::class, 'index'])->name('admins.index');
                Route::post('/administrators', [AdminTeamController::class, 'store'])
                    ->middleware('throttle:5,1')->name('admins.store');
                Route::patch('/administrators/{administrator}', [AdminTeamController::class, 'update'])
                    ->middleware('throttle:5,1')->name('admins.update');
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
                Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
            });
        });

});
