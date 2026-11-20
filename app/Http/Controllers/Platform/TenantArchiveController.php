<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TenantArchiveController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::onlyTrashed()->with('subscription.plan')->withCount(['users', 'branches'])
            ->orderByDesc('deleted_at')->paginate(15);
        return view('platform.tenants.archived', compact('tenants'));
    }

    private function authorizeConfirmation(Request $request, Tenant $tenant): string
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string'],
            'admin_password' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        if ($data['confirmation'] !== $tenant->slug) {
            throw ValidationException::withMessages([
                'confirmation' => 'Enter the exact business slug to confirm this action.',
            ]);
        }
        if (! Hash::check($data['admin_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Your administrator password is incorrect.',
            ]);
        }
        return $data['reason'];
    }

    public function archive(Request $request, Tenant $tenant): RedirectResponse
    {
        $reason = $this->authorizeConfirmation($request, $tenant);

        DB::transaction(function () use ($tenant, $request, $reason): void {
            $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $locked->delete();
            AuditLog::create([
                'tenant_id' => $locked->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.archived',
                'subject_type' => Tenant::class, 'subject_id' => $locked->id,
                'metadata' => ['status_preserved' => $locked->status, 'reason' => $reason],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('platform.tenants.archived')
            ->with('status', 'Business archived without deleting records. It can be restored.');
    }

    public function restore(Request $request, int $tenantId): RedirectResponse
    {
        $tenant = Tenant::onlyTrashed()->findOrFail($tenantId);
        $reason = $this->authorizeConfirmation($request, $tenant);

        DB::transaction(function () use ($tenant, $request, $reason): void {
            $locked = Tenant::withTrashed()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->trashed(), 404);
            $locked->restore();
            AuditLog::create([
                'tenant_id' => $locked->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.restored',
                'subject_type' => Tenant::class, 'subject_id' => $locked->id,
                'metadata' => ['restored_status' => $locked->status, 'reason' => $reason],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('platform.tenants.show', $tenant)
            ->with('status', 'Business restored with its original status, branches and records.');
    }
}
