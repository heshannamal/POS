<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosShiftService
{
    public function open(Request $request,Tenant $tenant): int
    {
        $data=$request->validate([
            'register_id'=>['required','integer',Rule::exists('pos_registers','id')->where('tenant_id',$tenant->id)->where('is_active',true)],
            'opening_cash'=>'required|numeric|min:0|max:100000000',
        ]);
        $opening=(int)round((float)$data['opening_cash']*100);
        return DB::transaction(function ()use($tenant,$request,$data,$opening) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $register=DB::table('pos_registers')->where('tenant_id',$tenant->id)
                ->where('id',$data['register_id'])->where('is_active',true)->lockForUpdate()->first();
            abort_unless($register,404);
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$register->branch_id);
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
            return $id;
        });
    }

    public function movement(Request $request,Tenant $tenant,int $shift,PosShiftAccounting $accounting): void
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
            $registerBranch=(int)DB::table('pos_registers')->where('tenant_id',$tenant->id)->where('id',$row->register_id)->value('branch_id');
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,$registerBranch);
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
    }

    public function close(Request $request,Tenant $tenant,int $shift,PosShiftAccounting $accounting): array
    {
        $data=$request->validate([
            'closing_cash'=>'required|numeric|min:0|max:100000000',
            'note'=>'nullable|string|max:3000',
        ]);
        $closing=(int)round((float)$data['closing_cash']*100);
        return DB::transaction(function ()use($tenant,$request,$shift,$data,$closing,$accounting){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $row=DB::table('pos_shifts')->where('tenant_id',$tenant->id)->where('id',$shift)
                ->whereNull('closed_at')->lockForUpdate()->first();
            abort_unless($row,404);
            $registerBranch=(int)DB::table('pos_registers')->where('tenant_id',$tenant->id)->where('id',$row->register_id)->value('branch_id');
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,$registerBranch);
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
            return ['shift_id'=>$shift,'expected_minor'=>$expected,'counted_minor'=>$closing,'difference_minor'=>$closing-$expected];
        },3);
    }


    private function audit(Tenant $tenant,Request $request,string $action,int $id,array $metadata=[]): void
    {
        AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
            'action'=>$action,'subject_type'=>'pos_shift','subject_id'=>$id,
            'metadata'=>$metadata,'ip_address'=>$request->ip()]);
    }
}
