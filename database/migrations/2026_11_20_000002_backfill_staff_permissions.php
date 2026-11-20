<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        foreach ([
            ['key' => 'employees.view', 'label' => 'View team members', 'group' => 'Employees'],
            ['key' => 'roles.manage', 'label' => 'Manage roles and permissions', 'group' => 'Employees'],
        ] as $p) {
            DB::table('permissions')->updateOrInsert(['key' => $p['key']], array_merge($p, ['updated_at' => $now, 'created_at' => $now]));
        }
        $managerPermissions = DB::table('permissions')->whereIn('key', ['pos.sales', 'inventory.view', 'inventory.manage', 'employees.view', 'reports.view'])->pluck('id');
        $cashierPermissions = DB::table('permissions')->whereIn('key', ['pos.sales', 'inventory.view'])->pluck('id');
        foreach (DB::table('roles')->whereIn('slug', ['manager', 'cashier'])->get() as $role) {
            $ids = $role->slug === 'manager' ? $managerPermissions : $cashierPermissions;
            foreach ($ids as $id) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $id], []);
            }
        }
    }

    public function down(): void
    {
        // Permission keys are shared among tenants; keep historical records on rollback.
    }
};
