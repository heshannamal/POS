<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PosCheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request,Tenant $tenant,PosCheckoutService $checkout,StoreDataController $dataController)
    {
        $sale=$checkout->checkout($request,$tenant);
        // Keep the cashier's response free of internal unit-cost snapshots.
        return $dataController->sale($request,$tenant,$sale);
    }
}
