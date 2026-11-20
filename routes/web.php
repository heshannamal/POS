<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessDashboardController;
use App\Http\Controllers\Business\EmployeeController;
use App\Http\Controllers\Business\RoleController;
use App\Http\Controllers\Business\SubscriptionController as BusinessSubscriptionController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\Platform\PlanController;
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
            Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
            Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
            Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
            Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
            Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
            Route::get('/trials', [TrialManagementController::class, 'index'])->name('trials.index');
            Route::put('/trials/settings', [TrialManagementController::class, 'settings'])->name('trials.settings');
            Route::get('/businesses', [PlatformTenants::class, 'index'])->name('tenants.index');
            Route::get('/businesses/create', [PlatformTenants::class, 'create'])->name('tenants.create');
            Route::post('/businesses', [PlatformTenants::class, 'store'])->name('tenants.store');
            Route::get('/businesses/{tenant:slug}', [PlatformTenants::class, 'show'])->name('tenants.show');
            Route::post('/businesses/{tenant:slug}/plan', [PlatformSubscriptionController::class, 'activate'])
                ->name('tenants.plan.activate');
            Route::put('/businesses/{tenant:slug}/modules', [PlatformSubscriptionController::class, 'grant'])
                ->name('tenants.modules.grant');
            Route::delete('/businesses/{tenant:slug}/modules/{module}', [PlatformSubscriptionController::class, 'revoke'])
                ->name('tenants.modules.revoke');
            Route::patch('/businesses/{tenant:slug}/status', [PlatformTenants::class, 'status'])
                ->name('tenants.status');
            Route::post('/businesses/{tenant:slug}/trial/extend', [PlatformTenants::class, 'extendTrial'])
                ->name('tenants.trial.extend');
        });
});
