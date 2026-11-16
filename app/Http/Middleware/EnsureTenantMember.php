<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        abort_unless($tenant instanceof Tenant, 404);
        abort_unless(
            $request->user()?->tenants()
                ->whereKey($tenant->id)
                ->wherePivot('status', 'active')
                ->exists(),
            403
        );

        return $next($request);
    }
}
