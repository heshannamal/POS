<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GlobalStoreController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'country' => ['nullable', 'regex:/^[A-Za-z]{2}$/'],
            'status' => ['nullable', Rule::in(['active', 'closed', 'suspended'])],
            'sort' => ['nullable', Rule::in(['created_at', 'name', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([12, 25, 50])],
        ]);

        $query = Branch::with(['tenant:id,name,slug,country,status', 'manager:id,name,email'])
            ->withCount(['staff', 'devices'])->whereHas('tenant');

        if ($term = trim((string) ($data['search'] ?? ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', '%'.$term.'%')
                ->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$term.'%'))
                ->orWhereHas('manager', fn ($u) => $u->where('email', 'like', '%'.$term.'%')));
        }
        if ($tenantId = $data['tenant_id'] ?? null) {
            $query->where('tenant_id', $tenantId);
        }
        if ($country = $data['country'] ?? null) {
            $query->whereHas('tenant', fn ($q) => $q->where('country', strtoupper($country)));
        }
        if ($status = $data['status'] ?? null) {
            $query->where('status', $status);
        }

        $stores = $query->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')
            ->orderBy('id', 'desc')->paginate((int) ($data['per_page'] ?? 12))->withQueryString();

        $tenants = Tenant::orderBy('name')->get(['id', 'name']);
        $countries = Tenant::whereNotNull('country')->distinct()->orderBy('country')->pluck('country');
        $stats = [
            'all' => Branch::whereHas('tenant')->count(),
            'active' => Branch::whereHas('tenant')->where('status', 'active')->count(),
            'closed' => Branch::whereHas('tenant')->where('status', 'closed')->count(),
            'suspended' => Branch::whereHas('tenant')->where('status', 'suspended')->count(),
        ];

        return view('platform.stores.index', compact('stores', 'tenants', 'countries', 'stats'));
    }

    public function create(): View
    {
        $tenants = Tenant::with('subscription.plan')
            ->whereIn('status', ['trial', 'active'])
            ->orderBy('name')->get(['id', 'name', 'slug', 'status']);

        return view('platform.stores.create', compact('tenants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['nullable', Rule::in(timezone_identifiers_list())],
        ]);

        $store = DB::transaction(function () use ($request, $data): Branch {
            $tenant = Tenant::whereKey($data['tenant_id'])->lockForUpdate()->firstOrFail();
            if (! in_array($tenant->effective_status, ['active', 'trial'], true)) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'The selected business is restricted or its trial is expired.',
                ]);
            }
            $maxBranches = $tenant->subscription?->plan?->max_branches;
            if ($maxBranches !== null && $tenant->branches()->where('is_active', true)->count() >= $maxBranches) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'The current plan maximum active store count has been reached.',
                ]);
            }

            $code = strtoupper($data['code']);
            if ($tenant->branches()->where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'This store code is already registered within the selected business.',
                ]);
            }

            $branch = Branch::create([
                'tenant_id' => $tenant->id, 'name' => $data['name'],
                'code' => $code, 'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'timezone' => $data['timezone'] ?? null, 'status' => 'active',
                'is_active' => true, 'is_default' => false,
            ]);

            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'store.created', 'subject_type' => Branch::class,
                'subject_id' => $branch->id,
                'metadata' => ['code' => $code],
                'ip_address' => $request->ip(),
            ]);

            return $branch;
        });

        return redirect()->route('platform.stores.show', $store)
            ->with('status', 'Store created within the selected business.');
    }

    public function show(Branch $store): View
    {
        $this->activeTenant($store);
        $store->load([
            'tenant.subscription.plan', 'manager:id,name,email',
            'staff', 'devices',
        ]);

        $eligible = $store->tenant->users()->wherePivot('status', 'active')
            ->orderBy('name')->get();
        $managers = $eligible->filter(fn (User $user) => $this->mayManage($store->tenant, $user));
        $activity = AuditLog::where('tenant_id', $store->tenant_id)
            ->where(fn ($q) => $q->where(fn ($b) => $b
                ->where('subject_type', Branch::class)->where('subject_id', $store->id))
                ->orWhere(fn ($d) => $d->where('subject_type', \App\Models\StoreDevice::class)
                    ->where('metadata->branch_id', $store->id)))
            ->with('actor:id,name')->latest('id')->limit(20)->get();

        return view('platform.stores.show', compact('store', 'eligible', 'managers', 'activity'));
    }

    public function update(Request $request, Branch $store): RedirectResponse
    {
        $this->activeTenant($store);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['nullable', Rule::in(timezone_identifiers_list())],
            'status' => ['required', Rule::in(['active', 'closed', 'suspended'])],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($store, $request, $data): void {
            $tenant = Tenant::whereKey($store->tenant_id)->lockForUpdate()->firstOrFail();
            $branch = Branch::whereKey($store->id)->lockForUpdate()->firstOrFail();

            if ($branch->is_default && $data['status'] !== 'active') {
                throw ValidationException::withMessages([
                    'status' => 'Set another active branch as default before closing or suspending this store.',
                ]);
            }

            if (! $branch->is_active && $data['status'] === 'active') {
                $limit = $tenant->subscription?->plan?->max_branches;
                if ($limit !== null && $tenant->branches()->where('is_active', true)->count() >= $limit) {
                    throw ValidationException::withMessages(['status' => 'The plan maximum active stores limit has been reached.']);
                }
            }

            $manager = null;
            if ($data['manager_user_id'] ?? null) {
                $manager = $tenant->users()->whereKey($data['manager_user_id'])
                    ->wherePivot('status', 'active')->first();
                if (! $manager || ! $this->mayManage($tenant, $manager)) {
                    throw ValidationException::withMessages([
                        'manager_user_id' => 'Manager must be an active owner or manager within this business.',
                    ]);
                }
            }

            $before = $branch->only(['name', 'phone', 'address', 'timezone', 'status', 'manager_user_id']);
            $branch->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'status' => $data['status'],
                'is_active' => $data['status'] === 'active',
                'manager_user_id' => $manager?->id,
            ]);

            if ($manager && ! $branch->staff()->whereKey($manager->id)->exists()) {
                $branch->staff()->attach($manager->id, [
                    'tenant_id' => $tenant->id,
                    'assigned_by' => $request->user()->id,
                ]);
            }

            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'store.updated', 'subject_type' => Branch::class,
                'subject_id' => $branch->id,
                'metadata' => [
                    'before' => $before, 'status' => $branch->status,
                    'manager_user_id' => $manager?->id, 'reason' => $data['reason'],
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Store details, status and manager saved.');
    }

    public function settings(Request $request, Branch $store): RedirectResponse
    {
        $this->activeTenant($store);
        $data = $request->validate([
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'print_receipts' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($store, $request, $data): void {
            Tenant::whereKey($store->tenant_id)->lockForUpdate()->firstOrFail();
            $branch = Branch::whereKey($store->id)->lockForUpdate()->firstOrFail();
            $settings = [
                'receipt_footer' => $data['receipt_footer'] ?? null,
                'low_stock_threshold' => isset($data['low_stock_threshold']) ? (int) $data['low_stock_threshold'] : null,
                'print_receipts' => (bool) $data['print_receipts'],
            ];
            $branch->update(['settings_overrides' => $settings]);
            AuditLog::create([
                'tenant_id' => $branch->tenant_id, 'actor_id' => $request->user()->id,
                'action' => 'store.settings.updated', 'subject_type' => Branch::class,
                'subject_id' => $branch->id,
                'metadata' => ['keys' => array_keys($settings)],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Store overrides saved for future POS module use.');
    }

    public function assign(Request $request, Branch $store): RedirectResponse
    {
        $this->activeTenant($store);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        DB::transaction(function () use ($store, $request, $data): void {
            $tenant = Tenant::whereKey($store->tenant_id)->lockForUpdate()->firstOrFail();
            $branch = Branch::whereKey($store->id)->lockForUpdate()->firstOrFail();
            if (! $branch->is_active) {
                throw ValidationException::withMessages(['user_id' => 'Cannot assign staff to a closed or suspended store.']);
            }
            $member = $tenant->users()->whereKey($data['user_id'])
                ->wherePivot('status', 'active')->first();
            if (! $member) {
                throw ValidationException::withMessages(['user_id' => 'Choose an active user from this business.']);
            }
            if ($branch->staff()->whereKey($member->id)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'This person is already assigned to this store.']);
            }
            $branch->staff()->attach($member->id, [
                'tenant_id' => $tenant->id, 'assigned_by' => $request->user()->id,
            ]);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'store.staff.assigned', 'subject_type' => Branch::class,
                'subject_id' => $branch->id,
                'metadata' => ['user_id' => $member->id], 'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Staff member assigned to this store.');
    }

    public function unassign(Request $request, Branch $store, User $user): RedirectResponse
    {
        $this->activeTenant($store);

        DB::transaction(function () use ($store, $user, $request): void {
            $branch = Branch::whereKey($store->id)->lockForUpdate()->firstOrFail();
            abort_unless($branch->staff()->whereKey($user->id)->exists(), 404);
            if ($branch->manager_user_id === $user->id) {
                throw ValidationException::withMessages([
                    'user_id' => 'Assign a different store manager before unassigning the current manager.',
                ]);
            }
            $branch->staff()->detach($user->id);
            AuditLog::create([
                'tenant_id' => $branch->tenant_id, 'actor_id' => $request->user()->id,
                'action' => 'store.staff.unassigned', 'subject_type' => Branch::class,
                'subject_id' => $branch->id,
                'metadata' => ['user_id' => $user->id], 'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Store assignment removed. Other business memberships are unchanged.');
    }

    private function activeTenant(Branch $store): Tenant
    {
        return Tenant::findOrFail($store->tenant_id);
    }

    private function mayManage(Tenant $tenant, User $user): bool
    {
        if ($user->pivot?->is_owner) {
            return true;
        }

        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.tenant_id', $tenant->id)
            ->where('role_user.user_id', $user->id)
            ->where('roles.tenant_id', $tenant->id)
            ->where('roles.slug', 'manager')
            ->exists();
    }
}
