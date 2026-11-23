<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;
use App\Models\SecurityAlert;
use App\Services\CommunicationCenter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep indexed VARCHAR fields compatible with utf8mb4 and smaller MySQL index limits.
        Schema::defaultStringLength(191);

        // Notifications never decide the outcome of a payment, trial, or
        // support transaction. Process only after the original commit.
        AuditLog::created(function (AuditLog $audit): void {
            if (! in_array($audit->action, [
                'tenant.created','subscription.payment.submitted',
                'subscription.payment.approved','subscription.payment.rejected',
                'support.ticket.created','support.ticket.replied',
                'support.customer.replied','employee.invited',
            ], true)) return;

            DB::afterCommit(function () use ($audit): void {
                try {
                    app(CommunicationCenter::class)->eventFromAudit($audit);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });

        SecurityAlert::created(function (SecurityAlert $alert): void {
            DB::afterCommit(function () use ($alert): void {
                try {
                    app(CommunicationCenter::class)->securityAlert($alert);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });
    }
}
