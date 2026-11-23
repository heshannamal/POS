<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\PosBranchAccess;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\PosShiftAccounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosRegisterController extends Controller
{
    public function index(Request $request, Tenant $tenant, PosShiftAccounting $accounting)
    {
        $allowed=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        $branches = Branch::where('tenant_id',$tenant->id)->whereIn('id',$allowed)->orderBy('name')->get();
        $registers = DB::table('pos_registers as r')
            ->join('branches as b','b.id','=','r.branch_id')
            ->where('r.tenant_id',$tenant->id)->whereIn('r.branch_id',$allowed)
            ->select('r.*','b.name as branch_name')->orderBy('r.id')->get();
        $openShifts = DB::table('pos_shifts as s')
            ->join('pos_registers as r','r.id','=','s.register_id')
            ->join('users as u','u.id','=','s.user_id')
            ->where('s.tenant_id',$tenant->id)->whereIn('r.branch_id',$allowed)->whereNull('s.closed_at')
            ->select('s.*','r.name as register_name','r.branch_id','u.name as cashier_name')
            ->orderBy('s.id')->get();
        $openShifts->each(function ($shift) use ($accounting) {
            $shift->expected = $accounting->expected($shift->id,$shift->opening_cash_minor);
        });
        $recent = DB::table('pos_shifts as s')
            ->join('pos_registers as r','r.id','=','s.register_id')
            ->where('s.tenant_id',$tenant->id)->whereIn('r.branch_id',$allowed)->whereNotNull('s.closed_at')
            ->select('s.*','r.name as register_name')->orderByDesc('s.id')->limit(30)->get();
        return view('business.pos.registers',compact('tenant','branches','registers','openShifts','recent'));
    }

    public function create(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'name'=>'required|string|max:120',
        ]);
        DB::transaction(function () use ($tenant,$request,$data) {
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
            $id=DB::table('pos_registers')->insertGetId([
                'tenant_id'=>$tenant->id,'branch_id'=>$data['branch_id'],'name'=>$data['name'],
                'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($tenant,$request,'pos.register.created',$id);
        });
        return back()->with('status','Cash register created.');
    }

    public function toggle(Request $request,Tenant $tenant,int $register)
    {
        DB::transaction(function () use ($tenant,$request,$register) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $row=DB::table('pos_registers')->where('tenant_id',$tenant->id)->where('id',$register)->lockForUpdate()->first();
            abort_unless($row,404);
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$row->branch_id);
            $open=DB::table('pos_shifts')->where('register_id',$register)->whereNull('closed_at')->exists();
            if($open) throw ValidationException::withMessages(['register'=>'Close the open shift first.']);
            DB::table('pos_registers')->where('id',$register)->update(['is_active'=>!$row->is_active,'updated_at'=>now()]);
            $this->audit($tenant,$request,'pos.register.status_changed',$register);
        });
        return back()->with('status','Register status updated.');
    }

    public function open(Request $request,Tenant $tenant,\App\Services\PosShiftService $service)
    {
        $service->open($request,$tenant);
        return back()->with('status','Cashier shift opened.');
    }

    public function movement(Request $request,Tenant $tenant,int $shift,PosShiftAccounting $accounting,\App\Services\PosShiftService $service)
    {
        $service->movement($request,$tenant,$shift,$accounting);
        return back()->with('status','Cash movement recorded.');
    }

    public function close(Request $request,Tenant $tenant,int $shift,PosShiftAccounting $accounting,\App\Services\PosShiftService $service)
    {
        $service->close($request,$tenant,$shift,$accounting);
        return back()->with('status','Shift closed and reconciled.');
    }

    private function audit(Tenant $tenant,Request $request,string $action,int $id,array $metadata=[]): void
    {
        AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
            'action'=>$action,'subject_type'=>'pos_shift_or_register','subject_id'=>$id,
            'metadata'=>$metadata,'ip_address'=>$request->ip()]);
    }
}