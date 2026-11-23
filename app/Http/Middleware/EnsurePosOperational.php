<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosOperational
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant=$request->route('tenant');
        abort_unless($tenant instanceof Tenant,404);
        abort_unless(in_array($tenant->effective_status,['active','trial'],true),403,
            'This business is not currently active for sales.');

        $subscription=$tenant->subscription;
        abort_unless($subscription &&
            in_array($subscription->status,['active','trialing'],true) &&
            (! $subscription->ends_at || $subscription->ends_at->isFuture()),403,
            'An active subscription or trial is required for POS transactions.');

        return $next($request);
    }
}
