<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant && $request->user(), 403);
        abort_unless(app(TenantAuthorization::class)->allows($request->user(), $tenant, $permission), 403);

        return $next($request);
    }
}
