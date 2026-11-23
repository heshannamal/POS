<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchasingSupplierController extends Controller
{
    public function index(Request $request,Tenant $tenant)
    {
        $suppliers=DB::table('pos_suppliers')->where('tenant_id',$tenant->id)
            ->whereNull('deleted_at')
            ->when($request->filled('search'),fn($q)=>$q->where('name','like','%'.trim($request->query('search')).'%'))
            ->orderBy('name')->paginate(20);
        return view('business.purchasing.suppliers',compact('tenant','suppliers'));
    }

    public function store(Request $request,Tenant $tenant)
    {
        $data=$this->validateSupplier($request,$tenant);
        DB::transaction(function()use($request,$tenant,$data){
            $id=DB::table('pos_suppliers')->insertGetId([
                ...$data,'tenant_id'=>$tenant->id,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($tenant,$request,'purchasing.supplier.created',$id);
        });
        return back()->with('status','Supplier created.');
    }

    public function update(Request $request,Tenant $tenant,int $supplier)
    {
        $row=DB::table('pos_suppliers')->where('tenant_id',$tenant->id)->whereNull('deleted_at')->where('id',$supplier)->first();
        abort_unless($row,404);
        $data=$this->validateSupplier($request,$tenant,$supplier);
        DB::transaction(function()use($request,$tenant,$data,$supplier){
            DB::table('pos_suppliers')->where('tenant_id',$tenant->id)->where('id',$supplier)
                ->update([...$data,'updated_at'=>now()]);
            $this->audit($tenant,$request,'purchasing.supplier.updated',$supplier);
        });
        return back()->with('status','Supplier updated.');
    }

    public function archive(Request $request,Tenant $tenant,int $supplier)
    {
        DB::transaction(function()use($tenant,$request,$supplier){
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $row=DB::table('pos_suppliers')->where('tenant_id',$tenant->id)
                ->where('id',$supplier)->whereNull('deleted_at')->first();
            abort_unless($row,404);
            $open=DB::table('pos_purchase_orders')->where('tenant_id',$tenant->id)
                ->where('supplier_id',$supplier)->whereIn('status',['draft','approved','partial'])->exists();
            abort_if($open,422,'Finish or cancel active purchase orders before archiving this supplier.');
            DB::table('pos_suppliers')->where('id',$supplier)->update([
                'deleted_at'=>now(),'is_active'=>false,'updated_at'=>now(),
            ]);
            $this->audit($tenant,$request,'purchasing.supplier.archived',$supplier);
        });
        return back()->with('status','Supplier archived; purchase history preserved.');
    }

    private function validateSupplier(Request $request,Tenant $tenant,?int $id=null):array
    {
        return $request->validate([
            'name'=>'required|string|max:255',
            'code'=>['required','string','max:60',Rule::unique('pos_suppliers','code')->where('tenant_id',$tenant->id)->ignore($id)],
            'contact_person'=>'nullable|string|max:255',
            'email'=>'nullable|email|max:255',
            'phone'=>'nullable|string|max:80',
            'address'=>'nullable|string|max:3000',
            'payment_terms_days'=>'required|integer|min:0|max:365',
            'is_active'=>'required|boolean',
        ]);
    }

    private function audit(Tenant $tenant,Request $request,string $action,int $id):void
    {
        AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
            'action'=>$action,'subject_type'=>'pos_supplier','subject_id'=>$id,'ip_address'=>$request->ip()]);
    }
}