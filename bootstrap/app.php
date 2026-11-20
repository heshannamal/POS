<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureAccountReady;
use App\Http\Middleware\EnsurePlatformRole;
use App\Http\Middleware\EnsurePermanentPlatformAdmin;
use App\Http\Middleware\EnsurePermanentBillingReviewer;
use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\EnsureTenantPermission;
use App\Http\Middleware\EnsureTenantFeature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account.ready' => EnsureAccountReady::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'platform.role' => EnsurePlatformRole::class,
            'platform.permanent' => EnsurePermanentPlatformAdmin::class,
            'platform.billing_review' => EnsurePermanentBillingReviewer::class,
            'tenant.member' => EnsureTenantMember::class,
            'tenant.permission' => EnsureTenantPermission::class,
            'tenant.feature' => EnsureTenantFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
