<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermanentBillingReviewer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_platform_admin
            && ! $user->isTemporaryAdminExpired()
            && $user->temporary_admin_expires_at === null
            && in_array($user->platformRole(), ['super_admin', 'billing'], true), 403);

        return $next($request);
    }
}
