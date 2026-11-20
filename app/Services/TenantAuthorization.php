<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantAuthorization
{
    public function allows(User $user, Tenant $tenant, string $permission): bool
    {
        $membership = $tenant->users()->whereKey($user->id)
            ->wherePivot('status', 'active')->first();

        if (! $membership) {
            return false;
        }

        if ($membership->pivot->is_owner) {
            return true;
        }

        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('role_user.tenant_id', $tenant->id)
            ->where('role_user.user_id', $user->id)
            ->where('roles.tenant_id', $tenant->id)
            ->where('permissions.key', $permission)
            ->exists();
    }
}
