<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePurchasingView
{
    public function handle(Request $request, Closure $next):Response
    {
        $tenant=$request->route('tenant');
        abort_unless($tenant instanceof Tenant,404);
        $auth=app(TenantAuthorization::class);
        $allowed=false;
        foreach(['pos.purchases.manage','pos.receiving.manage','pos.payables.manage'] as $permission) {
            if($auth->allows($request->user(),$tenant,$permission)){
                $allowed=true;break;
            }
        }
        abort_unless($allowed,403);
        return $next($request);
    }
}
