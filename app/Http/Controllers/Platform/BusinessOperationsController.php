<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class BusinessOperationsController extends Controller
{
    public function profile(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:150'],
            'country' => ['sometimes', 'nullable', 'string', 'regex:/^[a-zA-Z]{2}$/'],
            'website' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', Rule::in(['LKR', 'USD'])],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
        ]);

        if (array_key_exists('country', $data) && $data['country']) {
            $data['country'] = strtoupper($data['country']);
        }

        DB::transaction(function () use ($tenant, $data, $request): void {
            $before = $tenant->only(array_keys($data));
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
    public function uploadLogo(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('logo')->store('tenant-logos/'.$tenant->id, 'local');
        if (! $path) {
            throw ValidationException::withMessages(['logo' => 'The logo upload failed.']);
        }

        try {
            $oldPath = DB::transaction(function () use ($request, $tenant, $path): ?string {
                $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $oldPath = $locked->logo_path;
                $locked->update(['logo_path' => $path]);
                AuditLog::create([
                    'tenant_id' => $locked->id, 'actor_id' => $request->user()->id,
                    'action' => 'tenant.logo.updated',
                    'subject_type' => Tenant::class, 'subject_id' => $locked->id,
                    'metadata' => ['replaced' => $oldPath !== null],
                    'ip_address' => $request->ip(),
                ]);
                return $oldPath;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('status', 'Business logo uploaded.');
    }

    public function deleteLogo(Request $request, Tenant $tenant): RedirectResponse
    {
        $old = DB::transaction(function () use ($request, $tenant): ?string {
            $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $old = $locked->logo_path;
            if (! $old) {
                return null;
            }
            $locked->update(['logo_path' => null]);
            AuditLog::create([
                'tenant_id' => $locked->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.logo.deleted',
                'subject_type' => Tenant::class, 'subject_id' => $locked->id,
                'ip_address' => $request->ip(),
            ]);
            return $old;
        });

        if ($old) {
            Storage::disk('local')->delete($old);
        }
        return back()->with('status', 'Business logo removed.');
    }
}
