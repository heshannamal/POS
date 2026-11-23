@extends('layouts.app')
@section('title','Security center')
@section('header','Platform security')
@section('content')
<div class="page-head">
    <div><span class="overline">PRIVILEGED ACCESS MONITORING</span><h1>Security & login monitoring</h1><p class="muted">Tracked authentication events, unresolved warnings, session records and administrator 2FA enrollment.</p></div>
    <div class="page-actions">
        <a class="btn btn-outline" href="{{ route('platform.audit.index') }}">Audit history →</a>
        @if(auth()->user()->isPermanentPlatformAdmin() && auth()->user()->platformRole()==='super_admin')
            <a class="btn btn-secondary" href="{{ route('platform.security.sessions.index') }}">All sessions →</a>
            <a class="btn btn-secondary" href="{{ route('platform.security.roles.index') }}">Custom roles →</a>
        @endif
    </div>
</div>
<div class="stat-grid">
    <div class="stat-card tint-peach"><span class="stat-icon">!</span><span class="stat-label">Failed logins today</span><strong>{{ number_format($stats['failed_today']) }}</strong><small>Recorded credential failures</small></div>
    <div class="stat-card tint-lavender"><span class="stat-icon">◷</span><span class="stat-label">Unresolved alerts</span><strong>{{ number_format($stats['unresolved_alerts']) }}</strong><small>Require administrator review</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">▣</span><span class="stat-label">Recent non-revoked sessions</span><strong>{{ number_format($stats['active_sessions']) }}</strong><small>Based on last seen timestamp</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">⚿</span><span class="stat-label">Admins without 2FA</span><strong>{{ number_format($stats['unprotected_admins']) }}</strong><small>Permanent accounts not enrolled</small></div>
</div>
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>Login activity</h2><p class="muted">Filter by identity, IP address, result and date range. Passwords are never stored in login history.</p></div></div>
    <form method="GET" action="{{ route('platform.security.index') }}" class="filter-row">
        <input type="search" maxlength="120" name="search" value="{{ request('search') }}" placeholder="Email address or IP">
        <select name="outcome" aria-label="Login outcome">
            <option value="">All outcomes</option>
            @foreach(['success'=>'Successful','failed'=>'Failed','blocked'=>'Blocked','reset_required'=>'Reset required'] as $key=>$value)
                <option value="{{ $key }}" @selected(request('outcome')===$key)>{{ $value }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" aria-label="From date">
        <input type="date" name="to" value="{{ request('to') }}" aria-label="To date">
        <button class="btn btn-secondary">Apply</button><a href="{{ route('platform.security.index') }}" class="btn btn-outline">Clear</a>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>When</th><th>User</th><th>Outcome</th><th>IP</th><th>Device / browser</th></tr></thead>
        <tbody>
        @forelse($logins as $event)
            <tr>
                <td>{{ $event->created_at?->format('d M Y H:i:s') }}</td>
                <td>{{ $event->user?->email ?? 'Unknown user' }}</td>
                <td><span class="status status-{{ $event->outcome==='success'?'active':'suspended' }}">{{ ucfirst(str_replace('_',' ',$event->outcome)) }}</span></td>
                <td>{{ $event->ip_address ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($event->user_agent ?? 'Unknown',90) }}</td>
            </tr>
        @empty <tr><td colspan="5" class="empty-row">No login events match these filters.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $logins->links() }}</div>
</section>
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>Security alerts</h2><p class="muted">Repeated login failures, rejected two-factor challenges and other important authentication signals.</p></div></div>
    <div class="stack-list">
        @forelse($alerts as $alert)
            <div class="stack-row">
                <span class="circle-initial">!</span>
                <span class="stack-grow"><strong>{{ ucwords(str_replace('_',' ',$alert->type)) }}</strong><small>{{ $alert->user?->email ?? 'Unknown account' }} · {{ $alert->ip_address }} · {{ $alert->created_at?->format('d M Y H:i') }}</small></span>
                <span class="status status-{{ $alert->resolved_at?'active':'suspended' }}">{{ $alert->resolved_at?'Resolved':'Open' }}</span>
                @if(!$alert->resolved_at && auth()->user()->isPermanentPlatformAdmin() && auth()->user()->platformRole()==='super_admin')
                    <form method="POST" action="{{ route('platform.security.alerts.resolve',$alert) }}" class="security-alert-action">@csrf
                        <input name="reason" required minlength="5" maxlength="1000" placeholder="Review outcome" aria-label="Resolution reason">
                        <button class="btn btn-small btn-secondary" type="submit">Resolve</button>
                    </form>
                @endif
            </div>
        @empty <p class="muted empty-box">No security alerts recorded.</p>@endforelse
    </div>
</section>
<p class="muted" style="margin-top:14px">IP addresses and user agents indicate observed connections, not verified physical locations. No external IP geolocation or device telemetry is claimed.</p>
@endsection
