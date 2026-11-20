<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GlobalTenantUserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'store_id' => ['nullable', 'integer', 'exists:branches,id'],
            'role' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked', 'suspended'])],
            'sort' => ['nullable', Rule::in(['created_at', 'user', 'business'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([12, 25, 50])],
        ]);

        $query = DB::table('tenant_user as membership')
            ->join('users as account', 'account.id', '=', 'membership.user_id')
            ->join('tenants as business', 'business.id', '=', 'membership.tenant_id')
            ->whereNull('business.deleted_at')
            ->select([
                'membership.id', 'membership.user_id', 'membership.tenant_id',
                'membership.is_owner', 'membership.status', 'membership.created_at',
                'account.name as user_name', 'account.email', 'account.last_login_at',
                'account.force_password_reset_at',
                'business.name as business_name', 'business.slug as business_slug',
            ])
            ->selectSub(
                DB::table('role_user as assigned')
                    ->join('roles as role', 'role.id', '=', 'assigned.role_id')
                    ->whereColumn('assigned.user_id', 'membership.user_id')
                    ->whereColumn('assigned.tenant_id', 'membership.tenant_id')
                    ->select('role.name')->limit(1),
                'role_name'
            );

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($q) => $q->where('account.name', 'like', '%'.$search.'%')
                ->orWhere('account.email', 'like', '%'.$search.'%')
                ->orWhere('business.name', 'like', '%'.$search.'%'));
        }
        if ($tenantId = $filters['tenant_id'] ?? null) {
            $query->where('membership.tenant_id', $tenantId);
        }
        if ($storeId = $filters['store_id'] ?? null) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('branch_user as assignment')
                ->whereColumn('assignment.tenant_id', 'membership.tenant_id')
                ->whereColumn('assignment.user_id', 'membership.user_id')
                ->where('assignment.branch_id', $storeId));
        }
        if ($role = $filters['role'] ?? null) {
            if ($role === 'owner') {
                $query->where('membership.is_owner', true);
            } else {
                $query->whereExists(fn ($q) => $q->selectRaw('1')
                    ->from('role_user as assigned_role')
                    ->join('roles as r', 'r.id', '=', 'assigned_role.role_id')
                    ->whereColumn('assigned_role.user_id', 'membership.user_id')
                    ->whereColumn('assigned_role.tenant_id', 'membership.tenant_id')
                    ->where('r.slug', $role));
            }
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('membership.status', $status);
        }

        $sortColumn = match ($filters['sort'] ?? 'created_at') {
            'user' => 'account.name',
            'business' => 'business.name',
            default => 'membership.created_at',
        };
        $users = $query->orderBy($sortColumn, $filters['direction'] ?? 'desc')
            ->orderBy('membership.id', 'desc')
            ->paginate((int) ($filters['per_page'] ?? 12))->withQueryString();

        $tenants = Tenant::orderBy('name')->get(['id', 'name']);
        $stores = Branch::whereHas('tenant')->orderBy('name')->get(['id', 'tenant_id', 'name']);
        $roles = Role::select('slug', 'name')->distinct()->orderBy('name')->get();

        return view('platform.tenant-users.index', compact('users', 'tenants', 'stores', 'roles'));
    }

    public function show(Tenant $tenant, User $user): View
    {
        $membership = $tenant->users()->whereKey($user->id)->firstOrFail();
        $roles = Role::where('tenant_id', $tenant->id)->where('slug', '!=', 'owner')
            ->orderBy('name')->get();
        $assignedRoleId = DB::table('role_user')->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)->value('role_id');
        $stores = $tenant->branches()->orderBy('name')->get();
        $assignments = $user->branches()->wherePivot('tenant_id', $tenant->id)->get();
        $activity = AuditLog::where('tenant_id', $tenant->id)
            ->where('actor_id', $user->id)->latest('id')->limit(20)->get();
        $loginEvents = $user->loginEvents()->latest('id')->limit(12)->get();

        return view('platform.tenant-users.show', compact(
            'tenant', 'user', 'membership', 'roles', 'assignedRoleId',
            'stores', 'assignments', 'activity', 'loginEvents'
        ));
    }

    public function update(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        $member = $tenant->users()->whereKey($user->id)->firstOrFail();
        abort_if($member->pivot->is_owner || $request->user()->id === $user->id
            || $user->is_platform_admin, 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked', 'suspended'])],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')
                ->where(fn ($q) => $q->where('tenant_id', $tenant->id)->where('slug', '!=', 'owner'))],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $tenant, $user, $data): void {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $locked = $tenant->users()->whereKey($user->id)->firstOrFail();
            abort_if($locked->pivot->is_owner, 403);

            $previousRoleId = DB::table('role_user')->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)->value('role_id');
            $tenant->users()->updateExistingPivot($user->id, ['status' => $data['status']]);
            DB::table('role_user')->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)->delete();
            DB::table('role_user')->insert([
                'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $data['role_id'],
            ]);

            if ($data['status'] !== 'active' || Role::whereKey($data['role_id'])->value('slug') !== 'manager') {
                Branch::where('tenant_id', $tenant->id)->where('manager_user_id', $user->id)
                    ->update(['manager_user_id' => null]);
            }

            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.user.updated', 'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => [
                    'previous_status' => $locked->pivot->status, 'status' => $data['status'],
                    'old_role_id' => $previousRoleId, 'role_id' => $data['role_id'],
                    'reason' => $data['reason'],
                ], 'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Business member access updated. Other businesses are unaffected.');
    }

    public function forceReset(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        $membership = $tenant->users()->whereKey($user->id)->firstOrFail();
        abort_if($user->is_platform_admin || $request->user()->id === $user->id, 403);

        $data = $request->validate([
            'admin_password' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        if (! Hash::check($data['admin_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['admin_password' => 'Administrator password is incorrect.']);
        }
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'user' => 'The account must verify its email before a reset link can be delivered.',
            ]);
        }

        DB::transaction(function () use ($user, $tenant, $request, $data): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->is_platform_admin, 403);

            $locked->forceFill([
                'force_password_reset_at' => now(),
                'auth_revoked_at' => now(),
            ])->save();
            $locked->setRememberToken(\Illuminate\Support\Str::random(60));
            $locked->save();

            // Database-driver sessions are revoked immediately. Other drivers
            // are rejected by account.ready on their next request.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $locked->id)->delete();
            }

            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.user.password_reset_required',
                'subject_type' => User::class, 'subject_id' => $locked->id,
                'metadata' => ['reason' => $data['reason']],
                'ip_address' => $request->ip(),
            ]);
        });

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors([
                'user' => 'Password reset is required, but email delivery failed. Configure the mail transport and send a reset link again.',
            ]);
        }
        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors([
                'user' => 'Password reset is required, but the email was not sent (possibly throttled). Retry via Forgot Password.',
            ]);
        }

        return back()->with('status', 'A password-reset email was requested and existing account sessions were revoked.');
    }
}
