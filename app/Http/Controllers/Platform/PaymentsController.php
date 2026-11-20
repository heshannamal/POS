<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\SubscriptionPaymentRequest;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function index(): View
    {
        $methods = PaymentMethod::orderBy('id')->get();
        $pendingCount = SubscriptionPaymentRequest::where('status', 'pending')->count();
        $approvedCount = SubscriptionPaymentRequest::where('status', 'approved')->count();
        $recent = SubscriptionPaymentRequest::with(['tenant:id,name,slug', 'plan:id,name'])
            ->latest('id')->limit(6)->get();

        return view('platform.payments.index', compact('methods', 'pendingCount', 'approvedCount', 'recent'));
    }
}
