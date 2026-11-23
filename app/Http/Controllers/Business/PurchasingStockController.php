<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PosStock;
use App\Models\Tenant;
use App\Services\PosBranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchasingStockController extends Controller
{
    public function index(Request $request,Tenant $tenant)
    {
        $allowed=app(PosBranchAccess::class)->ids($request->user(),$tenant);
        $branches=$tenant->branches()->whereIn('id',$allowed)->orderBy('name')->get();
        $branch=$branches->firstWhere('id',(int)$request->query('branch_id'))??$branches->first();
        $stocks=$branch?DB::table('pos_stocks as s')
            ->join('pos_products as p','p.id','=','s.product_id')
            ->where('s.tenant_id',$tenant->id)->where('s.branch_id',$branch->id)
            ->where('p.track_stock',true)
            ->select('s.*','p.name','p.sku')->orderBy('p.name')->paginate(30):null;
        $counts=$branch?DB::table('pos_inventory_counts as c')
            ->join('pos_products as p','p.id','=','c.product_id')
            ->where('c.tenant_id',$tenant->id)->where('c.branch_id',$branch->id)
            ->select('c.*','p.name')->orderByDesc('c.id')->limit(20)->get():collect();
        return view('business.purchasing.stock',compact('tenant','branches','branch','stocks','counts'));
    }

    public function reorder(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'product_id'=>['required','integer',Rule::exists('pos_products','id')->where('tenant_id',$tenant->id)->whereNull('deleted_at')],
            'reorder_level'=>'required|integer|min:0|max:10000000',
        ]);
        DB::transaction(function()use($request,$tenant,$data){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
            $product=DB::table('pos_products')->where('tenant_id',$tenant->id)
                ->where('id',$data['product_id'])->where('track_stock',true)->first();
            abort_unless($product,422);
            $stock=PosStock::firstOrCreate([
                'branch_id'=>$data['branch_id'],'product_id'=>$data['product_id'],
            ],['tenant_id'=>$tenant->id,'quantity'=>0,'reorder_level'=>0]);
            PosStock::whereKey($stock->id)->update(['reorder_level'=>$data['reorder_level']]);
            $this->audit($request,$tenant,'purchasing.stock.reorder_level',$data['product_id'],['branch'=>$data['branch_id'],'reorder_level'=>$data['reorder_level']]);
        },3);
        return back()->with('status','Reorder threshold updated.');
    }

    public function count(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'product_id'=>['required','integer',Rule::exists('pos_products','id')->where('tenant_id',$tenant->id)->whereNull('deleted_at')],
            'counted_quantity'=>'required|integer|min:0|max:100000000',
            'reason'=>'required|string|min:5|max:255',
        ]);
        DB::transaction(function()use($request,$tenant,$data){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
            $product=DB::table('pos_products')->where('tenant_id',$tenant->id)
                ->where('id',$data['product_id'])->where('track_stock',true)->first();
            abort_unless($product,422);
            $stock=PosStock::firstOrCreate([
                'branch_id'=>$data['branch_id'],'product_id'=>$data['product_id'],
            ],['tenant_id'=>$tenant->id,'quantity'=>0,'reorder_level'=>0]);
            $stock=PosStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $before=(int)$stock->quantity; $after=(int)$data['counted_quantity'];
            $id=DB::table('pos_inventory_counts')->insertGetId([
                'tenant_id'=>$tenant->id,'branch_id'=>$data['branch_id'],'product_id'=>$data['product_id'],
                'actor_id'=>$request->user()->id,'before_quantity'=>$before,
                'counted_quantity'=>$after,'reason'=>$data['reason'],
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $delta=$after-$before;
            if($delta!==0){
                $stock->update(['quantity'=>$after]);
                DB::table('pos_stock_movements')->insert([
                    'tenant_id'=>$tenant->id,'branch_id'=>$data['branch_id'],'product_id'=>$data['product_id'],
                    'actor_id'=>$request->user()->id,'change'=>$delta,'balance_after'=>$after,
                    'reason'=>'inventory_count','reference'=>'COUNT-'.$id,'note'=>$data['reason'],
                    'created_at'=>now(),
                ]);
            }
            $this->audit($request,$tenant,'purchasing.stock.counted',$id,['before'=>$before,'after'=>$after]);
        },3);
        return back()->with('status','Physical inventory count recorded.');
    }

    private function audit(Request $request,Tenant $tenant,string $action,int $id,array $meta=[]):void
    {
        AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
            'action'=>$action,'subject_type'=>'pos_inventory','subject_id'=>$id,
            'metadata'=>$meta,'ip_address'=>$request->ip()]);
    }
}
