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
