<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantAuthorization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiDevice
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        // Sanctum can also authenticate a first-party web session. Desktop/mobile
        // API endpoints intentionally require an independently revocable Bearer token.
        $token = $request->user()?->currentAccessToken();
        abort_unless($request->bearerToken() && $token instanceof PersonalAccessToken, 401, 'Bearer token required.');
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);

        $user=$request->user();
        abort_if($user->is_platform_admin,403,'Platform administrator accounts cannot use business-device APIs.');
        abort_unless($user->hasVerifiedEmail() && $user->force_password_reset_at === null, 403);
        $maxAge=(int) config('platform_security.password_expiry_days',0);
        abort_if($maxAge>0 && $user->password_changed_at &&
            $user->password_changed_at->lt(now()->subDays($maxAge)),403,'Password expired. Reset it using the web portal.');
        abort_unless($tenant->users()->whereKey($user->id)->wherePivot('status','active')->exists(),403);
        // Existing shifts may be reconciled during suspension; no new sales
        // or cash movements are permitted because their routes require pos.operational.
        $closingShift=$request->routeIs('api.v1.tenants.shifts.close');
        abort_unless($closingShift || in_array($tenant->effective_status,['active','trial'],true),403,
            'The business is not active for device access.');
        abort_if($user->auth_revoked_at && $token->created_at->lessThanOrEqualTo($user->auth_revoked_at),
            401,'Account sessions were revoked.');
        abort_if($user->password_changed_at && $token->created_at->lessThan($user->password_changed_at),
            401,'Sign in again after changing your password.');

        $device = DB::table('pos_api_devices')
            ->where('tenant_id',$tenant->id)->where('user_id',$user->id)
            ->where('is_active',true)->whereNull('revoked_at')->where('id', (int) str_replace('pos-device:', '', $token->name))
            ->first();
        abort_unless($device &&
            $token->name === 'pos-device:'.$device->id &&
            $token->can('tenant:'.$tenant->id) &&
            $token->can('device:'.$device->id) &&
            (int)$device->auth_session_version===(int)$user->auth_session_version,401,'Device token is revoked or invalid.');

        if ($permission !== null) {
            abort_unless(app(TenantAuthorization::class)->allows($user,$tenant,$permission),403,'Missing permission.');
        }
        if(!$device->last_seen_at || \Illuminate\Support\Carbon::parse($device->last_seen_at)->lt(now()->subMinutes(5))) {
            DB::table('pos_api_devices')->where('id',$device->id)->update([
                'last_seen_at'=>now(),'last_ip'=>$request->ip(),
            ]);
        }
        $request->attributes->set('pos_api_device',$device);
        return $next($request);
    }
}
