<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\FeatureAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantFeature
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);
        abort_unless(app(FeatureAccess::class)->allows($tenant, $module), 403);

        return $next($request);
    }
}
