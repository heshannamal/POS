<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermanentPlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isPermanentPlatformAdmin()
            && ! $user->isTemporaryAdminExpired() && $user->platformRole() === 'super_admin', 403);

        return $next($request);
    }
}
