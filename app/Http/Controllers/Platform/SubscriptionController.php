<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantModuleGrant;
use App\Models\TrialEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function activate(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);
        $plan = Plan::findOrFail($data['plan_id']);

        DB::transaction(function () use ($tenant, $plan, $request) {
            $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $before = $tenant->status;
            $subscription = Subscription::where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            $subscription->update([
                'plan_id' => $plan->id, 'status' => 'active', 'source' => 'manual',
                'started_at' => now(), 'ends_at' => null,
            ]);
            $tenant->update(['status' => 'active']);
            if ($before === 'trial' && $tenant->trial) {
                $tenant->trial()->update(['converted_at' => now()]);
                TrialEvent::create([
                    'trial_id' => $tenant->trial->id, 'actor_id' => $request->user()->id,
                    'action' => 'manually_activated',
                ]);
            }
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'subscription.manually_activated',
                'subject_type' => Subscription::class, 'subject_id' => $subscription->id,
                'metadata' => ['plan_id' => $plan->id, 'previous_tenant_status' => $before, 'payment_collected' => false],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Plan activated manually. No payment has been collected.');
    }

    public function grant(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'module_id' => ['required', Rule::exists('modules', 'id')->where('is_available', true)],
            'enabled' => ['required', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        if ($tenant->status === 'suspended') {
            throw ValidationException::withMessages(['module_id' => 'Resume this business before granting access.']);
        }

        DB::transaction(function () use ($request, $tenant, $data) {
            TenantModuleGrant::updateOrCreate([
                'tenant_id' => $tenant->id, 'module_id' => $data['module_id'],
            ], [
                'enabled' => $data['enabled'], 'source' => 'manual',
                'expires_at' => $data['expires_at'] ?? null, 'granted_by' => $request->user()->id,
            ]);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'module.grant.changed',
                'metadata' => ['module_id' => (int) $data['module_id'], 'enabled' => (bool) $data['enabled']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Business module override updated.');
    }

    public function revoke(Request $request, Tenant $tenant, Module $module): RedirectResponse
    {
        DB::transaction(function () use ($request, $tenant, $module) {
            TenantModuleGrant::where('tenant_id', $tenant->id)->where('module_id', $module->id)->delete();
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'module.grant.removed', 'metadata' => ['module_id' => $module->id],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Override removed. The plan determines access again.');
    }
}
