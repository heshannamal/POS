<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PosBranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchasingReportsController extends Controller
{
    private function query(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'from'=>'nullable|date_format:Y-m-d',
            'to'=>'nullable|date_format:Y-m-d|after_or_equal:from',
            'branch_id'=>['nullable','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
        ]);
        $allowed=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        if(!empty($data['branch_id']))app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
        $query=DB::table('pos_purchase_orders as o')
            ->join('pos_suppliers as s','s.id','=','o.supplier_id')
            ->join('branches as b','b.id','=','o.branch_id')
            ->where('o.tenant_id',$tenant->id)->whereIn('o.branch_id',$allowed)
            ->when(!empty($data['branch_id']),fn($q)=>$q->where('o.branch_id',$data['branch_id']))
            ->when(!empty($data['from']),fn($q)=>$q->whereDate('o.created_at','>=',$data['from']))
            ->when(!empty($data['to']),fn($q)=>$q->whereDate('o.created_at','<=',$data['to']))
            ->select('o.*','s.name as supplier_name','b.name as branch_name')
            ->orderByDesc('o.id');
        return [$query,$data,$allowed];
    }

    public function index(Request $request,Tenant $tenant)
    {
        [$query,$filters,$allowed]=$this->query($request,$tenant);
        $orders=(clone $query)->paginate(25)->withQueryString();
        $branches=$tenant->branches()->whereIn('id',$allowed)->orderBy('name')->get();
        $all=(clone $query)->get();
        $receivable=(int)$all->sum('received_minor');
        $paid=(int)DB::table('pos_supplier_payments')->where('tenant_id',$tenant->id)
            ->whereIn('purchase_order_id',$all->pluck('id'))->sum('amount_minor');
        $stock=DB::table('pos_stocks as st')->join('pos_products as p','p.id','=','st.product_id')
            ->where('st.tenant_id',$tenant->id)->whereIn('st.branch_id',$allowed)
            ->when(!empty($filters['branch_id']),fn($q)=>$q->where('st.branch_id',$filters['branch_id']))
            ->selectRaw('COALESCE(SUM(st.quantity * p.cost_minor),0) AS valuation_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN st.quantity <= st.reorder_level THEN 1 ELSE 0 END),0) AS low_stock_count')->first();
        $suggestions=DB::table('pos_stocks as st')->join('pos_products as p','p.id','=','st.product_id')
            ->join('branches as b','b.id','=','st.branch_id')
            ->where('st.tenant_id',$tenant->id)->whereIn('st.branch_id',$allowed)
            ->when(!empty($filters['branch_id']),fn($q)=>$q->where('st.branch_id',$filters['branch_id']))
            ->where('p.track_stock',true)->whereColumn('st.quantity','<=','st.reorder_level')
            ->select('p.name','p.sku','b.name as branch_name','st.quantity','st.reorder_level')
            ->orderBy('st.quantity')->limit(40)->get();
        return view('business.purchasing.reports',compact('tenant','orders','branches','filters','receivable','paid','stock','suggestions'));
    }

    public function csv(Request $request,Tenant $tenant)
    {
        [$query]=$this->query($request,$tenant);
        return response()->streamDownload(function ()use($query){
            $stream=fopen('php://output','w');
            fputcsv($stream,['PO','Supplier','Branch','Status','Currency','Order total minor','Goods received minor','Date']);
            (clone $query)->orderBy('o.id')->chunk(500,function($rows)use($stream){
                foreach($rows as $row)fputcsv($stream,[$row->id,$row->supplier_name,$row->branch_name,
                    $row->status,$row->currency,$row->total_minor,$row->received_minor,$row->created_at]);
            });
            fclose($stream);
        },'pos-purchasing-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}