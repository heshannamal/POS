<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PosProduct;
use App\Models\PosSale;
use App\Models\Tenant;
use App\Services\PosBranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreDataController extends Controller
{
    public function me(Request $request,Tenant $tenant)
    {
        $user=$request->user();
        return response()->json(['data'=>[
            'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email],
            'tenant'=>['id'=>$tenant->id,'slug'=>$tenant->slug,'name'=>$tenant->name,
                'currency'=>$tenant->currency,'status'=>$tenant->effective_status],
            'device'=>['id'=>$request->attributes->get('pos_api_device')->id,
                'platform'=>$request->attributes->get('pos_api_device')->platform],
        ]]);
    }

    public function branches(Request $request,Tenant $tenant)
    {
        $branches=$tenant->branches()->whereIn('id',app(PosBranchAccess::class)->ids($request->user(),$tenant))
            ->where('is_active',true)->orderBy('name')->get(['id','name','code','timezone','is_default']);
        return response()->json(['data'=>$branches]);
    }

    public function categories(Request $request,Tenant $tenant)
    {
        $rows=DB::table('pos_categories')
            ->where('tenant_id',$tenant->id)->orderBy('name')
            ->get(['id','name','slug']);
        return response()->json(['data'=>$rows]);
    }

    public function products(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'search'=>'nullable|string|max:120',
            'category_id'=>['nullable','integer',Rule::exists('pos_categories','id')->where('tenant_id',$tenant->id)],
            'updated_after'=>'nullable|date',
            'per_page'=>'nullable|integer|min:1|max:100',
            'page'=>'nullable|integer|min:1',
        ]);
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
        $q=PosProduct::where('tenant_id',$tenant->id)->where('is_active',true)
            ->whereNull('deleted_at')
            ->when(!empty($data['category_id']),fn($q)=>$q->where('category_id',$data['category_id']))
            ->when(!empty($data['search']),function($q)use($data){
                $s=trim($data['search']);
                $q->where(fn($w)=>$w->where('name','like',"%{$s}%")
                    ->orWhere('sku','like',"%{$s}%")->orWhere('barcode','like',"%{$s}%"));
            })
            ->when(!empty($data['updated_after']),fn($q)=>$q->where('updated_at','>',$data['updated_after']))
            ->with(['stocks'=>fn($q)=>$q->where('branch_id',$data['branch_id'])])
            ->orderBy('id')->paginate($data['per_page']??50);
        return response()->json([
            'data'=>$q->getCollection()->map(fn($p)=>[
                'id'=>$p->id,'name'=>$p->name,'sku'=>$p->sku,'barcode'=>$p->barcode,
                'category_id'=>$p->category_id,
                'price_minor'=>$p->price_minor,'track_stock'=>(bool)$p->track_stock,
                'quantity'=>(int)($p->stocks->first()?->quantity??0),
                'updated_at'=>$p->updated_at?->toIso8601String(),
            ]),
            'meta'=>['current_page'=>$q->currentPage(),'last_page'=>$q->lastPage(),
                'total'=>$q->total(),'per_page'=>$q->perPage()],
        ]);
    }

    public function stock(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'page'=>'nullable|integer|min:1','per_page'=>'nullable|integer|min:1|max:100',
        ]);
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
        $rows=DB::table('pos_stocks as s')->join('pos_products as p','p.id','=','s.product_id')
            ->where('s.tenant_id',$tenant->id)->where('s.branch_id',$data['branch_id'])
            ->where('p.tenant_id',$tenant->id)
            ->whereNull('p.deleted_at')
            ->select('s.product_id','p.sku','s.quantity','s.reorder_level','s.updated_at')
            ->orderBy('s.id')->paginate($data['per_page']??50);
        return response()->json($rows);
    }

    public function registers(Request $request,Tenant $tenant)
    {
        $ids=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        $registers=DB::table('pos_registers')->where('tenant_id',$tenant->id)
            ->whereIn('branch_id',$ids)->where('is_active',true)->orderBy('id')
            ->get(['id','branch_id','name']);
        $shifts=DB::table('pos_shifts')->where('tenant_id',$tenant->id)
            ->where('user_id',$request->user()->id)->whereNull('closed_at')
            ->whereIn('register_id',$registers->pluck('id'))
            ->get(['id','register_id','opened_at','opening_cash_minor']);
        return response()->json(['data'=>['registers'=>$registers,'my_open_shifts'=>$shifts]]);
    }

    public function sales(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['nullable','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'per_page'=>'nullable|integer|min:1|max:100',
            'page'=>'nullable|integer|min:1',
        ]);
        $allowed=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        if(!empty($data['branch_id']))app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
        $page=PosSale::where('tenant_id',$tenant->id)->whereIn('branch_id',$allowed)
            ->when(!empty($data['branch_id']),fn($q)=>$q->where('branch_id',$data['branch_id']))
            ->latest('id')->paginate($data['per_page']??25);
        return response()->json($page);
    }

    public function sale(Request $request,Tenant $tenant,PosSale $sale)
    {
        abort_unless($sale->tenant_id===$tenant->id,404);
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$sale->branch_id);
        $sale->load('items','payments');
        return response()->json(['data'=>[
            'id'=>$sale->id,'branch_id'=>$sale->branch_id,'shift_id'=>$sale->shift_id,
            'cashier_id'=>$sale->cashier_id,'status'=>$sale->status,
            'subtotal_minor'=>$sale->subtotal_minor,'discount_minor'=>$sale->discount_minor,
            'total_minor'=>$sale->total_minor,'currency'=>$sale->currency,
            'completed_at'=>$sale->completed_at?->toIso8601String(),
            'items'=>$sale->items->map(fn($item)=>[
                'id'=>$item->id,'product_id'=>$item->product_id,'name'=>$item->name,
                'sku'=>$item->sku,'quantity'=>$item->quantity,
                'unit_price_minor'=>$item->unit_price_minor,'line_total_minor'=>$item->line_total_minor,
            ]),
            'payments'=>$sale->payments->map(fn($payment)=>[
                'method'=>$payment->method,'amount_minor'=>$payment->amount_minor,
                'reference'=>$payment->reference,
            ]),
        ]]);
    }
}
