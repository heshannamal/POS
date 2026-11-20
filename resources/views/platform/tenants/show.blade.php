@extends('layouts.app')
@section('title',$tenant->name)
@section('header',$tenant->name)
@section('content')
@php
    $role = auth()->user()->platformRole();
    $canOperate = in_array($role, ['super_admin','operations'], true);
    $canBill = in_array($role, ['super_admin','billing'], true);
@endphp
<div class="page-head">
    <div class="tenant-profile-head">
        @if($tenant->logo_path)<img src="{{ route('platform.tenants.logo',$tenant) }}" class="tenant-logo-header" alt="{{ $tenant->name }} logo">@else<span class="circle-initial tenant-logo-placeholder">{{ mb_strtoupper(mb_substr($tenant->name,0,1)) }}</span>@endif
        <div><span class="overline">BUSINESS DETAILS</span><h1>{{ $tenant->name }}</h1><p class="muted">{{ $tenant->slug }} · Created {{ $tenant->created_at->format('d M Y') }}</p></div>
    </div>
    <div class="page-actions"><span class="status status-{{ $tenant->effective_status }}">{{ ucfirst($tenant->effective_status) }}</span>@if($canOperate)<a class="btn btn-outline" href="{{ route('platform.tenants.index') }}">← All businesses</a>@elseif($canBill)<a class="btn btn-outline" href="{{ route('platform.subscriptions.index') }}">← Subscriptions</a>@else<a class="btn btn-outline" href="{{ route('platform.support.index') }}">← Support</a>@endif</div>
