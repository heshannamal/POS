<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessDashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboard;
use App\Http\Controllers\Platform\TenantController as PlatformTenants;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

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
        return redirect()->route('onboarding.create')->with('status', 'Email verified.');
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
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [OnboardingController::class, 'store'])
        ->middleware('throttle:6,1')->name('onboarding.store');

    Route::get('/app/{tenant:slug}/dashboard', [BusinessDashboardController::class, 'index'])
        ->middleware('tenant.member')->name('business.dashboard');

    Route::prefix('platform')->name('platform.')
        ->middleware('platform.admin')->group(function (): void {
            Route::get('/dashboard', [PlatformDashboard::class, 'index'])->name('dashboard');
            Route::get('/businesses', [PlatformTenants::class, 'index'])->name('tenants.index');
            Route::get('/businesses/create', [PlatformTenants::class, 'create'])->name('tenants.create');
            Route::post('/businesses', [PlatformTenants::class, 'store'])->name('tenants.store');
            Route::get('/businesses/{tenant:slug}', [PlatformTenants::class, 'show'])->name('tenants.show');
            Route::patch('/businesses/{tenant:slug}/status', [PlatformTenants::class, 'status'])
                ->name('tenants.status');
            Route::post('/businesses/{tenant:slug}/trial/extend', [PlatformTenants::class, 'extendTrial'])
                ->name('tenants.trial.extend');
        });
});
