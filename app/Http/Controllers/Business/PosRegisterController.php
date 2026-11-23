<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
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
        $branches = Branch::where('tenant_id',$tenant->id)->orderBy('name')->get();
        $registers = DB::table('pos_registers as r')
            ->join('branches as b','b.id','=','r.branch_id')
            ->where('r.tenant_id',$tenant->id)
            ->select('r.*','b.name as branch_name')->orderBy('r.id')->get();
        $openShifts = DB::table('pos_shifts as s')
            ->join('pos_registers as r','r.id','=','s.register_id')
            ->join('users as u','u.id','=','s.user_id')
            ->where('s.tenant_id',$tenant->id)->whereNull('s.closed_at')
            ->select('s.*','r.name as register_name','r.branch_id','u.name as cashier_name')
            ->orderBy('s.id')->get();
        $openShifts->each(function ($shift) use ($accounting) {
            $shift->expected = $accounting->expected($shift->id,$shift->opening_cash_minor);
        });
        $recent = DB::table('pos_shifts as s')
            ->join('pos_registers as r','r.id','=','s.register_id')
            ->where('s.tenant_id',$tenant->id)->whereNotNull('s.closed_at')
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
            $open=DB::table('pos_shifts')->where('register_id',$register)->whereNull('closed_at')->exists();
            if($open) throw ValidationException::withMessages(['register'=>'Close the open shift first.']);
            DB::table('pos_registers')->where('id',$register)->update(['is_active'=>!$row->is_active,'updated_at'=>now()]);
            $this->audit($tenant,$request,'pos.register.status_changed',$register);
        });
        return back()->with('status','Register status updated.');
    }

    public function open(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'register_id'=>['required','integer',Rule::exists('pos_registers','id')->where('tenant_id',$tenant->id)->where('is_active',true)],
            'opening_cash'=>'required|numeric|min:0|max:100000000',
        ]);
        $opening=(int)round((float)$data['opening_cash']*100);
        DB::transaction(function ()use($tenant,$request,$data,$opening) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $register=DB::table('pos_registers')->where('tenant_id',$tenant->id)
                ->where('id',$data['register_id'])->where('is_active',true)->lockForUpdate()->first();
            abort_unless($register,404);
            if(DB::table('pos_shifts')->where('register_id',$register->id)->whereNull('closed_at')->exists()) {
                throw ValidationException::withMessages(['register_id'=>'This register already has an open shift.']);
            }
            if(DB::table('pos_shifts')->where('tenant_id',$tenant->id)->where('user_id',$request->user()->id)
                ->whereNull('closed_at')->exists()) {
                throw ValidationException::withMessages(['register_id'=>'Close your existing cashier shift first.']);
            }
            $id=DB::table('pos_shifts')->insertGetId([
                'tenant_id'=>$tenant->id,'register_id'=>$register->id,'user_id'=>$request->user()->id,
                'opening_cash_minor'=>$opening,'opened_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($tenant,$request,'pos.shift.opened',$id,['register_id'=>$register->id,'opening_cash_minor'=>$opening]);
        });
        return back()->with('status','Cashier shift opened.');
    }

    public function movement(Request $request,Tenant $tenant,int $shift,PosShiftAccounting $accounting)
    {
        $data=$request->validate([
            'type'=>['required',Rule::in(['cash_in','cash_out'])],
            'amount'=>'required|numeric|gt:0|max:100000000',
            'reason'=>'required|string|max:500',
        ]);
        $amount=(int)round((float)$data['amount']*100);
        abort_if($amount<=0,422);
        DB::transaction(function ()use($tenant,$request,$shift,$data,$amount,$accounting){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $row=DB::table('pos_shifts')->where('tenant_id',$tenant->id)->where('id',$shift)
                ->whereNull('closed_at')->lockForUpdate()->first();
            abort_unless($row,404);
            abort_unless((int)$row->user_id===$request->user()->id || $request->user()->isOwnerOf($tenant),403);
            $delta=$data['type']==='cash_in'?$amount:-$amount;
            if ($accounting->expected($shift,$row->opening_cash_minor)+$delta<0) {
                throw ValidationException::withMessages(['amount'=>'Not enough cash in the register.']);
            }
            DB::table('pos_cash_movements')->insert([
                'tenant_id'=>$tenant->id,'shift_id'=>$shift,'actor_id'=>$request->user()->id,
                'amount_minor'=>$delta,'type'=>$data['type'],'note'=>$data['reason'],
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($tenant,$request,'pos.shift.cash_movement',$shift,['type'=>$data['type'],'amount_minor'=>$delta]);
        },3);
        return back()->with('status','Cash movement recorded.');
    }

    public function close(Request $request,Tenant $tenant,int $shift,PosShiftAccounting $accounting)
    {
        $data=$request->validate([
            'closing_cash'=>'required|numeric|min:0|max:100000000',
            'note'=>'nullable|string|max:3000',
        ]);
        $closing=(int)round((float)$data['closing_cash']*100);
        DB::transaction(function ()use($tenant,$request,$shift,$data,$closing,$accounting){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $row=DB::table('pos_shifts')->where('tenant_id',$tenant->id)->where('id',$shift)
                ->whereNull('closed_at')->lockForUpdate()->first();
            abort_unless($row,404);
            abort_unless((int)$row->user_id===$request->user()->id || $request->user()->isOwnerOf($tenant),403);
            $expected=$accounting->expected($shift,$row->opening_cash_minor);
            DB::table('pos_shifts')->where('id',$shift)->update([
                'closing_cash_minor'=>$closing,'expected_cash_minor'=>$expected,
                'cash_difference_minor'=>$closing-$expected,'closing_note'=>$data['note']??null,
                'closed_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($tenant,$request,'pos.shift.closed',$shift,[
                'expected_minor'=>$expected,'counted_minor'=>$closing,'difference_minor'=>$closing-$expected,
            ]);
        },3);
        return back()->with('status','Shift closed and reconciled.');
    }

    private function audit(Tenant $tenant,Request $request,string $action,int $id,array $metadata=[]): void
    {
        AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
            'action'=>$action,'subject_type'=>'pos_shift_or_register','subject_id'=>$id,
            'metadata'=>$metadata,'ip_address'=>$request->ip()]);
    }
}