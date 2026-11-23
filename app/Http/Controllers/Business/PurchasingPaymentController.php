<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\PosBranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchasingPaymentController extends Controller
{
    public function store(Request $request,Tenant $tenant,int $order)
    {
        $data=$request->validate([
            'idempotency_key'=>'required|uuid',
            'amount'=>'required|numeric|gt:0|max:100000000',
            'method'=>['required',Rule::in(['cash','bank_transfer','external_card','other'])],
            'reference'=>'required|string|max:120',
        ]);
        $amount=(int)round((float)$data['amount']*100);
        if($amount<=0)throw ValidationException::withMessages(['amount'=>'Amount is too small.']);
        DB::transaction(function()use($request,$tenant,$order,$data,$amount){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $existing=DB::table('pos_supplier_payments')->where('tenant_id',$tenant->id)
                ->where('idempotency_key',$data['idempotency_key'])->first();
            if($existing){
                abort_unless((int)$existing->purchase_order_id===$order,409);
                return;
            }
            $po=DB::table('pos_purchase_orders')->where('tenant_id',$tenant->id)->where('id',$order)
                ->lockForUpdate()->first();
            abort_unless($po,404);
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$po->branch_id);
            if(!in_array($po->status,['partial','received'],true)){
                throw ValidationException::withMessages(['amount'=>'Payments can be recorded only against received goods.']);
            }
            $paid=(int)DB::table('pos_supplier_payments')->where('tenant_id',$tenant->id)
                ->where('purchase_order_id',$order)->sum('amount_minor');
            if($paid+$amount>$po->received_minor){
                throw ValidationException::withMessages(['amount'=>'Payment exceeds the received, unpaid amount.']);
            }
            $payment=DB::table('pos_supplier_payments')->insertGetId([
                'tenant_id'=>$tenant->id,'purchase_order_id'=>$order,'actor_id'=>$request->user()->id,
                'idempotency_key'=>$data['idempotency_key'],'amount_minor'=>$amount,
                'method'=>$data['method'],'reference'=>$data['reference'],
                'paid_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'purchasing.supplier.payment.recorded','subject_type'=>'pos_supplier_payment',
                'subject_id'=>$payment,'metadata'=>['purchase_order_id'=>$order,'amount_minor'=>$amount,'method'=>$data['method']],
                'ip_address'=>$request->ip()]);
        },3);
        return back()->with('status','Supplier payment recorded. Verify against the external payment evidence.');
    }
}