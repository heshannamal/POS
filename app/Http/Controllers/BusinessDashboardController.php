<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\PlatformAnnouncement;
use Illuminate\View\View;

class BusinessDashboardController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $tenant->load('trial')->loadCount(['branches', 'users']);

        $announcements = PlatformAnnouncement::visible()->latest('id')->limit(3)->get();

        return view('business.dashboard', compact('tenant', 'announcements'));
    }
}
