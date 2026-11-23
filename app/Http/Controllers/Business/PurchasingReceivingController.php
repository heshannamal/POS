<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PosStock;
use App\Models\Tenant;
use App\Services\PosBranchAccess;
use App\Support\PurchaseAmounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchasingReceivingController extends Controller
{
    public function store(Request $request,Tenant $tenant,int $order)
    {
        $data=$request->validate([
            'idempotency_key'=>'required|uuid','reference'=>'nullable|string|max:120',
            'items'=>'required|array|min:1|max:100',
            'items.*.order_item_id'=>'required|integer',
            'items.*.quantity'=>'required|integer|min:0|max:1000000',
        ]);
        $receipt=DB::transaction(function()use($tenant,$request,$order,$data){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $existing=DB::table('pos_goods_receipts')->where('tenant_id',$tenant->id)
                ->where('idempotency_key',$data['idempotency_key'])->first();
            if($existing){
                abort_unless((int)$existing->purchase_order_id===$order,409,'Receipt key belongs to another order.');
                return $existing->id;
            }
            $po=DB::table('pos_purchase_orders')->where('tenant_id',$tenant->id)
                ->where('id',$order)->lockForUpdate()->first();
            abort_unless($po,404);
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$po->branch_id);
            if(!in_array($po->status,['approved','partial'],true)){
                throw ValidationException::withMessages(['items'=>'Only approved orders can receive goods.']);
            }
            $branch=DB::table('branches')->where('tenant_id',$tenant->id)->where('id',$po->branch_id)
                ->where('is_active',true)->lockForUpdate()->first();
            abort_unless($branch,422,'The receiving branch must be active.');
            $items=DB::table('pos_purchase_order_items')->where('purchase_order_id',$order)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $requested=[];
            foreach($data['items'] as $line){
                $id=(int)$line['order_item_id'];$quantity=(int)$line['quantity'];
                if(!$items->has($id) || isset($requested[$id])){
                    throw ValidationException::withMessages(['items'=>'Unknown or duplicated purchase line.']);
                }
                if($quantity>(int)$items[$id]->quantity-(int)$items[$id]->received_quantity){
                    throw ValidationException::withMessages(['items'=>'Quantity exceeds outstanding purchase quantity.']);
                }
                if($quantity>0)$requested[$id]=$quantity;
            }
            if(!$requested)throw ValidationException::withMessages(['items'=>'Enter a quantity to receive.']);
            // Require tracked, active products: untracked services cannot be stocked.
            $products=DB::table('pos_products')->where('tenant_id',$tenant->id)
                ->whereIn('id',array_map(fn($id)=>(int)$items[$id]->product_id,array_keys($requested)))
                ->where('track_stock',true)->where('is_active',true)->whereNull('deleted_at')
                ->pluck('id')->all();
            if(count($products)!==count($requested))throw ValidationException::withMessages(['items'=>'Every received product must be active and stock-tracked.']);
            $receipt=DB::table('pos_goods_receipts')->insertGetId([
                'tenant_id'=>$tenant->id,'purchase_order_id'=>$order,'received_by'=>$request->user()->id,
                'idempotency_key'=>$data['idempotency_key'],'reference'=>$data['reference']??null,
                'received_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach($requested as $id=>$quantity){
                $line=$items[$id];
                DB::table('pos_purchase_order_items')->where('id',$id)
                    ->increment('received_quantity',$quantity,['updated_at'=>now()]);
                DB::table('pos_goods_receipt_items')->insert([
                    'goods_receipt_id'=>$receipt,'purchase_order_item_id'=>$id,
                    'quantity'=>$quantity,'unit_cost_minor'=>$line->unit_cost_minor,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $stock=PosStock::firstOrCreate([
                    'branch_id'=>$po->branch_id,'product_id'=>$line->product_id,
                ],['tenant_id'=>$tenant->id,'quantity'=>0,'reorder_level'=>0]);
                $stock=PosStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
                $stock->increment('quantity',$quantity);
                DB::table('pos_stock_movements')->insert([
                    'tenant_id'=>$tenant->id,'branch_id'=>$po->branch_id,'product_id'=>$line->product_id,
                    'actor_id'=>$request->user()->id,'change'=>$quantity,
                    'balance_after'=>$stock->fresh()->quantity,'reason'=>'purchase_grn',
                    'reference'=>'GRN-'.$receipt,'created_at'=>now(),
                ]);
                DB::table('pos_products')->where('tenant_id',$tenant->id)->where('id',$line->product_id)
                    ->update(['cost_minor'=>$line->unit_cost_minor,'updated_at'=>now()]);
            }
            $all=DB::table('pos_purchase_order_items')->where('purchase_order_id',$order)->get();
            $receivedGross=0; $complete=true;
            foreach($all as $line){
                $receivedGross+=(int)$line->received_quantity*(int)$line->unit_cost_minor;
                if($line->received_quantity<$line->quantity)$complete=false;
            }
            $receivedAmount=PurchaseAmounts::receivedValue(
                $receivedGross,(int)$po->subtotal_minor,(int)$po->total_minor
            );
            DB::table('pos_purchase_orders')->where('id',$order)->update([
                'received_minor'=>$receivedAmount,'status'=>$complete?'received':'partial','updated_at'=>now(),
            ]);
            AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'purchasing.goods.received','subject_type'=>'pos_goods_receipt','subject_id'=>$receipt,
                'metadata'=>['purchase_order_id'=>$order,'branch_id'=>$po->branch_id],
                'ip_address'=>$request->ip()]);
            return $receipt;
        },3);
        return redirect()->route('business.purchasing.orders.show',[$tenant,$order])
            ->with('status','Goods receipt #'.$receipt.' posted and stock updated.');
    }
}