<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosDeviceManagementController extends Controller
{
    public function index(Request $request,Tenant $tenant)
    {
        $devices=DB::table('pos_api_devices as d')
            ->join('users as u','u.id','=','d.user_id')
            ->where('d.tenant_id',$tenant->id)
            ->select('d.id','d.user_id','d.name','d.platform','d.app_version',
                'd.is_active','d.revoked_at','d.last_seen_at','d.last_ip','d.created_at',
                'u.name as user_name','u.email as user_email')
            ->orderByDesc('d.id')->paginate(25);
        return view('business.pos.devices',compact('tenant','devices'));
    }

    public function revoke(Request $request,Tenant $tenant,int $device)
    {
        DB::transaction(function ()use($request,$tenant,$device){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $row=DB::table('pos_api_devices')->where('tenant_id',$tenant->id)
                ->where('id',$device)->lockForUpdate()->first();
            abort_unless($row,404);
            if(!$row->revoked_at){
                DB::table('pos_api_devices')->where('id',$device)->update([
                    'is_active'=>false,'revoked_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('personal_access_tokens')
                    ->where('tokenable_type',\App\Models\User::class)
                    ->where('tokenable_id',$row->user_id)
                    ->where('name','pos-device:'.$device)->delete();
                AuditLog::create([
                    'tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                    'action'=>'api.device.owner_revoked','subject_type'=>'pos_api_device',
                    'subject_id'=>$device,'metadata'=>['user_id'=>$row->user_id],
                    'ip_address'=>$request->ip(),
                ]);
            }
        },3);
        return back()->with('status','Device access revoked.');
    }
}
