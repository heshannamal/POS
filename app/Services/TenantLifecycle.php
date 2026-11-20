<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantLifecycle
{
    /**
     * A restriction never destroys a trial, cancels a subscription or
     * changes the paid plan/source; restoring does not extend expiration.
     */
    public function change(Tenant $tenant, User $actor, string $action, string $reason, ?string $ip = null): void
    {
        DB::transaction(function () use ($tenant, $actor, $action, $reason, $ip): void {
            $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $before = $locked->status;
            $subscription = $locked->subscription()->lockForUpdate()->first();

            if (in_array($action, ['deactivate', 'suspend'], true)) {
                $target = $action === 'suspend' ? 'suspended' : 'inactive';
                if ($before === $target) {
                    throw ValidationException::withMessages(['action' => 'Business already has this status.']);
                }
                if (! in_array($before, ['trial', 'active', 'inactive', 'suspended'], true)) {
                    throw ValidationException::withMessages(['action' => 'Business cannot be restricted from its current status.']);
                }
                $original = in_array($before, ['trial', 'active'], true)
                    ? $before : $locked->lifecycle_previous_status;

                $locked->forceFill([
                    'status' => $target,
                    'lifecycle_previous_status' => $original,
                ])->save();

                if ($subscription) {
                    $subscription->update(['status' => 'paused']);
                }
            } elseif ($action === 'reactivate') {
                if (! in_array($before, ['inactive', 'suspended'], true)) {
                    throw ValidationException::withMessages(['action' => 'Business is not inactive or suspended.']);
                }
                $original = $locked->lifecycle_previous_status;
                if (! in_array($original, ['trial', 'active'], true)) {
                    throw ValidationException::withMessages([
                        'action' => 'Previous active state is unknown. Review the account before reactivation.',
                    ]);
                }
                if ($original === 'active' && (! $subscription || $subscription->source === 'trial')) {
                    throw ValidationException::withMessages([
                        'action' => 'Active subscription entitlement could not be verified.',
                    ]);
                }
                if ($original === 'trial' && (! $locked->trial || $locked->trial->converted_at)) {
                    throw ValidationException::withMessages([
                        'action' => 'Trial entitlement could not be verified.',
                    ]);
                }

                $locked->forceFill([
                    'status' => $original,
                    'lifecycle_previous_status' => null,
                ])->save();

                if ($subscription) {
                    $subscription->update(['status' => $original === 'trial' ? 'trialing' : 'active']);
                }
            } else {
                throw new \InvalidArgumentException('Unknown lifecycle action.');
            }

            AuditLog::create([
                'tenant_id' => $locked->id,
                'actor_id' => $actor->id,
                'action' => 'tenant.lifecycle.'.$action,
                'subject_type' => Tenant::class,
                'subject_id' => $locked->id,
                'metadata' => [
                    'from' => $before,
                    'to' => $locked->status,
                    'reason' => $reason,
                    'previous_access_state' => $locked->lifecycle_previous_status,
                ],
                'ip_address' => $ip,
            ]);
        }, attempts: 3);
    }
}
