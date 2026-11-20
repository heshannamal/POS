<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Trial;
use App\Models\TrialEvent;
use App\Models\User;
use App\Services\CreateTenant;
use App\Services\TenantLifecycle;
use Illuminate\Support\Facades\Storage;
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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['trial', 'trial_inactive', 'expired', 'active', 'inactive', 'suspended'])],
            'plan' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'regex:/^[a-zA-Z]{2}$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['created_at', 'name', 'status', 'country'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([12, 25, 50])],
        ]);

        $query = Tenant::query()
            ->with(['trial', 'subscription.plan'])
            ->withCount(['users', 'branches']);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('contact_email', 'like', '%'.$search.'%')
                ->orWhere('contact_person', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')
                ->orWhereHas('users', fn ($owners) => $owners->where('email', 'like', '%'.$search.'%')));
        }

        if ($status = $filters['status'] ?? null) {
            if ($status === 'trial') {
                $query->where('status', 'trial')->whereHas('trial',
                    fn ($q) => $q->where('is_active', true)->where('ends_at', '>', now()));
            } elseif ($status === 'trial_inactive') {
                $query->where('status', 'trial')->whereHas('trial',
                    fn ($q) => $q->where('is_active', false));
            } elseif ($status === 'expired') {
                $query->where('status', 'trial')->whereHas('trial',
                    fn ($q) => $q->where('is_active', true)->where('ends_at', '<=', now()));
            } else {
                $query->where('status', $status);
            }
        }

        if ($plan = $filters['plan'] ?? null) {
            $query->whereHas('subscription.plan', fn ($q) => $q->where('slug', $plan));
        }
        if ($country = $filters['country'] ?? null) {
            $query->where('country', strtoupper($country));
        }
        if ($from = $filters['from'] ?? null) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $filters['to'] ?? null) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $tenants = $query->orderBy($sort, $direction)->orderBy('id', 'desc')
            ->paginate((int) ($filters['per_page'] ?? 12))->withQueryString();
        $plans = Plan::orderBy('name')->get(['id', 'slug', 'name']);
        $countries = Tenant::whereNotNull('country')->distinct()->orderBy('country')
            ->pluck('country');

        return view('platform.tenants.index', compact('tenants', 'plans', 'countries'));
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
            'contact_person' => ['nullable', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'regex:/^[a-zA-Z]{2}$/'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $owner = User::where('email', $input['owner_email'])->firstOrFail();

        if (! $owner->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'owner_email' => 'The owner must verify their account before a business is created.',
            ]);
        }

        if (! empty($input['country'])) {
            $input['country'] = strtoupper($input['country']);
        }

        $storedLogo = null;
        try {
            $tenant = DB::transaction(function () use ($service, $owner, $input, $request, &$storedLogo): Tenant {
                $tenant = $service->handle($owner, $input, $request->user());
                if ($request->hasFile('logo')) {
                    $storedLogo = $request->file('logo')->store('tenant-logos/'.$tenant->id, 'local');
                    if (! $storedLogo) {
                        throw ValidationException::withMessages(['logo' => 'The logo upload failed.']);
                    }
                    $tenant->update(['logo_path' => $storedLogo]);
                    AuditLog::create([
                        'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                        'action' => 'tenant.logo.updated', 'subject_type' => Tenant::class,
                        'subject_id' => $tenant->id, 'ip_address' => $request->ip(),
                    ]);
                }
                return $tenant;
            });
        } catch (\Throwable $e) {
            if ($storedLogo) {
                Storage::disk('local')->delete($storedLogo);
            }
            throw $e;
        }

        return redirect()->route('platform.tenants.show', $tenant)
            ->with('status', 'Business created and trial activated.');
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['trial', 'users', 'branches', 'subscription.plan.modules', 'moduleGrants.module']);
        $plans = Plan::where('is_active', true)->orderBy('name')->get();
        $modules = Module::orderBy('category')->orderBy('name')->get();

        $activity = AuditLog::where('tenant_id', $tenant->id)->with('actor:id,name')
            ->latest('id')->limit(12)->get();

        $notes = in_array(auth()->user()->platformRole(), ['super_admin', 'operations'], true)
            ? $tenant->notes()->with('author:id,name')->latest('id')->paginate(10, ['*'], 'notes_page')
            : collect();

        return view('platform.tenants.show', compact('tenant', 'plans', 'modules', 'activity', 'notes'));
    }

    public function status(Request $request, Tenant $tenant, TenantLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['trial', 'active', 'suspended', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:status,suspended,inactive'],
        ]);

        $current = $tenant->status;
        $target = $data['status'];
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($target === 'suspended' || $target === 'inactive') {
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages(['reason' => 'Give a reason of at least five characters.']);
            }
            $lifecycle->change(
                $tenant, $request->user(), $target === 'suspended' ? 'suspend' : 'deactivate',
                $reason, $request->ip()
            );
        } elseif (in_array($current, ['suspended', 'inactive'], true)
            && $tenant->lifecycle_previous_status === $target) {
            $lifecycle->change($tenant, $request->user(), 'reactivate',
                $reason ?: 'Restored by platform administrator', $request->ip());
        } elseif ($target === 'active' && $current === 'trial' && $request->user()->platformRole() === 'super_admin') {
            // Backward compatibility for the existing super-admin manual activation;
            // Operations cannot create a paid entitlement.
            DB::transaction(function () use ($tenant, $request): void {
                $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'trial', 409);
                $locked->update(['status' => 'active', 'lifecycle_previous_status' => null]);
                $locked->subscription()->update([
                    'status' => 'active', 'source' => 'manual', 'started_at' => now(), 'ends_at' => null,
                ]);
                $locked->trial()->update(['converted_at' => now()]);
                TrialEvent::create([
                    'trial_id' => $locked->trial()->value('id'),
                    'actor_id' => $request->user()->id,
                    'action' => 'manually_activated',
                ]);
                AuditLog::create([
                    'tenant_id' => $locked->id, 'actor_id' => $request->user()->id,
                    'action' => 'tenant.status.changed',
                    'subject_type' => Tenant::class, 'subject_id' => $locked->id,
                    'metadata' => ['from' => 'trial', 'to' => 'active', 'source' => 'manual'],
                    'ip_address' => $request->ip(),
                ]);
            });
        } else {
            throw ValidationException::withMessages([
                'status' => 'Use Deactivate, Suspend or Restore. Plan activation must go through Billing.',
            ]);
        }

        return back()->with('status', 'Business lifecycle status updated safely.');
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
