<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Trial;
use App\Models\TrialEvent;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tenant::query()->with('trial')->withCount('users');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('contact_email', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%'));
        }

        if ($status = $request->query('status')) {
            if (in_array($status, ['trial', 'active', 'suspended'], true)) {
                $query->where('status', $status);
            }
        }

        $tenants = $query->latest()->paginate(12)->withQueryString();

        return view('platform.tenants.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('platform.tenants.create');
    }

    public function store(Request $request, CreateTenant $service): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'exists:users,email'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $owner = User::where('email', $input['owner_email'])->firstOrFail();

        if (! $owner->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'owner_email' => 'The owner must verify their account before a business is created.',
            ]);
        }

        $tenant = $service->handle($owner, $input, $request->user());

        return redirect()->route('platform.tenants.show', $tenant)
            ->with('status', 'Business created and trial activated.');
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['trial', 'users', 'branches', 'subscription.plan.modules', 'moduleGrants.module']);
        $plans = Plan::where('is_active', true)->orderBy('name')->get();
        $modules = Module::orderBy('category')->orderBy('name')->get();

        return view('platform.tenants.show', compact('tenant', 'plans', 'modules'));
    }

    public function status(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['trial', 'active', 'suspended'])],
        ]);

        DB::transaction(function () use ($request, $tenant, $data): void {
            $before = $tenant->status;
            $tenant->update(['status' => $data['status']]);

            if ($before === 'trial' && $data['status'] === 'active') {
                $tenant->subscription()->update(['status' => 'active', 'source' => 'manual', 'started_at' => now(), 'ends_at' => null]);
                $tenant->trial()->update(['converted_at' => now()]);
                TrialEvent::create([
                    'trial_id' => $tenant->trial()->value('id'),
                    'actor_id' => $request->user()->id,
                    'action' => 'manually_activated',
                ]);
            }

            if ($data['status'] === 'suspended') {
                $tenant->subscription()->update(['status' => 'paused']);
            } elseif ($data['status'] === 'trial') {
                $tenant->subscription()->update(['status' => 'trialing', 'source' => 'trial']);
            } elseif ($data['status'] === 'active') {
                $tenant->subscription()->update(['status' => 'active', 'source' => 'manual']);
            }

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'actor_id' => $request->user()->id,
                'action' => 'tenant.status.changed',
                'subject_type' => Tenant::class,
                'subject_id' => $tenant->id,
                'metadata' => ['from' => $before, 'to' => $data['status']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Business status updated.');
    }

    public function extendTrial(Request $request, Tenant $tenant): RedirectResponse
    {
        $input = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($tenant->status !== 'trial') {
            throw ValidationException::withMessages([
                'days' => 'Only trial businesses can receive a trial extension.',
            ]);
        }

        DB::transaction(function () use ($request, $tenant, $input): void {
            $trial = Trial::where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            $previous = $trial->ends_at->copy();
            $starts = $previous->isPast() ? now() : $previous;
            $trial->ends_at = $starts->copy()->addDays((int) $input['days']);
            $trial->extension_days += (int) $input['days'];
            $trial->save();

            TrialEvent::create([
                'trial_id' => $trial->id,
                'actor_id' => $request->user()->id,
                'action' => 'extended',
                'previous_ends_at' => $previous,
                'new_ends_at' => $trial->ends_at,
                'notes' => $input['notes'] ?? null,
            ]);

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'actor_id' => $request->user()->id,
                'action' => 'tenant.trial.extended',
                'subject_type' => Trial::class,
                'subject_id' => $trial->id,
                'metadata' => ['days' => (int) $input['days']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Trial extended successfully.');
    }
}
