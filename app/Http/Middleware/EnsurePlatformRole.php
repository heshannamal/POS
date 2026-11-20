<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_platform_admin && ! $user->isTemporaryAdminExpired(), 403);
        $role = $user->platformRole();
        abort_unless($role === 'super_admin' || in_array($role, $roles, true), 403);

        return $next($request);
    }
}
