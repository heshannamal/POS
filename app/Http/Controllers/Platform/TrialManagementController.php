<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\Trial;
use App\Models\TrialEvent;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrialManagementController extends Controller
{
    public function index(Request $request): View
    {
        $state = $request->query('state', 'running');
        $query = Tenant::query()->with(['trial', 'subscription.plan'])
            ->whereHas('trial');

        if ($state === 'running') {
            $query->where('status', 'trial')->whereHas('trial',
                fn ($q) => $q->where('is_active', true)->where('ends_at', '>', now()));
        } elseif ($state === 'inactive') {
            $query->where('status', 'trial')->whereHas('trial',
                fn ($q) => $q->where('is_active', false));
        } elseif ($state === 'expired') {
            $query->where('status', 'trial')->whereHas('trial',
                fn ($q) => $q->where('is_active', true)->where('ends_at', '<=', now()));
        } elseif ($state === 'converted') {
            $query->whereHas('trial', fn ($q) => $q->whereNotNull('converted_at'));
        } elseif ($state === 'suspended') {
            $query->where('status', 'suspended');
        } elseif ($state !== 'all') {
            $state = 'running';
            $query->where('status', 'trial')->whereHas('trial',
                fn ($q) => $q->where('is_active', true)->where('ends_at', '>', now()));
        }

        if ($term = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('contact_email', 'like', '%'.$term.'%')
                ->orWhereHas('users', fn ($u) => $u->where('email', 'like', '%'.$term.'%')));
        }

        $tenants = $query->latest()->paginate(15)->withQueryString();
        $defaultDays = PlatformSetting::trialDays();
        $stats = [
            'running' => Tenant::where('status', 'trial')->whereHas('trial',
                fn ($q) => $q->where('is_active', true)->where('ends_at', '>', now()))->count(),
            'inactive' => Tenant::where('status', 'trial')->whereHas('trial',
                fn ($q) => $q->where('is_active', false))->count(),
            'expired' => Tenant::where('status', 'trial')->whereHas('trial',
                fn ($q) => $q->where('is_active', true)->where('ends_at', '<=', now()))->count(),
        ];

        return view('platform.trials.index', compact('tenants', 'defaultDays', 'state', 'stats'));
    }

    public function store(Request $request, CreateTenant $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:191', 'exists:users,email'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'trial_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $owner = User::where('email', $data['owner_email'])->firstOrFail();
        if (! $owner->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'owner_email' => 'The trial owner must verify their account first. Invite them from Customers & Users.',
            ]);
        }

        $tenant = DB::transaction(function () use ($service, $request, $owner, $data): Tenant {
            $tenant = $service->handle($owner, [
                'name' => $data['name'],
                'contact_email' => $data['contact_email'] ?? $owner->email,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ], $request->user());

            $days = (int) $data['trial_days'];
            $trial = Trial::where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            $trial->update([
                'duration_days' => $days,
                'ends_at' => $trial->started_at->copy()->addDays($days),
                'is_active' => true,
            ]);
            // Update the initial started event to reflect the admin-chosen period.
            TrialEvent::where('trial_id', $trial->id)->where('action', 'started')
                ->update(['new_ends_at' => $trial->ends_at]);
            AuditLog::create([
                'tenant_id' => $tenant->id,
                'actor_id' => $request->user()->id,
                'action' => 'tenant.trial.admin_created',
                'subject_type' => Trial::class,
                'subject_id' => $trial->id,
                'metadata' => ['days' => $days, 'owner_id' => $owner->id],
                'ip_address' => $request->ip(),
            ]);

            return $tenant;
        });

        return redirect()->route('platform.trials.index', ['state' => 'running'])
            ->with('status', 'Trial created for '.$tenant->name.'. Owner: '.$owner->email);
    }

    public function setActive(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $tenant, $data): void {
            $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'trial') {
                throw ValidationException::withMessages([
                    'is_active' => 'Only unconverted trial businesses can be activated or deactivated here.',
                ]);
            }

            $trial = Trial::where('tenant_id', $locked->id)->lockForUpdate()->firstOrFail();
            if ($trial->converted_at !== null) {
                throw ValidationException::withMessages([
                    'is_active' => 'This trial has already converted and cannot be toggled.',
                ]);
            }

            $requested = (bool) $data['is_active'];
            if ($trial->is_active === $requested) {
                throw ValidationException::withMessages([
                    'is_active' => 'The trial already has the selected status.',
                ]);
            }

            $before = $trial->is_active;
            $trial->update(['is_active' => $requested]);

            TrialEvent::create([
                'trial_id' => $trial->id,
                'actor_id' => $request->user()->id,
                'action' => $requested ? 'reactivated' : 'deactivated',
                'previous_ends_at' => $trial->ends_at,
                'new_ends_at' => $trial->ends_at,
                'notes' => $data['reason'],
            ]);
            AuditLog::create([
                'tenant_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'action' => $requested ? 'tenant.trial.reactivated' : 'tenant.trial.deactivated',
                'subject_type' => Trial::class,
                'subject_id' => $trial->id,
                'metadata' => [
                    'from' => $before, 'to' => $requested,
                    'reason' => $data['reason'],
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', $data['is_active']
            ? 'Trial reactivated. Its existing expiration date was preserved.'
            : 'Trial deactivated; business data and trial dates are preserved.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['default_days' => ['required', 'integer', 'min:1', 'max:90']]);
        PlatformSetting::updateOrCreate(['key' => 'trial.default_days'], ['value' => (string) $data['default_days']]);
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => 'trial.settings.updated',
            'metadata' => ['default_days' => (int) $data['default_days']],
            'ip_address' => $request->ip(),
        ]);
        return back()->with('status', 'Trial duration updated for newly registered businesses.');
    }
}
