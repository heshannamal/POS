<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Permission;
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
                'currency' => config('saas.default_currency', 'LKR'),
                'timezone' => config('saas.default_timezone', 'Asia/Colombo'),
                'status' => 'trial',
                'created_by' => ($actor ?? $owner)->id,
                'onboarding_completed_at' => now(),
            ]);

            $tenant->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

            Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'address' => $input['address'] ?? null,
                'is_default' => true,
            ]);

            $days = (int) config('saas.default_trial_days', 14);
            $trial = Trial::create([
                'tenant_id' => $tenant->id,
                'started_at' => now(),
                'ends_at' => now()->addDays($days),
                'duration_days' => $days,
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
            Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Manager',
                'slug' => 'manager',
                'is_system' => true,
            ]);
            Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Cashier',
                'slug' => 'cashier',
                'is_system' => true,
            ]);

            foreach ([
                ['key' => 'pos.sales', 'label' => 'Create POS sales', 'group' => 'POS'],
                ['key' => 'inventory.view', 'label' => 'View inventory', 'group' => 'Inventory'],
                ['key' => 'inventory.manage', 'label' => 'Manage inventory', 'group' => 'Inventory'],
                ['key' => 'employees.manage', 'label' => 'Manage employees', 'group' => 'Employees'],
                ['key' => 'reports.view', 'label' => 'View reports', 'group' => 'Reports'],
                ['key' => 'settings.manage', 'label' => 'Manage business settings', 'group' => 'Settings'],
            ] as $permission) {
                $p = Permission::firstOrCreate(['key' => $permission['key']], $permission);
                $ownerRole->permissions()->syncWithoutDetaching([$p->id]);
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