</div>
<div class="detail-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Business profile</h2><p class="muted">Core tenant information</p></div></div>
        <dl class="detail-list">
            <div><dt>Contact email</dt><dd>{{ $tenant->contact_email ?? '—' }}</dd></div>
            <div><dt>Contact person</dt><dd>{{ $tenant->contact_person ?? '—' }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $tenant->phone ?? '—' }}</dd></div>
            <div><dt>Country</dt><dd>{{ $tenant->country ?? '—' }}</dd></div>
            <div><dt>Registration no.</dt><dd>{{ $tenant->registration_number ?? '—' }}</dd></div>
            <div><dt>Website</dt><dd>@if($tenant->website)<a href="{{ $tenant->website }}" target="_blank" rel="noopener noreferrer" class="text-link">Visit website</a>@else — @endif</dd></div>
            <div><dt>Address</dt><dd>{{ $tenant->address ?? '—' }}</dd></div>
            <div><dt>Currency</dt><dd>{{ $tenant->currency }}</dd></div>
            <div><dt>Timezone</dt><dd>{{ $tenant->timezone }}</dd></div>
        </dl>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Trial management</h2><p class="muted">Trial data remains when the business upgrades</p></div></div>
        @if($tenant->trial)
            <div class="trial-highlight">
                <span>Trial remaining</span><strong>{{ $tenant->trial->days_remaining }} days</strong>
                <small>{{ $tenant->trial->started_at->format('d M Y') }} — {{ $tenant->trial->ends_at->format('d M Y') }}</small>
            </div>
            @if($tenant->status === 'trial' && $canOperate)
                <span class="status status-{{ $tenant->trial->is_active ? 'active' : 'suspended' }}">{{ $tenant->trial->is_active ? 'Trial active' : 'Trial inactive' }}</span>
                <form method="POST" action="{{ route('platform.tenants.trial.extend', $tenant) }}" class="form-stack">@csrf
                    <label for="days">Extend trial (days)</label>
                    <input id="days" type="number" name="days" min="1" max="90" value="7" required>
                    <label for="notes">Internal reason (optional)</label>
                    <input id="notes" type="text" name="notes" maxlength="1000" placeholder="Reason for extension">
                    <button class="btn btn-secondary" type="submit">Extend trial</button>
                </form>
                <form method="POST" action="{{ route('platform.trials.active', $tenant) }}" class="form-stack" style="margin-top:14px">
                    @csrf @method('PATCH')
                    <input type="hidden" name="is_active" value="{{ $tenant->trial->is_active ? '0' : '1' }}">
                    <label for="trial_active_reason">Reason for {{ $tenant->trial->is_active ? 'deactivation' : 'reactivation' }}</label>
                    <input id="trial_active_reason" name="reason" type="text" minlength="5" maxlength="1000" required placeholder="Internal reason">
                    <button class="btn btn-outline" type="submit"
                        data-confirm="{{ $tenant->trial->is_active ? 'Deactivate trial feature access without deleting business data?' : 'Reactivate trial without changing the current expiry date?' }}">
                        {{ $tenant->trial->is_active ? 'Deactivate trial' : 'Reactivate trial' }}
                    </button>
                </form>
            @endif
        @endif
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Members</h2><p class="muted">Users assigned to this business</p></div></div>
        <div class="stack-list">
            @foreach($tenant->users as $member)
                <div class="stack-row"><span class="circle-initial">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span><span class="stack-grow"><strong>{{ $member->name }}</strong><small>{{ $member->email }}</small></span><span class="status status-active">{{ $member->pivot->is_owner ? 'Owner' : ucfirst($member->pivot->status) }}</span></div>
            @endforeach
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Branches</h2><p class="muted">Isolated business locations</p></div></div>
        <div class="stack-list">
            @foreach($tenant->branches as $branch)
                <div class="stack-row"><span class="circle-initial">▦</span><span class="stack-grow"><strong>{{ $branch->name }}</strong><small>{{ $branch->code }} · {{ $branch->address ?? 'No address' }}</small></span>@if($branch->is_default)<span class="status status-trial">Main</span>@endif</div>
            @endforeach
        </div>
    </section>
    @if($canOperate)
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Business access & lifecycle</h2><p class="muted">Temporarily deactivate, suspend or restore access. Plans and subscription dates are preserved.</p></div></div>
        <div class="tenant-lifecycle-summary">
            <span class="status status-{{ $tenant->effective_status }}">{{ ucfirst($tenant->effective_status) }}</span>
            @if($tenant->lifecycle_previous_status)<span class="muted">Previous access: {{ ucfirst($tenant->lifecycle_previous_status) }}</span>@endif
            <span class="muted">This is different from deactivating a trial or editing a paid subscription.</span>
        </div>
        <form method="POST" action="{{ route('platform.tenants.lifecycle.update',$tenant) }}" class="form-stack">
            @csrf @method('PATCH')
            <label for="lifecycle_action">Access action</label>
            <select name="action" id="lifecycle_action" required>
                @if(in_array($tenant->status,['active','trial','suspended']))
                    <option value="deactivate">Deactivate business access</option>
                @endif
                @if(in_array($tenant->status,['active','trial','inactive']))
                    <option value="suspend">Suspend business</option>
                @endif
                @if(in_array($tenant->status,['inactive','suspended']))
                    <option value="reactivate">Restore previous access state</option>
                @endif
            </select>
            <label for="lifecycle_reason">Internal reason</label>
            <input name="reason" id="lifecycle_reason" minlength="5" maxlength="1000" required placeholder="Reason for this status change">
            <button class="btn btn-secondary" type="submit" data-confirm="Change business access, preserving its existing subscription and data?">Apply access change</button>
        </form>
        @if($role === 'super_admin' && $tenant->status === 'trial')
            <div class="hint-box" style="margin-top:14px">To grant a permanent/manual subscription, use Plan & Manual Activation below. Access restoration alone never creates a paid entitlement.</div>
        @endif
    </section>
    @endif
