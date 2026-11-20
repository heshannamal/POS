<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $roles = Role::where('tenant_id', $tenant->id)->with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('label')->get()->groupBy('group');

        return view('business.roles.index', compact('tenant', 'roles', 'permissions'));
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
        $slug = Str::slug($data['name']);
        if (! $slug || Role::where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Please choose a unique role name.']);
        }

        DB::transaction(function () use ($tenant, $data, $slug, $request) {
            $role = Role::create(['tenant_id' => $tenant->id, 'name' => $data['name'], 'slug' => $slug]);
            $role->permissions()->sync($data['permissions'] ?? []);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'role.created', 'subject_type' => Role::class,
                'subject_id' => $role->id, 'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'New role created.');
    }

    public function update(Request $request, Tenant $tenant, Role $role): RedirectResponse
    {
        abort_unless($role->tenant_id === $tenant->id, 404);
        abort_if($role->slug === 'owner', 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        DB::transaction(function () use ($role, $data, $tenant, $request) {
            $role->update(['name' => $data['name']]);
            $role->permissions()->sync($data['permissions'] ?? []);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'role.updated', 'subject_type' => Role::class,
                'subject_id' => $role->id, 'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Role permissions saved.');
    }
}
