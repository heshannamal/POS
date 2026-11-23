<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\PosBranchAccess;
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
        $allowed=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        $branches = Branch::where('tenant_id',$tenant->id)->whereIn('id',$allowed)->where('is_active',true)->orderBy('name')->get();
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
        $allowed=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        $sales = PosSale::where('tenant_id',$tenant->id)->whereIn('branch_id',$allowed)->with('items','payments')
            ->latest('id')->paginate(25);
        return view('business.pos.sales', compact('tenant','sales'));
    }

    public function receipt(Request $request,Tenant $tenant, PosSale $sale)
    {
        abort_unless($sale->tenant_id===$tenant->id,404);
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,$sale->branch_id);
        $sale->load('items','payments');
        return view('business.pos.receipt', compact('tenant','sale'));
    }

    public function checkout(Request $request, Tenant $tenant)
    {
        $sale = app(\App\Services\PosCheckoutService::class)->checkout($request, $tenant);
        return redirect()->route('business.pos.receipt', [$tenant, $sale])->with('status', 'Sale completed.');
    }

}