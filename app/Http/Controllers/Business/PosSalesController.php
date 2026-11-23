<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PosProduct;
use App\Models\PosSale;
use App\Models\PosStock;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosSalesController extends Controller
{
    public function terminal(Request $request, Tenant $tenant)
    {
        $branches = Branch::where('tenant_id',$tenant->id)->where('is_active',true)->orderBy('name')->get();
        $branch = $branches->firstWhere('id',(int)$request->query('branch_id')) ?? $branches->first();
        $products = PosProduct::where('tenant_id',$tenant->id)->where('is_active',true)
            ->when($request->filled('search'),function($q)use($request){
                $term=trim((string)$request->query('search'));
                $q->where(fn($p)=>$p->where('name','like',"%{$term}%")
                    ->orWhere('sku','like',"%{$term}%")->orWhere('barcode','like',"%{$term}%"));
            })->with(['stocks'=>fn($q)=>$branch?$q->where('branch_id',$branch->id):$q->whereRaw('1=0')])
            ->orderBy('name')->limit(100)->get();
        $openShift = $branch ? DB::table('pos_shifts as s')
            ->join('pos_registers as r','r.id','=','s.register_id')
            ->where('s.tenant_id',$tenant->id)->where('r.branch_id',$branch->id)
            ->where('s.user_id',$request->user()->id)->whereNull('s.closed_at')
            ->select('s.id','r.name as register_name')->first() : null;
        return view('business.pos.terminal', compact('tenant','branches','branch','products','openShift'));
    }

    public function history(Request $request, Tenant $tenant)
    {
        $sales = PosSale::where('tenant_id',$tenant->id)->with('items','payments')
            ->latest('id')->paginate(25);
        return view('business.pos.sales', compact('tenant','sales'));
    }

    public function receipt(Tenant $tenant, PosSale $sale)
    {
        abort_unless($sale->tenant_id===$tenant->id,404);
        $sale->load('items','payments');
        return view('business.pos.receipt', compact('tenant','sale'));
    }

    public function checkout(Request $request, Tenant $tenant)
    {
        $data=$request->validate([
            'idempotency_key'=>'required|uuid',
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)->where('is_active',true)],
            'shift_id'=>['required','integer',Rule::exists('pos_shifts','id')->where('tenant_id',$tenant->id)->where('user_id',$request->user()->id)->whereNull('closed_at')],
            'items'=>'required|array|min:1|max:100',
            'items.*.product_id'=>['required','integer',Rule::exists('pos_products','id')->where('tenant_id',$tenant->id)->whereNull('deleted_at')],
            'items.*.quantity'=>'required|integer|min:1|max:10000',
            'discount_minor'=>'nullable|integer|min:0',
            'payment_method'=>['required',Rule::in(['cash'])],
            'payment_reference'=>'nullable|string|max:120',
        ]);

        $sale=DB::transaction(function ()use($data,$tenant,$request){
            // Lock tenant to serialize same-key checkouts and safeguard stock-row creation.
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $existing=PosSale::where('tenant_id',$tenant->id)->where('idempotency_key',$data['idempotency_key'])->first();
            if($existing){return $existing;}
            $branch=Branch::where('tenant_id',$tenant->id)->where('is_active',true)->findOrFail($data['branch_id']);
            $shift=DB::table('pos_shifts as s')->join('pos_registers as r','r.id','=','s.register_id')
                ->where('s.tenant_id',$tenant->id)->where('s.id',$data['shift_id'])
                ->where('s.user_id',$request->user()->id)->where('r.branch_id',$branch->id)
                ->where('r.is_active',true)->whereNull('s.closed_at')->select('s.id')->first();
            if (!$shift) {
                throw ValidationException::withMessages(['shift_id'=>'Open your cashier shift for this branch before selling.']);
            }
            $quantities=[];
            foreach($data['items'] as $item){
                $id=(int)$item['product_id'];
                $quantities[$id]=($quantities[$id]??0)+(int)$item['quantity'];
                if($quantities[$id]>10000){
                    throw ValidationException::withMessages(['items'=>'Quantity exceeds limit.']);
                }
            }
            $products=PosProduct::where('tenant_id',$tenant->id)->where('is_active',true)
                ->whereIn('id',array_keys($quantities))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if($products->count()!==count($quantities)){
                throw ValidationException::withMessages(['items'=>'A product is no longer available.']);
            }
            $subtotal=0;
            foreach($quantities as $id=>$qty){
                $price=$products[$id]->price_minor;
                if ($price>intdiv(PHP_INT_MAX,max(1,$qty))) {
                    throw ValidationException::withMessages(['items'=>'Sale amount exceeds permitted range.']);
                }
                $subtotal+=$price*$qty;
            }
            if ($subtotal > 100000000) {
                throw ValidationException::withMessages(['items'=>'Maximum supported sale amount is 1,000,000 currency units. Split the sale.']);
            }
            $discount=(int)($data['discount_minor']??0);
            if($discount>$subtotal){throw ValidationException::withMessages(['discount_minor'=>'Discount exceeds subtotal.']);}
            foreach($quantities as $id=>$qty){
                $product=$products[$id];
                if(!$product->track_stock)continue;
                $stock=PosStock::firstOrCreate(['branch_id'=>$branch->id,'product_id'=>$id],
                    ['tenant_id'=>$tenant->id,'quantity'=>0,'reorder_level'=>0]);
                $stock=PosStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
                if($stock->quantity<$qty){
                    throw ValidationException::withMessages(['items'=>"Insufficient stock for {$product->name}."]);
                }
                $stock->decrement('quantity',$qty);
                DB::table('pos_stock_movements')->insert([
                    'tenant_id'=>$tenant->id,'branch_id'=>$branch->id,'product_id'=>$id,
                    'actor_id'=>$request->user()->id,'change'=>-$qty,'balance_after'=>$stock->fresh()->quantity,
                    'reason'=>'sale','reference'=>$data['idempotency_key'],'created_at'=>now(),
                ]);
            }
            $sale=PosSale::create([
                'tenant_id'=>$tenant->id,'branch_id'=>$branch->id,'shift_id'=>$shift->id,'cashier_id'=>$request->user()->id,
                'idempotency_key'=>$data['idempotency_key'],'status'=>'completed',
                'subtotal_minor'=>$subtotal,'discount_minor'=>$discount,'total_minor'=>$subtotal-$discount,
                'currency'=>$tenant->currency,'completed_at'=>now(),
            ]);
            foreach($quantities as $id=>$qty){
                $product=$products[$id];
                $sale->items()->create(['product_id'=>$id,'name'=>$product->name,'sku'=>$product->sku,
                    'quantity'=>$qty,'unit_price_minor'=>$product->price_minor,'unit_cost_minor'=>$product->cost_minor,
                    'line_total_minor'=>$product->price_minor*$qty]);
            }
            $sale->payments()->create(['method'=>$data['payment_method'],
                'amount_minor'=>$subtotal-$discount,'reference'=>$data['payment_reference']??null]);
            AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'pos.sale.completed','subject_type'=>PosSale::class,'subject_id'=>$sale->id,
                'metadata'=>['branch_id'=>$branch->id,'shift_id'=>$shift->id,'total_minor'=>$subtotal-$discount],
                'ip_address'=>$request->ip()]);
            return $sale;
        },3);
        return redirect()->route('business.pos.receipt',[$tenant,$sale])->with('status','Sale completed.');
    }
}