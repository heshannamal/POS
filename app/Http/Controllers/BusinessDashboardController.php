<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\View\View;

class BusinessDashboardController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $tenant->load('trial')->loadCount(['branches', 'users']);

        return view('business.dashboard', compact('tenant'));
    }
}
