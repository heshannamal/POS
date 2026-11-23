<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PosCheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request,Tenant $tenant,PosCheckoutService $checkout)
    {
        $sale=$checkout->checkout($request,$tenant);
        $sale->load('items','payments');
        return response()->json(['data'=>$sale,'message'=>'Sale completed.'],200);
    }
}
