<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Business\PosCatalogController;
use App\Http\Controllers\Business\PosInventoryController;
use App\Http\Controllers\Business\PosSalesController;
use App\Http\Controllers\Business\PosRegisterController;
use App\Http\Controllers\Business\PosReturnController;
use App\Http\Controllers\Business\PosReportsController;

use App\Http\Controllers\Security\TwoFactorController;
use App\Http\Controllers\Security\SessionController;
use App\Http\Controllers\Platform\SecurityDashboardController;
use App\Http\Controllers\Platform\AdminSessionsController;
use App\Http\Controllers\Platform\PlatformRoleController;
use App\Http\Controllers\BusinessDashboardController;
use App\Http\Controllers\Business\EmployeeController;
use App\Http\Controllers\Business\RoleController;
use App\Http\Controllers\Business\SubscriptionController as BusinessSubscriptionController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\ModuleController;
use App\Http\Controllers\Platform\AdminTeamController;
use App\Http\Controllers\Platform\SettingsController;
use App\Http\Controllers\Platform\CommunicationController;
use App\Http\Controllers\NotificationInboxController;
use App\Http\Controllers\Platform\BusinessOperationsController;
use App\Http\Controllers\Platform\TenantNotesController;
use App\Http\Controllers\Platform\TenantOnboardingController;
use App\Http\Controllers\Platform\TenantLifecycleController;
use App\Http\Controllers\Platform\TenantArchiveController;
use App\Http\Controllers\Platform\TenantExportController;
use App\Http\Controllers\TenantLogoController;
use App\Http\Controllers\Platform\SubscriptionRegistryController;
use App\Http\Controllers\Platform\ReportsController;
use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\AnnouncementController;
use App\Http\Controllers\Platform\SupportController as PlatformSupportController;
use App\Http\Controllers\Platform\UsersController;
use App\Http\Controllers\Platform\GlobalStoreController;
use App\Http\Controllers\Platform\StoreDeviceController;
use App\Http\Controllers\Platform\GlobalTenantUserController;
use App\Http\Controllers\Platform\PaymentsController;
use App\Http\Controllers\Platform\PaymentReviewController;
use App\Http\Controllers\Platform\BillingInvoiceController;
use App\Http\Controllers\Platform\BillingCouponController;
use App\Http\Controllers\Platform\BillingOperationsController;
use App\Http\Controllers\Business\BillingController as BusinessBillingController;
use App\Http\Controllers\Platform\PaymentSettingsController;
use App\Http\Controllers\Business\SubscriptionPaymentController as BusinessPaymentController;
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

