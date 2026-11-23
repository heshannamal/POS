<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PosBranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    /**
     * Server-issued numeric cursor using stock movement PK.
     * This is a delta feed, not a complete offline consistency protocol.
     */
    public function manifest(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
        ]);
        $branch=(int)$data['branch_id'];
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,$branch);
        // This is an observation watermark only. A client must never use it to
        // infer that local stock can be safely decremented while disconnected.
        $lastMovement=(int)DB::table('pos_stock_movements')
            ->where('tenant_id',$tenant->id)->where('branch_id',$branch)->max('id');
        $lastProductUpdate=DB::table('pos_products')->where('tenant_id',$tenant->id)
            ->max('updated_at');
        return response()->json(['data'=>[
            'contract_version'=>1,
            'tenant_id'=>$tenant->id,'branch_id'=>$branch,
            'server_time'=>now()->toIso8601String(),
            'stock_after_id'=>$lastMovement,
            'latest_product_updated_at'=>$lastProductUpdate,
            'offline_sales_enabled'=>false,
            'write_retry_policy'=>'same UUID and identical transaction body only',
            'catalog_strategy'=>'refresh paginated products on connection; removed/archived products are not yet served as tombstones',
        ]]);
    }

    public function stockMovements(Request $request,Tenant $tenant)
    {
        $data=$request->validate([
            'branch_id'=>['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant->id)],
            'after_id'=>'nullable|integer|min:0',
            'limit'=>'nullable|integer|min:1|max:250',
        ]);
        app(PosBranchAccess::class)->ensure($request->user(),$tenant,(int)$data['branch_id']);
        $after=(int)($data['after_id']??0);
        $limit=(int)($data['limit']??100);
        $rows=DB::table('pos_stock_movements')
            ->where('tenant_id',$tenant->id)->where('branch_id',$data['branch_id'])
            ->where('id','>',$after)->orderBy('id')->limit($limit+1)
            ->get(['id','branch_id','product_id','change','balance_after','reason','created_at']);
        $hasMore=$rows->count()>$limit;
        $dataRows=$rows->take($limit)->values();
        return response()->json(['data'=>$dataRows,
            'meta'=>['next_after_id'=>(int)($dataRows->last()?->id??$after),'has_more'=>$hasMore]]);
    }
}
