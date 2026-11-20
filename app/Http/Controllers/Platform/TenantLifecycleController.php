<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantLifecycleController extends Controller
{
    public function update(Request $request, Tenant $tenant, TenantLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['deactivate', 'suspend', 'reactivate'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $lifecycle->change($tenant, $request->user(), $data['action'], $data['reason'], $request->ip());

        return back()->with('status', 'Business access updated. Subscription dates and data were preserved.');
    }
}
