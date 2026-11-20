<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantOnboardingController extends Controller
{
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'onboarding_status' => ['required', Rule::in(['incomplete', 'pending', 'verified', 'completed'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $tenant, $data): void {
            $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $before = $locked->onboarding_status;
            $locked->update([
                'onboarding_status' => $data['onboarding_status'],
                'onboarding_completed_at' => $data['onboarding_status'] === 'completed'
                    ? ($locked->onboarding_completed_at ?? now()) : null,
            ]);
            AuditLog::create([
                'tenant_id' => $locked->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.onboarding.changed',
                'subject_type' => Tenant::class, 'subject_id' => $locked->id,
                'metadata' => ['from' => $before, 'to' => $data['onboarding_status'], 'reason' => $data['reason']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Business onboarding progress updated.');
    }
}
