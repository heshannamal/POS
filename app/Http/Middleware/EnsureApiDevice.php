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
        abort_unless($tenant->users()->whereKey($user->id)->wherePivot('status','active')->exists(),403);

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
        $request->attributes->set('pos_api_device',$device);
        return $next($request);
    }
}
