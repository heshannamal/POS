<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class FeatureAccess
{
    public function allows(Tenant $tenant, string $code): bool
    {
        // Tenant suspensions and expired trials cannot be bypassed by a grant.
        if (! in_array($tenant->effective_status, ['trial', 'active'], true)) {
            return false;
        }

        $module = Module::query()->where('code', $code)->where('is_available', true)->first();
        if (! $module) {
            return false;
        }

        $subscription = $tenant->subscription;
        if (! $subscription || ! in_array($subscription->status, ['trialing', 'active'], true)) {
            return false;
        }
        if ($subscription->status === 'trialing' && $tenant->effective_status !== 'trial') {
            return false;
        }
        if ($subscription->status === 'active' && $tenant->effective_status !== 'active') {
            return false;
        }
        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            return false;
        }

        // Explicit platform overrides win over plan access; expired overrides are ignored.
        $grant = DB::table('tenant_module_grants')
            ->where('tenant_id', $tenant->id)->where('module_id', $module->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
        if ($grant) {
            return (bool) $grant->enabled;
        }

        return DB::table('plan_modules')->where('plan_id', $subscription->plan_id)
            ->where('module_id', $module->id)->exists();
    }
}
