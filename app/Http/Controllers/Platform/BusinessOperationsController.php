<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusinessOperationsController extends Controller
{
    public function profile(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', Rule::in(['LKR', 'USD'])],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
        ]);

        DB::transaction(function () use ($tenant, $data, $request): void {
            $before = $tenant->only(['name', 'contact_email', 'phone', 'address', 'currency', 'timezone']);
            $tenant->update($data);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.profile.updated', 'subject_type' => Tenant::class,
                'subject_id' => $tenant->id, 'metadata' => [
                    'changed_fields' => array_keys(array_diff_assoc($data, $before)),
                ], 'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Business profile updated.');
    }

    public function storeBranch(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $tenant, $data): void {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $limit = $tenant->subscription?->plan?->max_branches;
            if ($limit !== null) {
                abort_if($tenant->branches()->where('is_active', true)->count() >= $limit, 422,
                    'The branch limit for this plan has been reached.');
            }

            $code = strtoupper($data['code']);
            abort_if($tenant->branches()->where('code', $code)->exists(), 422, 'Branch code already exists.');

            $branch = $tenant->branches()->create([
                'name' => $data['name'], 'code' => $code,
                'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null,
                'is_default' => false, 'is_active' => true,
            ]);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.branch.created', 'subject_type' => Branch::class,
                'subject_id' => $branch->id, 'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Branch added.');
    }

    public function updateBranch(Request $request, Tenant $tenant, Branch $branch): RedirectResponse
    {
        abort_unless($branch->tenant_id === $tenant->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $tenant, $branch, $data): void {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $branch = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            abort_if(! $data['is_active'] && $branch->is_default, 422,
                'Choose a new default branch before deactivating this one.');
            if (! $branch->is_active && $data['is_active']) {
                $limit = $tenant->subscription?->plan?->max_branches;
                abort_if($limit !== null
                    && $tenant->branches()->where('is_active', true)->count() >= $limit,
                    422, 'The branch limit for this plan has been reached.');
            }
            $branch->update($data);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.branch.updated', 'subject_type' => Branch::class,
                'subject_id' => $branch->id, 'metadata' => ['is_active' => (bool) $data['is_active']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Branch updated.');
    }

    public function setDefaultBranch(Request $request, Tenant $tenant, Branch $branch): RedirectResponse
    {
        abort_unless($branch->tenant_id === $tenant->id, 404);
        DB::transaction(function () use ($request, $tenant, $branch): void {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            abort_unless($branch->fresh()->is_active, 422, 'Inactive branches cannot be default.');
            $tenant->branches()->where('is_default', true)->update(['is_default' => false]);
            $tenant->branches()->whereKey($branch->id)->update(['is_default' => true]);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.branch.default_changed',
                'subject_type' => Branch::class, 'subject_id' => $branch->id,
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Default branch updated.');
    }
}
