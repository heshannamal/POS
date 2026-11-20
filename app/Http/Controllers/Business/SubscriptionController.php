<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Module;
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

        return view('business.subscription.index', compact('tenant', 'modules', 'allowed'));
    }
}