</div>
@if($canBill)
<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Plan & manual activation</h2><p class="muted">Licensing controls — assigning a plan does not collect money.</p></div></div>
        <div class="trial-highlight">
            <span>Current assignment</span><strong>{{ $tenant->subscription?->plan?->name ?? 'None' }}</strong>
            <small>Status: {{ $tenant->subscription?->status ?? 'unassigned' }} · Source: {{ $tenant->subscription?->source ?? '—' }}</small>
        </div>
        <form method="POST" action="{{ route('platform.tenants.plan.activate', $tenant) }}" class="form-stack">@csrf
            <label for="activation_plan">Select plan</label>
            <select name="plan_id" id="activation_plan" required>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected($tenant->subscription?->plan_id == $plan->id)>{{ $plan->name }} — {{ $plan->currency }} {{ number_format($plan->priceForCycleMinor('monthly') / 100, 2) }} / month{{ $plan->discountIsActive() ? ' ('.$plan->discount_percentage.'% off)' : '' }}</option>
                @endforeach
            </select>
            <div class="hint-box">Activating a plan is a manual administrative grant. No payment verification, subscription invoice or money collection takes place.</div>
            <button class="btn btn-primary" type="submit" data-confirm="Activate this plan manually without collecting payment?">Activate plan manually</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Feature overrides</h2><p class="muted">Only released modules can be enabled.</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.modules.grant', $tenant) }}" class="form-stack">@csrf @method('PUT')
            <label for="module_id">Released module</label>
            <select name="module_id" id="module_id" required>
                @forelse($modules->where('is_available', true) as $module)
                    <option value="{{ $module->id }}">{{ $module->name }}</option>
                @empty <option value="">No released modules</option> @endforelse
            </select>
            <label for="enabled">Override action</label>
            <select name="enabled" id="enabled"><option value="1">Enable</option><option value="0">Disable</option></select>
            <label for="expires_at">Expires at (optional)</label>
            <input type="datetime-local" name="expires_at" id="expires_at">
            <button class="btn btn-secondary" type="submit">Save module override</button>
        </form>
        <div class="stack-list">
            @foreach($tenant->moduleGrants as $grant)
                <div class="stack-row">
                    <span class="stack-grow"><strong>{{ $grant->module?->name }}</strong><small>{{ $grant->enabled ? 'Enabled' : 'Disabled' }} · {{ $grant->expires_at?->format('d M Y') ?? 'No expiry' }}</small></span>
                    <form method="POST" action="{{ route('platform.tenants.modules.revoke', [$tenant, $grant->module]) }}">@csrf @method('DELETE')
                        <button class="btn btn-small btn-outline" type="submit">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endif
@if($canOperate)
    @include('platform.tenants.partials.operations')
@endif
@if(auth()->user()->isPermanentPlatformAdmin() && $role === 'super_admin')
<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Tenant data export</h2><p class="muted">Download a portable JSON snapshot of tenant business records. Requires your current administrator password.</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.export',$tenant) }}" class="form-stack">
            @csrf
            <label for="export_password">Confirm permanent Super Admin password</label>
            <input id="export_password" name="admin_password" type="password" autocomplete="current-password" required>
            <button class="btn btn-outline" type="submit">Export tenant JSON ↓</button>
        </form>
        <p class="muted">Internal notes, administrator secrets and private uploaded payment files are excluded. Export downloads are audited.</p>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Archive business</h2><p class="muted">Reversible soft-delete. Normal tenant routes will stop resolving the workspace.</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.archive',$tenant) }}" class="form-stack">
            @csrf
            <label for="archive_confirmation">Type the exact business slug to confirm</label>
            <input id="archive_confirmation" name="confirmation" required placeholder="{{ $tenant->slug }}" autocomplete="off">
            <label for="archive_reason">Reason for archiving (at least 10 characters)</label>
            <input id="archive_reason" name="reason" required minlength="10" maxlength="1000" placeholder="Requested by owner / business closure">
            <label for="archive_admin_password">Permanent Super Admin password</label>
            <input id="archive_admin_password" type="password" name="admin_password" autocomplete="current-password" required>
            <button type="submit" class="btn btn-outline" data-confirm="Archive this business? All normal tenant routes will become unavailable until restored.">Archive business</button>
        </form>
        <p class="muted">No permanent delete is available here. Restore from Archived Businesses.</p>
    </section>
</div>
@endif
<section class="panel" style="margin-top:16px">
    <div class="panel-heading"><div><h2>Recent activity</h2><p class="muted">Latest audited events for this business</p></div></div>
    <div class="stack-list">
        @forelse($activity as $event)
            <div class="stack-row"><span class="circle-initial">◷</span><span class="stack-grow"><strong>{{ $event->action }}</strong><small>{{ $event->actor?->name ?? 'System' }} · {{ $event->created_at?->format('d M Y H:i') }}</small></span></div>
        @empty <p class="muted empty-box">No recent audit events for this business.</p> @endforelse
    </div>
</section>
@endsection
