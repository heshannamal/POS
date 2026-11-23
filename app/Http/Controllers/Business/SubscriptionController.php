<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use App\Models\PaymentMethod;
use App\Models\SubscriptionPaymentRequest;
use App\Models\Tenant;
use App\Services\FeatureAccess;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Tenant $tenant, FeatureAccess $access): View
    {
        $tenant->load(['trial', 'subscription.plan.modules', 'moduleGrants.module']);
        $modules = Module::orderBy('category')->orderBy('name')->get();
        $allowed = [];
        foreach ($modules as $module) {
            $allowed[$module->code] = $access->allows($tenant, $module->code);
        }

        $plans = Plan::where('is_active', true)->where('is_public', true)
            ->where('currency', $tenant->currency)->orderBy('monthly_price_minor')->get();
        $methods = PaymentMethod::orderBy('id')->get()->keyBy('code');
        $payments = SubscriptionPaymentRequest::where('tenant_id', $tenant->id)
            ->with(['plan:id,name', 'reviewer:id,name', 'billingInvoice:id,payment_request_id,number,status'])->latest('id')->limit(15)->get();
        $canSubmit = app(\App\Services\TenantAuthorization::class)
            ->allows(auth()->user(), $tenant, 'settings.manage');
        $pendingPayment = $payments->firstWhere('status', 'pending');

        return view('business.subscription.index', compact(
            'tenant', 'modules', 'allowed', 'plans', 'methods', 'payments', 'canSubmit', 'pendingPayment'
        ));
    }
}
