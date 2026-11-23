<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingCoupon;
use App\Services\BillingMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillingCouponController extends Controller
{
    public function index(): View
    {
        $coupons=BillingCoupon::withCount(['redemptions as reserved_count'=>fn($q)=>$q->where('status','reserved'),
            'redemptions as redeemed_count'=>fn($q)=>$q->where('status','redeemed')])
            ->orderByDesc('id')->paginate(20);
        return view('platform.billing.coupons',compact('coupons'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validated($request);
        $coupon=DB::transaction(function () use ($request,$data): BillingCoupon {
            $coupon=BillingCoupon::create($data+['created_by'=>$request->user()->id]);
            AuditLog::create([
                'actor_id'=>$request->user()->id,'action'=>'billing.coupon.created',
                'subject_type'=>BillingCoupon::class,'subject_id'=>$coupon->id,
                'metadata'=>['code'=>$coupon->code,'type'=>$coupon->discount_type],
                'ip_address'=>$request->ip(),
            ]);
            return $coupon;
        });
        return back()->with('status','Coupon '.$coupon->code.' created. A coupon never bypasses payment verification.');
    }

    public function update(Request $request, BillingCoupon $coupon): RedirectResponse
    {
        $data=$request->validate([
            'is_active'=>['required','boolean'],
            'expires_at'=>['nullable','date'],
        ]);
        DB::transaction(function () use ($request,$coupon,$data): void {
            $coupon->update($data);
            AuditLog::create([
                'actor_id'=>$request->user()->id,'action'=>'billing.coupon.updated',
                'subject_type'=>BillingCoupon::class,'subject_id'=>$coupon->id,
                'metadata'=>['is_active'=>(bool)$data['is_active'],'expires_at'=>$data['expires_at']??null],
                'ip_address'=>$request->ip(),
            ]);
        });
        return back()->with('status','Coupon availability updated. Existing reserved discounts retain their invoice snapshot.');
    }

    private function validated(Request $request): array
    {
        $data=$request->validate([
            'code'=>['required','string','min:3','max:40','regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('billing_coupons','code')],
            'description'=>['nullable','string','max:255'],
            'discount_type'=>['required',Rule::in(['percent','fixed'])],
            'value'=>['required','numeric','min:0.01','max:10000000','decimal:0,2'],
            'currency'=>['nullable',Rule::in(['LKR','USD'])],
            'max_uses'=>['nullable','integer','min:1','max:1000000'],
            'max_uses_per_tenant'=>['required','integer','min:1','max:1000000'],
            'starts_at'=>['nullable','date'],
            'expires_at'=>['nullable','date','after:starts_at'],
            'is_active'=>['required','boolean'],
        ]);
        $code=strtoupper($data['code']);
        if (BillingCoupon::where('code',$code)->exists()) {
            throw ValidationException::withMessages(['code'=>'Coupon code already exists.']);
        }
        if ($data['discount_type']==='fixed' && empty($data['currency'])) {
            throw ValidationException::withMessages(['currency'=>'Fixed discounts require a matching currency.']);
        }
        if ($data['discount_type']==='percent' && (float)$data['value']>99.99) {
            throw ValidationException::withMessages(['value'=>'Percentage cannot exceed 99.99%.']);
        }
        if ($data['max_uses']??null) {
            if ($data['max_uses_per_tenant']>$data['max_uses']) {
                throw ValidationException::withMessages(['max_uses_per_tenant'=>'Per-business limit cannot exceed total usage limit.']);
            }
        }
        $basisPoints=BillingMoney::parse((string)$data['value']);
        unset($data['value']);
        $data['discount_value']=$basisPoints;
        $data['code']=$code;
        if ($data['discount_type']==='percent') $data['currency']=null;
        return $data;
    }
}
