@extends('layouts.app')
@section('title','Tenant member: '.$user->name)
@section('header','Tenant member details')
@section('content')
@php
    $canEdit=in_array(auth()->user()->platformRole(),['super_admin','operations'],true);
    $canSecurity=auth()->user()->platformRole()==='super_admin' && auth()->user()->isPermanentPlatformAdmin() && !$user->is_platform_admin && auth()->id()!==$user->id;
@endphp
<div class="page-head">
    <div><span class="overline">BUSINESS USER PROFILE</span><h1>{{ $user->name }}</h1><p class="muted">{{ $user->email }} · {{ $tenant->name }} · {{ $membership->pivot->is_owner ? 'Business owner' : 'Staff member' }}</p></div>
    <a class="btn btn-outline" href="{{ route('platform.tenant-users.index') }}">← All tenant users</a>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">♙</span><span class="stat-label">Business role</span><strong class="stat-text">{{ $membership->pivot->is_owner ? 'Owner' : ($roles->firstWhere('id',$assignedRoleId)?->name ?? 'Unassigned') }}</strong><small>Scoped to {{ $tenant->name }}</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Membership</span><strong class="stat-text">{{ ucfirst($membership->pivot->status) }}</strong><small>Does not affect other businesses</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">▦</span><span class="stat-label">Assigned stores</span><strong>{{ $assignments->count() }}</strong><small>Location assignments</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">◷</span><span class="stat-label">Last sign in</span><strong class="stat-text">{{ $user->last_login_at?->format('d M Y') ?? 'Never recorded' }}</strong><small>{{ $user->last_login_ip ?? 'IP not recorded' }}</small></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Business access & role</h2><p class="muted">Never edit the owner role here. Changes are logged and do not affect the user's other businesses.</p></div></div>
        @if($canEdit && !$membership->pivot->is_owner && !$user->is_platform_admin && $user->id!==auth()->id())
            <form method="POST" action="{{ route('platform.tenant-users.update',[$tenant,$user]) }}" class="form-stack">
                @csrf @method('PATCH')
                <div class="tenant-fields-grid">
                    <div><label for="membership_status">Membership status</label>
                        <select id="membership_status" name="status" required>
                            @foreach(['active'=>'Active','inactive'=>'Inactive','blocked'=>'Blocked','suspended'=>'Suspended (legacy)'] as $status=>$label)
                                <option value="{{ $status }}" @selected($membership->pivot->status===$status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="member_role">Tenant role</label>
                        <select id="member_role" name="role_id" required>
                            @foreach($roles as $role)<option value="{{ $role->id }}" @selected($assignedRoleId===$role->id)>{{ $role->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <label for="member_reason">Reason for the change</label>
                <input id="member_reason" name="reason" minlength="5" maxlength="1000" required placeholder="Reason for this membership update">
                <button class="btn btn-primary" type="submit" data-confirm="Change this person's access to the selected business only?">Save tenant membership →</button>
            </form>
        @else
            <div class="hint-box">This account is protected or you have view-only permissions. Owner and platform administrator accounts cannot be modified here.</div>
        @endif
        <div class="divider-space"></div>
        <h2>Assigned stores</h2>
        <div class="stack-list">
            @forelse($assignments as $store)
                <div class="stack-row"><span class="circle-initial">▦</span><span class="stack-grow"><strong>{{ $store->name }}</strong><small>{{ $store->code }} · {{ ucfirst($store->status) }}</small></span><a class="text-link" href="{{ route('platform.stores.show',$store) }}">Open →</a></div>
            @empty <p class="muted empty-box">No store assignments. Assign this member from the store management screen.</p>@endforelse
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Account identity & security</h2><p class="muted">These values apply to the global user account</p></div></div>
        <dl class="detail-list">
            <div><dt>Verified email</dt><dd>{{ $user->hasVerifiedEmail() ? 'Yes' : 'No' }}</dd></div>
            <div><dt>Last login</dt><dd>{{ $user->last_login_at?->format('d M Y H:i:s') ?? 'Never tracked' }}</dd></div>
            <div><dt>Recorded login IP</dt><dd>{{ $user->last_login_ip ?? 'Not recorded' }}</dd></div>
            <div><dt>Reset required</dt><dd>{{ $user->force_password_reset_at ? 'Yes' : 'No' }}</dd></div>
            <div><dt>Sessions visible</dt><dd>{{ config('session.driver')==='database' ? $activeSessions->count().' recent database sessions' : 'File/other session driver; individual sessions not listed' }}</dd></div>
        </dl>
        @if($canSecurity)
            <div class="hint-box" style="margin-top:17px">Password resets and session revocation affect this user's account across <strong>all businesses</strong>. Confirm that you have authorization to do so.</div>
            <form method="POST" action="{{ route('platform.tenant-users.force-reset',[$tenant,$user]) }}" class="form-stack" style="margin-top:16px">
                @csrf
                <h2>Require password reset</h2>
                <label for="force_reset_reason">Security reason</label>
                <input id="force_reset_reason" name="reason" minlength="10" maxlength="1000" required placeholder="Account security review">
                <label for="force_reset_password">Your Super Admin password</label>
                <input id="force_reset_password" name="admin_password" type="password" autocomplete="current-password" required>
                <button class="btn btn-outline" type="submit" data-confirm="Require global password reset, revoke sessions, and send the user a reset link?">Require secure reset →</button>
            </form>
            <form method="POST" action="{{ route('platform.tenant-users.revoke-sessions',[$tenant,$user]) }}" class="form-stack" style="margin-top:22px">
                @csrf
                <h2>Force logout / revoke sessions</h2>
                <label for="revoke_reason">Reason</label>
                <input id="revoke_reason" name="reason" minlength="10" maxlength="1000" required placeholder="Device lost or access review">
                <label for="revoke_password">Your Super Admin password</label>
                <input id="revoke_password" type="password" name="admin_password" autocomplete="current-password" required>
                <button type="submit" class="btn btn-secondary" data-confirm="Revoke all this user's active sessions on every business?">Revoke all sessions</button>
            </form>
        @endif
    </section>
</div>
<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Recent user activity in {{ $tenant->name }}</h2><p class="muted">Actions recorded by the audit service for this tenant</p></div></div>
        <div class="stack-list">
            @forelse($activity as $event)
                <div class="stack-row"><span class="circle-initial">◷</span><span class="stack-grow"><strong>{{ $event->action }}</strong><small>{{ $event->created_at?->format('d M Y H:i:s') }}</small></span></div>
            @empty <p class="muted empty-box">No recorded business actions yet.</p>@endforelse
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Login history</h2><p class="muted">IP, time, browser/device user agent; geographic location is not inferred</p></div></div>
        <div class="store-audit-feed">
            @forelse($loginEvents as $login)
                <div class="store-login-event">
                    <span class="status status-{{ $login->outcome==='success'?'active':'suspended' }}">{{ ucwords(str_replace('_',' ',$login->outcome)) }}</span>
                    <strong>{{ $login->ip_address ?? 'IP unavailable' }}</strong>
                    <small>{{ $login->created_at?->format('d M Y H:i') }}</small>
                    <small title="{{ $login->user_agent }}">{{ \Illuminate\Support\Str::limit($login->user_agent ?? 'Unknown device',85) }}</small>
                </div>
            @empty <p class="muted empty-box">No login history recorded since tracking was enabled.</p>@endforelse
        </div>
        @if(config('session.driver')==='database')
            <h2 style="margin-top:22px">Recent database sessions</h2>
            <div class="stack-list">
                @forelse($activeSessions as $session)
                    <div class="stack-row"><span class="circle-initial">◷</span><span class="stack-grow"><strong>{{ $session->ip_address ?? 'Unknown IP' }}</strong><small>{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->format('d M Y H:i') }} · {{ \Illuminate\Support\Str::limit($session->user_agent ?? '',45) }}</small></span></div>
                @empty <p class="muted empty-box">No recorded database sessions.</p>@endforelse
            </div>
        @endif
    </section>
</div>
@endsection
