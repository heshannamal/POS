<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformRole;
use App\Services\PlatformAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformRoleController extends Controller
{
    public function index(): View
    {
        $roles = PlatformRole::orderBy('name')->get();
        $scopes = PlatformAccess::SCOPES;
        $permissions = PlatformAccess::PERMISSIONS;
        return view('platform.security.roles', compact('roles', 'scopes', 'permissions'));
    }

    private function input(Request $request, ?PlatformRole $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('platform_roles', 'slug')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(array_keys(PlatformAccess::SCOPES)), 'distinct'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(array_keys(PlatformAccess::PERMISSIONS)), 'distinct'],
            'is_active' => ['required', 'boolean'],
            'admin_password' => ['required', 'string'],
        ]);
        if (! Hash::check($data['admin_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['admin_password' => 'Administrator password is incorrect.']);
        }
        $allowedByScope = [
            'operations' => ['dashboard.view', 'tenants.view', 'tenants.manage', 'stores.view', 'stores.manage',
                'users.view', 'users.manage', 'trials.view', 'trials.manage', 'reports.view', 'support.view', 'support.manage', 'notifications.view'],
            'billing' => ['dashboard.view', 'tenants.view', 'billing.view', 'billing.manage', 'reports.view', 'notifications.view'],
            'support' => ['dashboard.view', 'tenants.view', 'stores.view', 'users.view', 'support.view', 'support.manage', 'notifications.view'],
            'auditor' => ['dashboard.view', 'reports.view', 'audit.view', 'audit.export', 'security.view', 'notifications.view'],
        ];
        $allowed = [];
        foreach ($data['scopes'] as $scope) {
            $allowed = array_merge($allowed, $allowedByScope[$scope] ?? []);
        }
        if (array_diff($data['permissions'], $allowed)) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more permissions do not match the selected scope(s).',
            ]);
        }
        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->input($request);

        DB::transaction(function () use ($request, $data): void {
            $role = PlatformRole::create([
                ...collect($data)->except('admin_password')->toArray(),
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'platform.custom_role.created',
                'subject_type' => PlatformRole::class, 'subject_id' => $role->id,
                'metadata' => ['scopes' => $role->scopes, 'permissions' => $role->permissions],
                'ip_address' => $request->ip(),
            ]);
        });
        return back()->with('status', 'Granular role created. Assign it to a verified platform administrator.');
    }

    public function update(Request $request, PlatformRole $role): RedirectResponse
    {
        $data = $this->input($request, $role);
        DB::transaction(function () use ($request, $role, $data): void {
            $locked = PlatformRole::whereKey($role->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['scopes', 'permissions', 'is_active']);
            $locked->update(collect($data)->except('admin_password')->toArray());
            // Changes should invalidate existing sessions of affected custom administrators.
            \App\Models\User::where('is_platform_admin', true)
                ->where('platform_role', 'custom_'.$locked->id)
                ->increment('auth_session_version');
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'platform.custom_role.updated',
                'subject_type' => PlatformRole::class, 'subject_id' => $locked->id,
                'metadata' => ['before' => $before, 'after' => $locked->only(['scopes', 'permissions', 'is_active'])],
                'ip_address' => $request->ip(),
            ]);
        });
        return back()->with('status', 'Role updated; affected administrator sessions must sign in again.');
    }
}