Route::middleware(['auth', 'verified'])->prefix('security')->name('security.')->group(function (): void {
    Route::get('/setup', fn () => redirect()->route('security.challenge'))->name('setup');
    Route::get('/challenge', [TwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('/challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1')->name('verify');
    Route::post('/resend', [TwoFactorController::class, 'resend'])->middleware('throttle:3,1')->name('resend');
});

Route::middleware(['auth', 'verified', 'account.ready'])->prefix('security')->name('security.')->group(function (): void {
    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions');
    Route::delete('/sessions/{session}', [SessionController::class, 'revoke'])
        ->middleware('throttle:5,1')->name('sessions.revoke');
});

// Signing out must work even while a 2FA challenge or forced reset is pending.
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'account.ready'])->group(function (): void {

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

Route::middleware(['auth', 'verified', 'account.ready'])->group(function (): void {
    Route::post('/team/invite/{token}/accept', [StaffInvitationController::class, 'accept'])
        ->middleware('throttle:10,1')->name('staff.invites.accept');
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [OnboardingController::class, 'store'])
        ->middleware('throttle:6,1')->name('onboarding.store');

    Route::prefix('app/{tenant:slug}')->name('business.')
        ->middleware('tenant.member')->group(function (): void {
            Route::get('/dashboard', [BusinessDashboardController::class, 'index'])->name('dashboard');

            Route::middleware('tenant.permission:pos.inventory.manage')->group(function (): void {
                Route::get('/products', [PosCatalogController::class, 'index'])->name('pos.products');
                Route::post('/products/categories', [PosCatalogController::class, 'category'])->middleware('pos.operational')->name('pos.categories.store');
                Route::post('/products', [PosCatalogController::class, 'store'])->middleware('pos.operational')->name('pos.products.store');
                Route::put('/products/{product}', [PosCatalogController::class, 'update'])->middleware('pos.operational')->name('pos.products.update');
                Route::delete('/products/{product}', [PosCatalogController::class, 'archive'])->middleware('pos.operational')->name('pos.products.archive');
                Route::get('/inventory', [PosInventoryController::class, 'index'])->name('pos.inventory');
                Route::post('/inventory/adjust', [PosInventoryController::class, 'adjust'])->middleware('pos.operational')->name('pos.inventory.adjust');
                Route::post('/inventory/transfer', [PosInventoryController::class, 'transfer'])->middleware('pos.operational')->name('pos.inventory.transfer');
            });
            Route::middleware('tenant.permission:pos.sales')->group(function (): void {
                Route::get('/pos', [PosSalesController::class, 'terminal'])->name('pos.terminal');
                Route::post('/pos/checkout', [PosSalesController::class, 'checkout'])
                    ->middleware('throttle:30,1')->middleware('pos.operational')->name('pos.checkout');
                Route::get('/sales', [PosSalesController::class, 'history'])->name('pos.sales');
                Route::get('/sales/{sale}/receipt', [PosSalesController::class, 'receipt'])->name('pos.receipt');
            });
            Route::middleware('tenant.permission:pos.register.manage')->group(function (): void {
                Route::post('/registers', [PosRegisterController::class, 'create'])->middleware('pos.operational')->name('pos.registers.create');
                Route::patch('/registers/{register}/status', [PosRegisterController::class, 'toggle'])->middleware('pos.operational')->name('pos.registers.toggle');
            });
            Route::middleware('tenant.permission:pos.shifts.operate')->group(function (): void {
                Route::get('/registers', [PosRegisterController::class, 'index'])->name('pos.registers');
                Route::post('/registers/shifts', [PosRegisterController::class, 'open'])->middleware('pos.operational')->name('pos.shifts.open');
                Route::post('/registers/shifts/{shift}/movements', [PosRegisterController::class, 'movement'])->middleware('pos.operational')->name('pos.shifts.movement');
                Route::post('/registers/shifts/{shift}/close', [PosRegisterController::class, 'close'])->name('pos.shifts.close');
            });
            Route::middleware('tenant.permission:pos.returns.manage')->group(function (): void {
                Route::get('/returns', [PosReturnController::class, 'index'])->name('pos.returns');
                Route::get('/sales/{sale}/return', [PosReturnController::class, 'create'])->name('pos.returns.create');
                Route::post('/sales/{sale}/return', [PosReturnController::class, 'store'])
                    ->middleware('throttle:20,1')->middleware('pos.operational')->name('pos.returns.store');
            });
            Route::middleware('tenant.permission:pos.reports.view')->group(function (): void {
                Route::get('/pos-reports', [PosReportsController::class, 'index'])->name('pos.reports');
                Route::get('/pos-reports/csv', [PosReportsController::class, 'csv'])->name('pos.reports.csv');
            });
            Route::get('/subscription', [BusinessSubscriptionController::class, 'index'])->name('subscription.index');
            Route::get('/billing', [BusinessBillingController::class, 'index'])
                ->middleware('tenant.permission:settings.manage')->name('billing.index');
            Route::get('/billing/invoices/{invoice}', [BusinessBillingController::class, 'show'])
                ->middleware('tenant.permission:settings.manage')->name('billing.show');
            Route::get('/billing/invoices/{invoice}/pdf', [BusinessBillingController::class, 'pdf'])
                ->middleware('tenant.permission:settings.manage')->name('billing.pdf');
            Route::get('/billing/credit-notes/{refund}/pdf', [BusinessBillingController::class, 'creditNote'])
                ->middleware('tenant.permission:settings.manage')->name('billing.credit-note');
            Route::put('/billing/renewal', [BusinessBillingController::class, 'renewal'])
                ->middleware(['tenant.permission:settings.manage','throttle:5,1'])->name('billing.renewal');
            Route::get('/logo', [TenantLogoController::class, 'show'])->name('logo.show');
            Route::get('/notifications', [NotificationInboxController::class, 'business'])
                ->name('notifications.index');
            Route::post('/notifications/{notification}/read', [NotificationInboxController::class, 'markBusiness'])
                ->name('notifications.read');
            Route::post('/notifications/read-all', [NotificationInboxController::class, 'markAllBusiness'])
                ->name('notifications.read-all');
            Route::put('/notifications/preferences', [NotificationInboxController::class, 'preferencesBusiness'])
                ->name('notifications.preferences');
            Route::get('/subscription/payment-quote', [BusinessPaymentController::class, 'quote'])
                ->middleware(['tenant.permission:settings.manage','throttle:30,1'])->name('subscription.payments.quote');
            Route::post('/subscription/payments', [BusinessPaymentController::class, 'store'])
                ->middleware(['tenant.permission:settings.manage', 'throttle:5,1'])->name('subscription.payments.store');
            Route::get('/subscription/payments/{payment}/evidence', [BusinessPaymentController::class, 'receipt'])
                ->middleware('tenant.permission:settings.manage')->name('subscription.payments.receipt');
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
            Route::get('/notifications', [NotificationInboxController::class, 'platform'])->name('notifications.index');
            Route::post('/notifications/read-all', [NotificationInboxController::class, 'markAllPlatform'])->name('notifications.read-all');
            Route::post('/notifications/{notification}/read', [NotificationInboxController::class, 'markPlatform'])->name('notifications.read');
            Route::put('/notifications/preferences', [NotificationInboxController::class, 'preferencesPlatform'])->name('notifications.preferences');
            Route::get('/security', [SecurityDashboardController::class, 'index'])
                ->middleware('platform.role:auditor')->name('security.index');

            Route::get('/businesses/archived', [TenantArchiveController::class, 'index'])
                ->middleware('platform.permanent')->name('tenants.archived');
            Route::post('/businesses/archived/{tenantId}/restore', [TenantArchiveController::class, 'restore'])
                ->middleware(['platform.permanent', 'throttle:5,1'])->name('tenants.restore');
            Route::post('/businesses/{tenant:slug}/archive', [TenantArchiveController::class, 'archive'])
                ->middleware(['platform.permanent', 'throttle:5,1'])->name('tenants.archive');
            Route::post('/businesses/{tenant:slug}/export', [TenantExportController::class, 'download'])
                ->middleware(['platform.permanent', 'throttle:5,1'])->name('tenants.export');
            Route::get('/businesses/{tenant:slug}/logo', [TenantLogoController::class, 'show'])
                ->middleware('platform.role:operations,billing,support')->name('tenants.logo');

            Route::get('/businesses/{tenant:slug}', [PlatformTenants::class, 'show'])
                ->middleware('platform.role:operations,billing,support')->name('tenants.show');

            Route::get('/users', [UsersController::class, 'index'])
                ->middleware('platform.role:operations,support')->name('users.index');
            Route::get('/users/{user}', [UsersController::class, 'show'])
                ->middleware('platform.role:operations,support')->name('users.show');
            Route::post('/users/invite', [UsersController::class, 'invite'])
                ->middleware(['platform.role:operations', 'throttle:8,1'])->name('users.invite');

            Route::middleware('platform.role:operations,support')->group(function (): void {
                Route::get('/stores', [GlobalStoreController::class, 'index'])->name('stores.index');
                Route::get('/stores/create', [GlobalStoreController::class, 'create'])
                    ->middleware('platform.role:operations')->name('stores.create');
                Route::get('/stores/{store}', [GlobalStoreController::class, 'show'])->name('stores.show');
                Route::get('/tenant-users', [GlobalTenantUserController::class, 'index'])->name('tenant-users.index');
                Route::get('/tenant-users/{tenant:slug}/{user}', [GlobalTenantUserController::class, 'show'])->name('tenant-users.show');
            });
            Route::middleware('platform.role:operations')->group(function (): void {
                Route::post('/stores', [GlobalStoreController::class, 'store'])->name('stores.store');
                Route::patch('/stores/{store}', [GlobalStoreController::class, 'update'])->name('stores.update');
                Route::put('/stores/{store}/settings', [GlobalStoreController::class, 'settings'])->name('stores.settings');
                Route::post('/stores/{store}/staff', [GlobalStoreController::class, 'assign'])->name('stores.staff.assign');
                Route::delete('/stores/{store}/staff/{user}', [GlobalStoreController::class, 'unassign'])->name('stores.staff.unassign');
                Route::post('/stores/{store}/devices', [StoreDeviceController::class, 'store'])->name('stores.devices.store');
                Route::patch('/stores/{store}/devices/{device}', [StoreDeviceController::class, 'update'])->name('stores.devices.update');
                Route::patch('/tenant-users/{tenant:slug}/{user}', [GlobalTenantUserController::class, 'update'])->name('tenant-users.update');
                Route::get('/businesses', [PlatformTenants::class, 'index'])->name('tenants.index');
                Route::get('/businesses/create', [PlatformTenants::class, 'create'])->name('tenants.create');
                Route::post('/businesses', [PlatformTenants::class, 'store'])->name('tenants.store');
                Route::post('/businesses/{tenant:slug}/logo', [BusinessOperationsController::class, 'uploadLogo'])
                    ->name('tenants.logo.upload');
                Route::delete('/businesses/{tenant:slug}/logo', [BusinessOperationsController::class, 'deleteLogo'])
                    ->name('tenants.logo.delete');
                Route::post('/businesses/{tenant:slug}/notes', [TenantNotesController::class, 'store'])
                    ->name('tenants.notes.store');
                Route::delete('/businesses/{tenant:slug}/notes/{note}', [TenantNotesController::class, 'destroy'])
                    ->name('tenants.notes.destroy');
                Route::patch('/businesses/{tenant:slug}/onboarding', [TenantOnboardingController::class, 'update'])
                    ->name('tenants.onboarding.update');
                Route::patch('/businesses/{tenant:slug}/lifecycle', [TenantLifecycleController::class, 'update'])
                    ->name('tenants.lifecycle.update');
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
                Route::get('/trials/create', [TrialManagementController::class, 'create'])->name('trials.create');
                Route::post('/trials', [TrialManagementController::class, 'store'])
                    ->middleware('throttle:8,1')->name('trials.store');
                Route::patch('/trials/{tenant:slug}/active', [TrialManagementController::class, 'setActive'])
                    ->name('trials.active');
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
                Route::get('/billing/invoices', [BillingInvoiceController::class, 'index'])->name('billing.invoices.index');
                Route::get('/billing/invoices/{invoice}', [BillingInvoiceController::class, 'show'])->name('billing.invoices.show');
                Route::get('/billing/invoices/{invoice}/pdf', [BillingInvoiceController::class, 'pdf'])->name('billing.invoices.pdf');
                Route::get('/billing/coupons', [BillingCouponController::class, 'index'])->name('billing.coupons.index');
                Route::get('/billing/settings', [BillingOperationsController::class, 'settings'])->name('billing.settings.index');
                Route::get('/billing/reports', [BillingOperationsController::class, 'report'])->name('billing.reports.index');
                Route::get('/billing/reports/export', [BillingOperationsController::class, 'export'])->name('billing.reports.export');
                Route::get('/billing/credit-notes/{refund}/pdf', [BillingInvoiceController::class, 'creditNote'])
                    ->name('billing.credit-note');
                Route::middleware('platform.billing_review')->group(function (): void {
                    Route::post('/billing/invoices/{invoice}/refund', [BillingInvoiceController::class, 'refund'])
                        ->middleware('throttle:5,1')->name('billing.invoices.refund');
                    Route::post('/billing/invoices/{invoice}/resend', [BillingInvoiceController::class, 'resend'])
                        ->middleware('throttle:5,1')->name('billing.invoices.resend');
                    Route::post('/billing/coupons', [BillingCouponController::class, 'store'])
                        ->name('billing.coupons.store');
                    Route::patch('/billing/coupons/{coupon}', [BillingCouponController::class, 'update'])
                        ->name('billing.coupons.update');
                    Route::put('/billing/settings', [BillingOperationsController::class, 'updateSettings'])
                        ->name('billing.settings.update');
                    Route::post('/billing/subscriptions/{tenant:slug}/extend', [BillingOperationsController::class, 'extend'])
                        ->name('billing.subscriptions.extend');
                });
                Route::get('/payment-requests', [PaymentReviewController::class, 'index'])->name('payments.review.index');
                Route::get('/payment-requests/{payment}', [PaymentReviewController::class, 'show'])->name('payments.review.show');
                Route::get('/payment-requests/{payment}/evidence', [PaymentReviewController::class, 'receipt'])->name('payments.review.receipt');
                Route::post('/payment-requests/{payment}/approve', [PaymentReviewController::class, 'approve'])
                    ->middleware(['platform.billing_review', 'throttle:10,1'])->name('payments.review.approve');
                Route::post('/payment-requests/{payment}/reject', [PaymentReviewController::class, 'reject'])
                    ->middleware(['platform.billing_review', 'throttle:10,1'])->name('payments.review.reject');
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
                Route::post('/tenant-users/{tenant:slug}/{user}/reset-password', [GlobalTenantUserController::class, 'forceReset'])
                    ->middleware('throttle:5,1')->name('tenant-users.force-reset');
                Route::post('/tenant-users/{tenant:slug}/{user}/revoke-sessions', [GlobalTenantUserController::class, 'revokeSessions'])
                    ->middleware('throttle:5,1')->name('tenant-users.revoke-sessions');
                Route::get('/security/sessions', [AdminSessionsController::class, 'index'])->name('security.sessions.index');
                Route::delete('/security/sessions/{session}', [AdminSessionsController::class, 'revoke'])
                    ->middleware('throttle:5,1')->name('security.sessions.revoke');
                Route::get('/security/roles', [PlatformRoleController::class, 'index'])->name('security.roles.index');
                Route::post('/security/roles', [PlatformRoleController::class, 'store'])->middleware('throttle:5,1')->name('security.roles.store');
                Route::put('/security/roles/{role}', [PlatformRoleController::class, 'update'])->middleware('throttle:5,1')->name('security.roles.update');
                Route::post('/security/alerts/{alert}/resolve', [SecurityDashboardController::class, 'resolve'])
                    ->name('security.alerts.resolve');
                Route::get('/administrators', [AdminTeamController::class, 'index'])->name('admins.index');
                Route::post('/administrators', [AdminTeamController::class, 'store'])
                    ->middleware('throttle:5,1')->name('admins.store');
                Route::patch('/administrators/{administrator}', [AdminTeamController::class, 'update'])
                    ->middleware('throttle:5,1')->name('admins.update');
                Route::get('/communications/templates', [CommunicationController::class, 'templates'])->name('communications.templates.index');
                Route::get('/communications/templates/create', [CommunicationController::class, 'create'])->name('communications.templates.create');
                Route::post('/communications/templates', [CommunicationController::class, 'store'])->name('communications.templates.store');
                Route::get('/communications/templates/{template}/edit', [CommunicationController::class, 'edit'])->name('communications.templates.edit');
                Route::put('/communications/templates/{template}', [CommunicationController::class, 'update'])->name('communications.templates.update');
                Route::post('/communications/templates/preview', [CommunicationController::class, 'preview'])
                    ->name('communications.templates.preview');
                Route::get('/communications/recipients', [CommunicationController::class, 'rules'])->name('communications.rules.index');
                Route::put('/communications/recipients/{rule}', [CommunicationController::class, 'updateRule'])
                    ->name('communications.rules.update');
                Route::get('/communications/settings', [CommunicationController::class, 'settings'])->name('communications.settings.index');
                Route::put('/communications/settings', [CommunicationController::class, 'updateSettings'])
                    ->name('communications.settings.update');
                Route::post('/communications/test', [CommunicationController::class, 'test'])
                    ->middleware('throttle:5,1')->name('communications.test');
                Route::get('/communications/deliveries', [CommunicationController::class, 'deliveries'])->name('communications.deliveries.index');
                Route::post('/communications/deliveries/{delivery}/retry', [CommunicationController::class, 'retry'])
                    ->middleware('throttle:5,1')->name('communications.deliveries.retry');
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
                Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
                Route::put('/payment-setup/methods/{method}', [PaymentSettingsController::class, 'update'])
                    ->name('payments.methods.update');
            });
        });

});
