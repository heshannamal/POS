<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\PosBranchAccess;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PosReportsController extends Controller
{
    private function filters(Request $request,Tenant $tenant): array
    {
        $data=$request->validate([
            'from'=>'nullable|date_format:Y-m-d',
            'to'=>'nullable|date_format:Y-m-d|after_or_equal:from',
            'branch_id'=>['nullable','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
        ]);
        if (!empty($data['branch_id'])) {
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
        }
        return $data;
    }

    private function sales(Tenant $tenant,array $filters)
    {
        return DB::table('pos_sales')->where('tenant_id',$tenant->id)->where('status','completed')
            ->whereIn('branch_id',app(PosBranchAccess::class)->ids(auth()->user(),$tenant))
            ->when(!empty($filters['branch_id']),fn($q)=>$q->where('branch_id',$filters['branch_id']))
            ->when(!empty($filters['from']),fn($q)=>$q->whereDate('completed_at','>=',$filters['from']))
            ->when(!empty($filters['to']),fn($q)=>$q->whereDate('completed_at','<=',$filters['to']));
    }

    private function returns(Tenant $tenant,array $filters)
    {
        return DB::table('pos_returns as r')->join('pos_sales as s','s.id','=','r.sale_id')
            ->where('r.tenant_id',$tenant->id)
            ->whereIn('s.branch_id',app(PosBranchAccess::class)->ids(auth()->user(),$tenant))
            ->when(!empty($filters['branch_id']),fn($q)=>$q->where('s.branch_id',$filters['branch_id']))
            ->when(!empty($filters['from']),fn($q)=>$q->whereDate('r.created_at','>=',$filters['from']))
            ->when(!empty($filters['to']),fn($q)=>$q->whereDate('r.created_at','<=',$filters['to']));
    }

    public function index(Request $request,Tenant $tenant)
    {
        $filters=$this->filters($request,$tenant);
        $allowed=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        $branches=Branch::where('tenant_id',$tenant->id)->whereIn('id',$allowed)->orderBy('name')->get();
        $saleCount=(clone $this->sales($tenant,$filters))->count();
        $gross=(int)(clone $this->sales($tenant,$filters))->sum('total_minor');
        $refund=(int)(clone $this->returns($tenant,$filters))->sum('refund_minor');
        $byBranch=(clone $this->sales($tenant,$filters))->join('branches as b','b.id','=','pos_sales.branch_id')
            ->select('b.name',DB::raw('COUNT(pos_sales.id) as sale_count'),DB::raw('SUM(pos_sales.total_minor) as total_minor'))
            ->groupBy('b.id','b.name')->orderBy('b.name')->get();
        $byCashier=(clone $this->sales($tenant,$filters))->join('users as u','u.id','=','pos_sales.cashier_id')
            ->select('u.name',DB::raw('COUNT(pos_sales.id) as sale_count'),DB::raw('SUM(pos_sales.total_minor) as total_minor'))
            ->groupBy('u.id','u.name')->orderByDesc('total_minor')->limit(30)->get();
        $topProducts=DB::table('pos_sale_items as i')->join('pos_sales as s','s.id','=','i.sale_id')
            ->where('s.tenant_id',$tenant->id)->where('s.status','completed')
            ->whereIn('s.branch_id',$allowed)
            ->when(!empty($filters['branch_id']),fn($q)=>$q->where('s.branch_id',$filters['branch_id']))
            ->when(!empty($filters['from']),fn($q)=>$q->whereDate('s.completed_at','>=',$filters['from']))
            ->when(!empty($filters['to']),fn($q)=>$q->whereDate('s.completed_at','<=',$filters['to']))
            ->select('i.sku','i.name',DB::raw('SUM(i.quantity) AS units'),
                DB::raw('SUM(i.line_total_minor) AS gross_minor'),
                DB::raw('SUM(CASE WHEN i.unit_cost_minor IS NOT NULL THEN i.unit_cost_minor * i.quantity ELSE 0 END) AS known_cost_minor'))
            ->groupBy('i.sku','i.name')->orderByDesc('gross_minor')->limit(30)->get();
        $stock=DB::table('pos_stocks as s')->join('pos_products as p','p.id','=','s.product_id')
            ->where('s.tenant_id',$tenant->id)->whereIn('s.branch_id',$allowed)
            ->when(!empty($filters['branch_id']),fn($q)=>$q->where('s.branch_id',$filters['branch_id']))
            ->selectRaw('COALESCE(SUM(s.quantity * p.cost_minor),0) AS valuation_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN s.quantity <= s.reorder_level THEN 1 ELSE 0 END),0) AS low_stock_count')
            ->first();
        return view('business.pos.reports',compact('tenant','branches','filters','saleCount','gross','refund','byBranch','byCashier','topProducts','stock'));
    }

    public function csv(Request $request,Tenant $tenant)
    {
        $filters=$this->filters($request,$tenant);
        $rows=(clone $this->sales($tenant,$filters))->orderBy('id')->get();
        return response()->streamDownload(function ()use($rows){
            $out=fopen('php://output','w');
            fputcsv($out,['Sale ID','Date','Branch ID','Cashier ID','Currency','Subtotal minor','Discount minor','Total minor']);
            foreach($rows as $row){
                fputcsv($out,[$row->id,$row->completed_at,$row->branch_id,$row->cashier_id,
                    $row->currency,$row->subtotal_minor,$row->discount_minor,$row->total_minor]);
            }
            fclose($out);
        },'pos-sales-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
