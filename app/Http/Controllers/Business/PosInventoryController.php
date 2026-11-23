<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PosProduct;
use App\Models\PosStock;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosInventoryController extends Controller
{
    public function index(Request $request, Tenant $tenant)
    {
        $branches = Branch::where('tenant_id',$tenant->id)->orderBy('name')->get();
        $branch = $branches->firstWhere('id',(int) $request->query('branch_id')) ?? $branches->first();
        $stocks = $branch ? PosProduct::where('tenant_id',$tenant->id)->where('track_stock',true)->with([
            'stocks'=>fn($q)=>$q->where('branch_id',$branch->id)
        ])->orderBy('name')->paginate(30) : null;
        $movements = $branch ? DB::table('pos_stock_movements')->join('pos_products','pos_products.id','=','pos_stock_movements.product_id')
            ->where('pos_stock_movements.tenant_id',$tenant->id)->where('pos_stock_movements.branch_id',$branch->id)
            ->select('pos_stock_movements.*','pos_products.name as product_name')
            ->orderByDesc('pos_stock_movements.id')->limit(25)->get() : collect();
        return view('business.pos.inventory', compact('tenant','branches','branch','stocks','movements'));
    }

    public function adjust(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'product_id'=>['required','integer',Rule::exists('pos_products','id')->where('tenant_id',$tenant->id)->whereNull('deleted_at')],
            'change'=>'required|integer|between:-1000000,1000000|not_in:0',
            'reason'=>['required',Rule::in(['opening','purchase','damage','correction','return'])],
            'note'=>'nullable|string|max:1000',
        ]);
        DB::transaction(function () use ($tenant,$request,$data) {
            $product = PosProduct::where('tenant_id',$tenant->id)->findOrFail($data['product_id']);
            abort_unless($product->track_stock && $product->is_active,422);
            // Serialize creation of new stock rows via branch row locking.
            Branch::where('tenant_id',$tenant->id)->lockForUpdate()->findOrFail($data['branch_id']);
            $stock = PosStock::firstOrCreate(
                ['branch_id'=>$data['branch_id'],'product_id'=>$product->id],
                ['tenant_id'=>$tenant->id,'quantity'=>0,'reorder_level'=>0]
            );
            $stock = PosStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $new = $stock->quantity + (int) $data['change'];
            if ($new < 0) {
                throw ValidationException::withMessages(['change'=>'Adjustment would create negative stock.']);
            }
            $stock->update(['quantity'=>$new]);
            DB::table('pos_stock_movements')->insert([
                'tenant_id'=>$tenant->id,'branch_id'=>$data['branch_id'],'product_id'=>$product->id,
                'actor_id'=>$request->user()->id,'change'=>$data['change'],'balance_after'=>$new,
                'reason'=>$data['reason'],'note'=>$data['note']??null,'created_at'=>now(),
            ]);
            AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'pos.stock.adjusted','subject_type'=>PosProduct::class,'subject_id'=>$product->id,
                'metadata'=>['branch_id'=>$data['branch_id'],'change'=>$data['change'],'reason'=>$data['reason']],
                'ip_address'=>$request->ip()]);
        },3);
        return back()->with('status','Stock adjustment recorded.');
    }

    public function transfer(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'from_branch_id'=>['required','integer','different:to_branch_id',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'to_branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'product_id'=>['required','integer',Rule::exists('pos_products','id')->where('tenant_id',$tenant->id)->whereNull('deleted_at')],
            'quantity'=>'required|integer|min:1|max:1000000',
        ]);
        DB::transaction(function () use ($tenant,$request,$data) {
            $product = PosProduct::where('tenant_id',$tenant->id)->where('track_stock',true)->findOrFail($data['product_id']);
            // Lock branches in stable order to prevent concurrent creation/deadlock.
            Branch::where('tenant_id',$tenant->id)->whereIn('id',[$data['from_branch_id'],$data['to_branch_id']])
                ->orderBy('id')->lockForUpdate()->get();
            foreach ([$data['from_branch_id'],$data['to_branch_id']] as $id) {
                PosStock::firstOrCreate(['branch_id'=>$id,'product_id'=>$product->id],[
                    'tenant_id'=>$tenant->id,'quantity'=>0,'reorder_level'=>0,
                ]);
            }
            $rows = PosStock::where('tenant_id',$tenant->id)->where('product_id',$product->id)
                ->whereIn('branch_id',[$data['from_branch_id'],$data['to_branch_id']])
                ->orderBy('branch_id')->lockForUpdate()->get()->keyBy('branch_id');
            $source = $rows[$data['from_branch_id']];
            $destination = $rows[$data['to_branch_id']];
            if ($source->quantity < $data['quantity']) {
                throw ValidationException::withMessages(['quantity'=>'Insufficient stock at source branch.']);
            }
            $source->decrement('quantity',$data['quantity']);
            $destination->increment('quantity',$data['quantity']);
            $reference = 'TRANSFER-'.strtoupper(\Illuminate\Support\Str::random(12));
            foreach ([[$source,-$data['quantity']],[$destination,$data['quantity']]] as [$stock,$delta]) {
                DB::table('pos_stock_movements')->insert([
                    'tenant_id'=>$tenant->id,'branch_id'=>$stock->branch_id,'product_id'=>$product->id,
                    'actor_id'=>$request->user()->id,'change'=>$delta,
                    'balance_after'=>$stock->fresh()->quantity,'reason'=>'transfer',
                    'reference'=>$reference,'created_at'=>now(),
                ]);
            }
            AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'pos.stock.transferred','subject_type'=>PosProduct::class,'subject_id'=>$product->id,
                'metadata'=>['reference'=>$reference,'from'=>$source->branch_id,'to'=>$destination->branch_id,'quantity'=>$data['quantity']],
                'ip_address'=>$request->ip()]);
        },3);
        return back()->with('status','Stock transferred.');
    }
}