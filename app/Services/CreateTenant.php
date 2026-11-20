<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Trial;
use App\Models\TrialEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenant
{
    /**
     * Create the real tenant once; upgrading a trial never migrates or deletes its data.
     * Must be called after owner identity has been established.
     *
     * @param array{name:string,contact_email?:?string,phone?:?string,address?:?string} $input
     */
    public function handle(User $owner, array $input, ?User $actor = null): Tenant
    {
        return DB::transaction(function () use ($owner, $input, $actor): Tenant {
            $base = Str::slug($input['name']) ?: 'business';

            do {
                $slug = Str::limit($base, 91, '').'-'.Str::lower(Str::random(8));
            } while (Tenant::where('slug', $slug)->exists());

            $tenant = Tenant::create([
                'name' => $input['name'],
                'slug' => $slug,
                'contact_email' => $input['contact_email'] ?? $owner->email,
                'phone' => $input['phone'] ?? null,
                'address' => $input['address'] ?? null,
                'country' => $input['country'] ?? null,
                'website' => $input['website'] ?? null,
                'registration_number' => $input['registration_number'] ?? null,
                'currency' => PlatformSetting::defaultCurrency(),
                'timezone' => PlatformSetting::defaultTimezone(),
                'status' => 'trial',
                'created_by' => ($actor ?? $owner)->id,
                'onboarding_completed_at' => now(),
                'onboarding_status' => 'completed',
            ]);

            $tenant->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

            Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'address' => $input['address'] ?? null,
                'is_default' => true,
            ]);

            $days = PlatformSetting::trialDays();
            $trial = Trial::create([
                'tenant_id' => $tenant->id,
                'started_at' => now(),
                'ends_at' => now()->addDays($days),
                'duration_days' => $days,
            ]);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => Plan::where('slug', 'starter')->value('id'),
                'status' => 'trialing', 'source' => 'trial', 'started_at' => now(),
            ]);

            TrialEvent::create([
                'trial_id' => $trial->id,
                'actor_id' => ($actor ?? $owner)->id,
                'action' => 'started',
                'new_ends_at' => $trial->ends_at,
            ]);

            $ownerRole = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Owner',
                'slug' => 'owner',
                'is_system' => true,
            ]);
            $managerRole = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Manager', 'slug' => 'manager', 'is_system' => true,
            ]);
            $cashierRole = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Cashier', 'slug' => 'cashier', 'is_system' => true,
            ]);

            foreach ([
                ['key' => 'pos.sales', 'label' => 'Create POS sales', 'group' => 'POS'],
                ['key' => 'inventory.view', 'label' => 'View inventory', 'group' => 'Inventory'],
                ['key' => 'inventory.manage', 'label' => 'Manage inventory', 'group' => 'Inventory'],
                ['key' => 'employees.manage', 'label' => 'Manage employees', 'group' => 'Employees'],
                ['key' => 'reports.view', 'label' => 'View reports', 'group' => 'Reports'],
                ['key' => 'settings.manage', 'label' => 'Manage business settings', 'group' => 'Settings'],
                ['key' => 'employees.view', 'label' => 'View team members', 'group' => 'Employees'],
                ['key' => 'roles.manage', 'label' => 'Manage roles and permissions', 'group' => 'Employees'],
            ] as $permission) {
                $p = Permission::firstOrCreate(['key' => $permission['key']], $permission);
                $ownerRole->permissions()->syncWithoutDetaching([$p->id]);
                if (in_array($p->key, ['pos.sales', 'inventory.view', 'inventory.manage', 'employees.view', 'reports.view'], true)) {
                    $managerRole->permissions()->syncWithoutDetaching([$p->id]);
                }
                if (in_array($p->key, ['pos.sales', 'inventory.view'], true)) {
                    $cashierRole->permissions()->syncWithoutDetaching([$p->id]);
                }
            }

            DB::table('role_user')->insert([
                'role_id' => $ownerRole->id,
                'user_id' => $owner->id,
                'tenant_id' => $tenant->id,
            ]);

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'actor_id' => ($actor ?? $owner)->id,
                'action' => 'tenant.created',
                'subject_type' => Tenant::class,
                'subject_id' => $tenant->id,
                'metadata' => ['owner_id' => $owner->id],
                'ip_address' => request()->ip(),
            ]);

            return $tenant->load(['trial', 'branches']);
        });
    }
}
