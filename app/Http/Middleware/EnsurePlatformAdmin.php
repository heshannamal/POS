<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_platform_admin && ! $user->isTemporaryAdminExpired(), 403);
        $factor = app(\App\Services\PlatformTwoFactor::class);
        if ($factor->required($user) && ! $factor->verified($request)) {
            return redirect()->to($factor->destination($request));
        }
        if (app(\App\Services\PlatformAccess::class)->isCustom($user)) {
            // Each custom route is subsequently protected by its scoped
            // platform.role middleware; dashboard needs its own permission.
            if ($request->routeIs('platform.dashboard')) {
                abort_unless(app(\App\Services\PlatformAccess::class)->allowsDashboard($user, $request), 403);
            }
        }

        return $next($request);
    }
}
