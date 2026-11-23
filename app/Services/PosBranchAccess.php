<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PosBranchAccess
{
    public function ids(User $user,Tenant $tenant): array
    {
        if ($user->isOwnerOf($tenant)) {
            return $tenant->branches()->pluck('id')->map(fn($id)=>(int)$id)->all();
        }
        return DB::table('branch_user')->where('tenant_id',$tenant->id)
            ->where('user_id',$user->id)->pluck('branch_id')->map(fn($id)=>(int)$id)->all();
    }

    public function allows(User $user,Tenant $tenant,int $branchId): bool
    {
        return in_array($branchId,$this->ids($user,$tenant),true);
    }

    public function ensure(User $user,Tenant $tenant,int $branchId): void
    {
        abort_unless($this->allows($user,$tenant,$branchId),403,'No access to this store.');
    }
}
