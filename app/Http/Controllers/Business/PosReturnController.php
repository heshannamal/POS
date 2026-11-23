<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\PosBranchAccess;
use App\Models\AuditLog;
use App\Models\PosSale;
use App\Models\PosStock;
use App\Models\Tenant;
use App\Services\PosShiftAccounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosReturnController extends Controller
{
    public function index(Tenant $tenant)
    {
        $allowed=app(PosBranchAccess::class)->ids(auth()->user(),$tenant);
        $returns = DB::table('pos_returns as r')
            ->join('pos_sales as s','s.id','=','r.sale_id')
            ->where('r.tenant_id',$tenant->id)->whereIn('s.branch_id',$allowed)
            ->select('r.*','s.currency')->orderByDesc('r.id')->paginate(25);
        return view('business.pos.returns',compact('tenant','returns'));
    }

    public function create(Tenant $tenant, PosSale $sale)
    {
        abort_unless($sale->tenant_id===$tenant->id && $sale->status==='completed',404);
        app(PosBranchAccess::class)->ensure(auth()->user(),$tenant,$sale->branch_id);
        $sale->load('items');
        $returned = DB::table('pos_return_items as ri')->join('pos_returns as r','r.id','=','ri.return_id')
            ->where('r.tenant_id',$tenant->id)->where('r.sale_id',$sale->id)
            ->select('ri.sale_item_id',DB::raw('SUM(ri.quantity) AS quantity'))
            ->groupBy('ri.sale_item_id')->pluck('quantity','sale_item_id');
        $shifts = DB::table('pos_shifts as s')->join('pos_registers as r','r.id','=','s.register_id')
            ->where('s.tenant_id',$tenant->id)->where('r.branch_id',$sale->branch_id)
            ->whereNull('s.closed_at')->where('s.user_id',auth()->id())
            ->select('s.id','r.name')->get();
        return view('business.pos.return-create',compact('tenant','sale','returned','shifts'));
    }

    public function store(Request $request,Tenant $tenant,PosSale $sale,PosShiftAccounting $accounting)
    {
        abort_unless($sale->tenant_id===$tenant->id,404);
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,$sale->branch_id);
        $data=$request->validate([
            'idempotency_key'=>'required|uuid',
            'shift_id'=>['required','integer',Rule::exists('pos_shifts','id')->where('tenant_id',$tenant->id)->where('user_id',$request->user()->id)->whereNull('closed_at')],
            'reason'=>'required|string|min:3|max:500',
            'items'=>'required|array|min:1|max:100',
            'items.*.sale_item_id'=>'required|integer',
            'items.*.quantity'=>'required|integer|min:0',
            'items.*.restock'=>'nullable|boolean',
        ]);
        $returnId=DB::transaction(function ()use($tenant,$request,$data,$sale,$accounting){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $existing=DB::table('pos_returns')->where('tenant_id',$tenant->id)
                ->where('idempotency_key',$data['idempotency_key'])->first();
            if($existing)return $existing->id;
            $shift=DB::table('pos_shifts as s')->join('pos_registers as r','r.id','=','s.register_id')
                ->where('s.tenant_id',$tenant->id)->where('s.id',$data['shift_id'])
                ->where('s.user_id',$request->user()->id)->where('r.branch_id',$sale->branch_id)
                ->whereNull('s.closed_at')->select('s.*')->first();
            abort_unless($shift,403);
            $lockedSale=PosSale::where('tenant_id',$tenant->id)->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedSale->status==='completed',422);
            abort_unless($lockedSale->subtotal_minor>0,422,'Zero-subtotal sales cannot be refunded.');
            $items=DB::table('pos_sale_items')->where('sale_id',$sale->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $requested=[];
            foreach($data['items'] as $item){
                $id=(int)$item['sale_item_id']; $qty=(int)$item['quantity'];
                if(!$items->has($id))throw ValidationException::withMessages(['items'=>'Item does not belong to this sale.']);
                if(isset($requested[$id]))throw ValidationException::withMessages(['items'=>'Duplicate sale item.']);
                if($qty>0)$requested[$id]=['qty'=>$qty,'restock'=>(bool)($item['restock']??false)];
            }
            if(!$requested)throw ValidationException::withMessages(['items'=>'Select at least one return quantity.']);
            $prior=DB::table('pos_return_items as ri')
                ->join('pos_returns as r','r.id','=','ri.return_id')
                ->where('r.tenant_id',$tenant->id)->where('r.sale_id',$sale->id)
                ->select('ri.sale_item_id',DB::raw('SUM(ri.quantity) AS quantity'))
                ->groupBy('ri.sale_item_id')->pluck('quantity','sale_item_id');
            $priorRefund=(int)DB::table('pos_returns')->where('tenant_id',$tenant->id)
                ->where('sale_id',$sale->id)->sum('refund_minor');
            $totalGross=0;
            foreach($items as $id=>$item){
                $qty=(int)($prior[$id]??0)+(int)($requested[$id]['qty']??0);
                if($qty>$item->quantity)throw ValidationException::withMessages(['items'=>'Return exceeds quantity sold.']);
                $totalGross+=$qty*$item->unit_price_minor;
            }
            $targetRefund=intdiv($totalGross*$lockedSale->total_minor,$lockedSale->subtotal_minor);
            $refund=$targetRefund-$priorRefund;
            if($refund<0 || $targetRefund>$lockedSale->total_minor){
                throw ValidationException::withMessages(['items'=>'Refund calculation exceeds original payment.']);
            }
            if($accounting->expected($shift->id,$shift->opening_cash_minor)<$refund){
                throw ValidationException::withMessages(['shift_id'=>'Insufficient cash in register to issue refund.']);
            }
            $returnId=DB::table('pos_returns')->insertGetId([
                'tenant_id'=>$tenant->id,'sale_id'=>$sale->id,'shift_id'=>$shift->id,
                'actor_id'=>$request->user()->id,'idempotency_key'=>$data['idempotency_key'],
                'refund_minor'=>$refund,'reason'=>$data['reason'],'created_at'=>now(),'updated_at'=>now(),
            ]);
            $allocated=0; $firstId=array_key_first($requested);
            foreach($requested as $id=>$entry){
                $item=$items[$id];$qty=$entry['qty'];
                $lineRefund=intdiv($item->unit_price_minor*$qty*$lockedSale->total_minor,$lockedSale->subtotal_minor);
                if($id===$firstId){
                    $lineRefund=$refund;
                    foreach($requested as $otherId=>$other)if($otherId!==$id) {
                        $lineRefund-=intdiv($items[$otherId]->unit_price_minor*$other['qty']*$lockedSale->total_minor,$lockedSale->subtotal_minor);
                    }
                }
                $allocated+=$lineRefund;
                DB::table('pos_return_items')->insert([
                    'return_id'=>$returnId,'sale_item_id'=>$id,'quantity'=>$qty,
                    'restock'=>$entry['restock'],'refund_minor'=>$lineRefund,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                if($entry['restock']){
                    $product=DB::table('pos_products')->where('tenant_id',$tenant->id)->where('id',$item->product_id)->first();
                    if(!$product || !$product->track_stock)continue;
                    $stock=PosStock::firstOrCreate(['branch_id'=>$sale->branch_id,'product_id'=>$item->product_id],[
                        'tenant_id'=>$tenant->id,'quantity'=>0,'reorder_level'=>0,
                    ]);
                    $stock=PosStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
                    $stock->increment('quantity',$qty);
                    DB::table('pos_stock_movements')->insert([
                        'tenant_id'=>$tenant->id,'branch_id'=>$sale->branch_id,'product_id'=>$item->product_id,
                        'actor_id'=>$request->user()->id,'change'=>$qty,'balance_after'=>$stock->fresh()->quantity,
                        'reason'=>'sale_return','reference'=>'RETURN-'.$returnId,'created_at'=>now(),
                    ]);
                }
            }
            DB::table('pos_cash_movements')->insert([
                'tenant_id'=>$tenant->id,'shift_id'=>$shift->id,'actor_id'=>$request->user()->id,
                'type'=>'refund','amount_minor'=>-$refund,'reference'=>'RETURN-'.$returnId,
                'note'=>$data['reason'],'created_at'=>now(),'updated_at'=>now(),
            ]);
            AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'pos.sale.refunded','subject_type'=>PosSale::class,'subject_id'=>$sale->id,
                'metadata'=>['return_id'=>$returnId,'amount_minor'=>$refund,'shift_id'=>$shift->id],
                'ip_address'=>$request->ip()]);
            return $returnId;
        },3);
        return redirect()->route('business.pos.returns',$tenant)->with('status','Return recorded. Cash refund issued in register ledger. Ref #'.$returnId);
    }
}
