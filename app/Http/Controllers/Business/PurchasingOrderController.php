<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PosProduct;
use App\Models\Tenant;
use App\Services\PosBranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchasingOrderController extends Controller
{
    public function index(Request $request,Tenant $tenant)
    {
        $branches=$tenant->branches()->whereIn('id',app(PosBranchAccess::class)->ids($request->user(),$tenant))->orderBy('name')->get();
        $suppliers=DB::table('pos_suppliers')->where('tenant_id',$tenant->id)->whereNull('deleted_at')
            ->where('is_active',true)->orderBy('name')->get();
        $products=PosProduct::where('tenant_id',$tenant->id)->where('is_active',true)->where('track_stock',true)->orderBy('name')->get();
        $orders=DB::table('pos_purchase_orders as o')
            ->join('pos_suppliers as s','s.id','=','o.supplier_id')
            ->join('branches as b','b.id','=','o.branch_id')
            ->where('o.tenant_id',$tenant->id)->whereIn('o.branch_id',$branches->pluck('id'))
            ->select('o.*','s.name as supplier_name','b.name as branch_name')
            ->orderByDesc('o.id')->paginate(25);
        return view('business.purchasing.orders',compact('tenant','branches','suppliers','products','orders'));
    }

    public function show(Request $request,Tenant $tenant,int $order)
    {
        $purchase=$this->purchase($tenant,$order);
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,$purchase->branch_id);
        $items=DB::table('pos_purchase_order_items')->where('purchase_order_id',$order)->orderBy('id')->get();
        $receipts=DB::table('pos_goods_receipts')->where('tenant_id',$tenant->id)
            ->where('purchase_order_id',$order)->orderByDesc('id')->get();
        $payments=DB::table('pos_supplier_payments')->where('tenant_id',$tenant->id)
            ->where('purchase_order_id',$order)->orderByDesc('id')->get();
        $paid=(int)$payments->sum('amount_minor');
        return view('business.purchasing.order-show',compact('tenant','purchase','items','receipts','payments','paid'));
    }

    public function store(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)->where('is_active',true)],
            'supplier_id'=>['required','integer',Rule::exists('pos_suppliers','id')->where('tenant_id',$tenant->id)->where('is_active',true)->whereNull('deleted_at')],
            'expected_date'=>'nullable|date_format:Y-m-d',
            'note'=>'nullable|string|max:3000',
            'discount_minor'=>'nullable|integer|min:0',
            'items'=>'required|array|min:1|max:100',
            'items.*.product_id'=>['required','integer',Rule::exists('pos_products','id')->where('tenant_id',$tenant->id)->where('is_active',true)->whereNull('deleted_at')],
            'items.*.quantity'=>'required|integer|min:1|max:1000000',
            'items.*.unit_cost'=>'required|numeric|min:0|max:100000000',
        ]);
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
        $orderId=DB::transaction(function()use($request,$tenant,$data){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $productIds=array_column($data['items'],'product_id');
            if(count($productIds)!==count(array_unique($productIds))) {
                throw ValidationException::withMessages(['items'=>'Each product may appear only once on a purchase order.']);
            }
            $products=PosProduct::where('tenant_id',$tenant->id)
                ->whereIn('id',$productIds)->where('is_active',true)->where('track_stock',true)->get()->keyBy('id');
            if($products->count()!==count($productIds)){
                throw ValidationException::withMessages(['items'=>'One or more products are unavailable.']);
            }
            $subtotal=0;$lines=[];
            foreach($data['items'] as $item){
                $product=$products[$item['product_id']];
                $cost=(int)round((float)$item['unit_cost']*100);
                $line=$cost*(int)$item['quantity'];
                if($line>100000000000 || $subtotal+$line>100000000000){
                    throw ValidationException::withMessages(['items'=>'Purchase amount exceeds supported limit.']);
                }
                $subtotal+=$line;
                $lines[]=['product_id'=>$product->id,'name'=>$product->name,'sku'=>$product->sku,
                    'quantity'=>$item['quantity'],'received_quantity'=>0,'unit_cost_minor'=>$cost,
                    'line_total_minor'=>$line,'created_at'=>now(),'updated_at'=>now()];
            }
            $discount=(int)($data['discount_minor']??0);
            if($discount>$subtotal)throw ValidationException::withMessages(['discount_minor'=>'Discount exceeds subtotal.']);
            $id=DB::table('pos_purchase_orders')->insertGetId([
                'tenant_id'=>$tenant->id,'branch_id'=>$data['branch_id'],'supplier_id'=>$data['supplier_id'],
                'created_by'=>$request->user()->id,'status'=>'draft','currency'=>$tenant->currency,
                'subtotal_minor'=>$subtotal,'discount_minor'=>$discount,'total_minor'=>$subtotal-$discount,
                'expected_date'=>$data['expected_date']??null,'note'=>$data['note']??null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach($lines as $line)DB::table('pos_purchase_order_items')->insert([
                ...$line,'purchase_order_id'=>$id,
            ]);
            $this->audit($request,$tenant,'purchasing.order.created',$id);
            return $id;
        },3);
        return redirect()->route('business.purchasing.orders.show',[$tenant,$orderId])->with('status','Purchase order created as draft.');
    }

    public function approve(Request $request,Tenant $tenant,int $order)
    {
        DB::transaction(function()use($request,$tenant,$order){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $po=$this->purchase($tenant,$order,true);
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,$po->branch_id);
            if($po->status!=='draft')throw ValidationException::withMessages(['order'=>'Only draft purchase orders may be approved.']);
            DB::table('pos_purchase_orders')->where('id',$order)->update([
                'status'=>'approved','approved_by'=>$request->user()->id,'approved_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($request,$tenant,'purchasing.order.approved',$order);
        },3);
        return back()->with('status','Purchase order approved. Goods can now be received.');
    }

    public function cancel(Request $request,Tenant $tenant,int $order)
    {
        DB::transaction(function()use($request,$tenant,$order){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $po=$this->purchase($tenant,$order,true);
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,$po->branch_id);
            if(!in_array($po->status,['draft','approved'],true) || $po->received_minor>0){
                throw ValidationException::withMessages(['order'=>'Only unreceived purchase orders can be cancelled.']);
            }
            DB::table('pos_purchase_orders')->where('id',$order)->update(['status'=>'cancelled','updated_at'=>now()]);
            $this->audit($request,$tenant,'purchasing.order.cancelled',$order);
        },3);
        return back()->with('status','Purchase order cancelled.');
    }

    public function purchase(Tenant $tenant,int $id,bool $lock=false):object
    {
        $q=DB::table('pos_purchase_orders as o')
            ->join('pos_suppliers as s','s.id','=','o.supplier_id')
            ->join('branches as b','b.id','=','o.branch_id')
            ->where('o.tenant_id',$tenant->id)->where('o.id',$id)
            ->select('o.*','s.name as supplier_name','b.name as branch_name');
        if($lock)$q->lockForUpdate();
        $row=$q->first();abort_unless($row,404);
        return $row;
    }

    private function audit(Request $request,Tenant $tenant,string $action,int $id):void
    {
        AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
            'action'=>$action,'subject_type'=>'pos_purchase_order','subject_id'=>$id,'ip_address'=>$request->ip()]);
    }
}