<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class DeviceAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'tenant_slug'=>'required|string|max:190',
            'email'=>'required|email|max:191',
            'password'=>'required|string',
            'device_uuid'=>'required|uuid',
            'device_name'=>'required|string|max:120',
            'platform'=>'required|in:desktop,mobile',
            'app_version'=>'nullable|string|max:60',
        ]);
        $email=mb_strtolower(trim($data['email']));
        $key='pos-device-login:'.hash('sha256',$email.'|'.$request->ip());
        if(RateLimiter::tooManyAttempts($key,5)){
            return response()->json(['message'=>'Too many sign-in attempts. Try again later.'],429);
        }
        $user=User::where('email',$email)->first();
        $tenant=Tenant::where('slug',$data['tenant_slug'])->first();
        $valid=$user && $tenant && Hash::check($data['password'],$user->password)
            && !$user->is_platform_admin && !$user->isTemporaryAdminExpired()
            && $user->hasVerifiedEmail() && $user->force_password_reset_at===null
            && !((int)config('platform_security.password_expiry_days',0)>0 &&
                $user->password_changed_at && $user->password_changed_at->lt(
                    now()->subDays((int)config('platform_security.password_expiry_days'))))
            && $tenant->users()->whereKey($user->id)->wherePivot('status','active')->exists()
            && in_array($tenant->effective_status,['active','trial'],true);
        if(!$valid){
            RateLimiter::hit($key,900);
            throw ValidationException::withMessages(['credentials'=>'Credentials or business access are invalid.']);
        }
        RateLimiter::clear($key);

        $result=DB::transaction(function()use($user,$tenant,$data,$request){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $device=DB::table('pos_api_devices')->where('tenant_id',$tenant->id)
                ->where('user_id',$user->id)->where('device_uuid',$data['device_uuid'])
                ->lockForUpdate()->first();

            if(!$device) {
                $limit=max(1,(int) config('pos_api.max_devices_per_user_tenant',10));
                $active=DB::table('pos_api_devices')->where('tenant_id',$tenant->id)
                    ->where('user_id',$user->id)->where('is_active',true)->whereNull('revoked_at')->count();
                if($active >= $limit){
                    throw ValidationException::withMessages(['device_uuid'=>'Device limit reached. Revoke an existing device first.']);
                }
                $id=DB::table('pos_api_devices')->insertGetId([
                    'tenant_id'=>$tenant->id,'user_id'=>$user->id,'device_uuid'=>$data['device_uuid'],
                    'name'=>$data['device_name'],'platform'=>$data['platform'],
                    'app_version'=>$data['app_version']??null,'auth_session_version'=>(int)$user->auth_session_version,
                    'is_active'=>true,'last_seen_at'=>now(),'last_ip'=>$request->ip(),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            } else {
                // Revoked device identities cannot silently be resurrected on login.
                if(!$device->is_active || $device->revoked_at){
                    throw ValidationException::withMessages(['device_uuid'=>'Device was revoked. Register with a new device UUID.']);
                }
                if($device->platform!==$data['platform']){
                    throw ValidationException::withMessages(['device_uuid'=>'Device platform does not match.']);
                }
                $id=$device->id;
                DB::table('pos_api_devices')->where('id',$id)->update([
                    'name'=>$data['device_name'],'app_version'=>$data['app_version']??null,
                    'auth_session_version'=>(int)$user->auth_session_version,
                    'last_seen_at'=>now(),'last_ip'=>$request->ip(),'updated_at'=>now(),
                ]);
                $user->tokens()->where('name','pos-device:'.$id)->delete();
            }
            $days=max(1,min(30,(int) config('pos_api.token_expiry_days',14)));
            $token=$user->createToken('pos-device:'.$id,[
                'tenant:'.$tenant->id,'device:'.$id,
            ],now()->addDays($days));
            AuditLog::create([
                'tenant_id'=>$tenant->id,'actor_id'=>$user->id,
                'action'=>'api.device.login','subject_type'=>'pos_api_device','subject_id'=>$id,
                'metadata'=>['platform'=>$data['platform']],'ip_address'=>$request->ip(),
            ]);
            return ['token'=>$token->plainTextToken,
                'expires_at'=>$token->accessToken->expires_at->toIso8601String(),'device_id'=>$id];
        },3);
        return response()->json([
            'data'=>['access_token'=>$result['token'],'token_type'=>'Bearer',
                'expires_at'=>$result['expires_at'],'device_id'=>$result['device_id'],
                'tenant'=>['id'=>$tenant->id,'slug'=>$tenant->slug,'name'=>$tenant->name],
                'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email]],
        ],201);
    }

    public function logout(Request $request,Tenant $tenant)
    {
        AuditLog::create([
            'tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
            'action'=>'api.device.logout','subject_type'=>'pos_api_device',
            'subject_id'=>$request->attributes->get('pos_api_device')->id,
            'ip_address'=>$request->ip(),
        ]);
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message'=>'Device session signed out.']);
    }

    public function devices(Request $request,Tenant $tenant)
    {
        $rows=DB::table('pos_api_devices')->where('tenant_id',$tenant->id)
            ->where('user_id',$request->user()->id)->orderByDesc('id')
            ->get(['id','name','platform','app_version','is_active','last_seen_at','revoked_at','created_at']);
        return response()->json(['data'=>$rows]);
    }

    public function revoke(Request $request,Tenant $tenant,int $device)
    {
        DB::transaction(function()use($request,$tenant,$device){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $item=DB::table('pos_api_devices')->where('tenant_id',$tenant->id)
                ->where('user_id',$request->user()->id)->where('id',$device)->first();
            abort_unless($item,404);
            DB::table('pos_api_devices')->where('id',$device)->update([
                'is_active'=>false,'revoked_at'=>now(),'updated_at'=>now(),
            ]);
            $request->user()->tokens()->where('name','pos-device:'.$device)->delete();
            AuditLog::create([
                'tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'api.device.revoked','subject_type'=>'pos_api_device',
                'subject_id'=>$device,'ip_address'=>$request->ip(),
            ]);
        },3);
        return response()->json(['message'=>'Device revoked.']);
    }
}
