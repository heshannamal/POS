<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PosCategory;
use App\Models\PosProduct;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PosCatalogController extends Controller
{
    public function index(Request $request, Tenant $tenant)
    {
        $products = PosProduct::where('tenant_id', $tenant->id)
            ->with('category')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim((string) $request->query('search'));
                $query->where(fn ($q) => $q->where('name','like',"%{$term}%")
                    ->orWhere('sku','like',"%{$term}%")->orWhere('barcode','like',"%{$term}%"));
            })->orderBy('name')->paginate(20)->withQueryString();
        $categories = PosCategory::where('tenant_id',$tenant->id)->orderBy('name')->get();
        return view('business.pos.products', compact('tenant','products','categories'));
    }

    public function category(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['name'=>'required|string|max:120']);
        $slug = Str::slug($data['name']);
        if ($slug === '') {
            return back()->withErrors(['name'=>'Choose a category name containing letters or numbers.']);
        }
        PosCategory::firstOrCreate(['tenant_id'=>$tenant->id,'slug'=>$slug],['name'=>$data['name']]);
        return back()->with('status','Category saved.');
    }

    public function store(Request $request, Tenant $tenant)
    {
        $data = $this->validatedProduct($request,$tenant);
        DB::transaction(function () use ($request,$tenant,$data) {
            $product = PosProduct::create(['tenant_id'=>$tenant->id,...$data]);
            $this->audit($request,$tenant,'pos.product.created',$product->id);
        });
        return back()->with('status','Product added.');
    }

    public function update(Request $request, Tenant $tenant, PosProduct $product)
    {
        abort_unless($product->tenant_id === $tenant->id,404);
        $data = $this->validatedProduct($request,$tenant,$product->id);
        DB::transaction(function () use ($request,$tenant,$data,$product) {
            $product->update($data);
            $this->audit($request,$tenant,'pos.product.updated',$product->id);
        });
        return back()->with('status','Product updated.');
    }

    public function archive(Request $request, Tenant $tenant, PosProduct $product)
    {
        abort_unless($product->tenant_id === $tenant->id,404);
        DB::transaction(function () use ($request,$tenant,$product) {
            $product->update(['is_active'=>false]); $product->delete();
            $this->audit($request,$tenant,'pos.product.archived',$product->id);
        });
        return back()->with('status','Product archived. Transaction history is retained.');
    }

    private function validatedProduct(Request $request, Tenant $tenant, ?int $except = null): array
    {
        $data = $request->validate([
            'name'=>'required|string|max:255',
            'sku'=>['required','string','max:100',Rule::unique('pos_products','sku')->where('tenant_id',$tenant->id)->ignore($except)],
            'barcode'=>'nullable|string|max:100',
            'brand'=>'nullable|string|max:120',
            'description'=>'nullable|string|max:5000',
            'category_id'=>['nullable','integer',Rule::exists('pos_categories','id')->where('tenant_id',$tenant->id)],
            'price'=>'required|numeric|min:0|max:100000000',
            'cost'=>'required|numeric|min:0|max:100000000',
            'track_stock'=>'required|boolean',
            'is_active'=>'required|boolean',
        ]);
        $data['price_minor'] = (int) round(((float) $data['price']) * 100);
        $data['cost_minor'] = (int) round(((float) $data['cost']) * 100);
        unset($data['price'],$data['cost']);
        return $data;
    }

    private function audit(Request $request,Tenant $tenant,string $action,int $id): void
    {
        AuditLog::create(['tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
            'action'=>$action,'subject_type'=>PosProduct::class,'subject_id'=>$id,
            'metadata'=>[],'ip_address'=>$request->ip()]);
    }
}