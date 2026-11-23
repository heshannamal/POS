<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PosBranchAccess;
use App\Services\PosShiftAccounting;
use App\Services\PosShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function index(Request $request,Tenant $tenant,PosShiftAccounting $accounting)
    {
        $ids=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        $rows=DB::table('pos_shifts as s')
            ->join('pos_registers as r','r.id','=','s.register_id')
            ->where('s.tenant_id',$tenant->id)
            ->whereIn('r.branch_id',$ids)
            ->where('s.user_id',$request->user()->id)->whereNull('s.closed_at')
            ->select('s.id','s.register_id','r.branch_id','s.opening_cash_minor','s.opened_at')
            ->orderByDesc('s.id')->get()
            ->map(fn($shift)=>[
                'id'=>$shift->id,'register_id'=>$shift->register_id,
                'branch_id'=>$shift->branch_id,
                'opening_cash_minor'=>$shift->opening_cash_minor,
                'expected_cash_minor'=>$accounting->expected($shift->id,$shift->opening_cash_minor),
                'opened_at'=>$shift->opened_at,
            ]);
        return response()->json(['data'=>$rows]);
    }

    public function open(Request $request,Tenant $tenant,PosShiftService $service)
    {
        $id=$service->open($request,$tenant);
        return response()->json(['data'=>['shift_id'=>$id],'message'=>'Shift opened.'],201);
    }

    public function movement(Request $request,Tenant $tenant,int $shift,PosShiftService $service,PosShiftAccounting $accounting)
    {
        $service->movement($request,$tenant,$shift,$accounting);
        return response()->json(['message'=>'Cash movement recorded.'],201);
    }

    public function close(Request $request,Tenant $tenant,int $shift,PosShiftService $service,PosShiftAccounting $accounting)
    {
        $summary=$service->close($request,$tenant,$shift,$accounting);
        return response()->json(['data'=>$summary,'message'=>'Shift reconciled.']);
    }
}
