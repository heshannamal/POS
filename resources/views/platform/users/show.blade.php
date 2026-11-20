@extends('layouts.app')
@section('title', 'User: '.$user->name)
@section('header', 'Account details')
@section('content')
<div class="page-head">
    <div><span class="overline">USER PROFILE</span><h1>{{ $user->name }}</h1><p class="muted">{{ $user->email }} · Account #{{ str_pad($user->id,6,'0',STR_PAD_LEFT) }}</p></div>
    <a class="btn btn-outline" href="{{ route('platform.users.index') }}">← User directory</a>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">▦</span><span class="stat-label">Business memberships</span><strong>{{ $user->tenants->count() }}</strong><small>Linked workspaces</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Email status</span><strong class="stat-text">{{ $user->hasVerifiedEmail() ? 'Verified' : 'Pending' }}</strong><small>{{ $user->email_verified_at?->format('d M Y') ?? 'Awaiting verification' }}</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">♙</span><span class="stat-label">Platform role</span><strong class="stat-text">{{ $user->platformRole() ? ucwords(str_replace('_',' ',$user->platformRole())) : 'Customer' }}</strong><small>{{ $user->is_platform_admin ? 'Platform administration' : 'No administrative access' }}</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">◷</span><span class="stat-label">Created</span><strong class="stat-text">{{ $user->created_at->format('d M Y') }}</strong><small>Registered account</small></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Business memberships</h2><p class="muted">Roles and tenant-specific access</p></div></div>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Business</th><th>Membership</th><th>Role</th><th>Plan</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($user->tenants as $tenant)
                    <tr>
                        <td><strong>{{ $tenant->name }}</strong><small class="table-subtext">{{ $tenant->slug }}</small></td>
                        <td>{{ $tenant->pivot->is_owner ? 'Business owner' : 'Employee' }}</td>
                        <td>{{ $roles[$tenant->id] ?? null ? $roles[$tenant->id]->pluck('name')->implode(', ') : 'Not assigned' }}</td>
                        <td>{{ $tenant->subscription?->plan?->name ?? '—' }}</td>
                        <td><span class="status status-{{ $tenant->pivot->status === 'active' ? 'active' : 'suspended' }}">{{ ucfirst($tenant->pivot->status) }}</span></td>
                        <td><a class="text-link" href="{{ route('platform.tenants.show',$tenant) }}">Open →</a></td>
                    </tr>
                @empty <tr><td colspan="6" class="empty-row">This user has not joined a business yet.</td></tr>@endforelse
            </tbody>
        </table></div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Recent user activity</h2><p class="muted">Audited events by this account</p></div></div>
        <div class="stack-list">
            @forelse($activity as $event)
                <div class="stack-row"><span class="circle-initial">◷</span><span class="stack-grow"><strong>{{ $event->action }}</strong><small>{{ $event->created_at?->format('d M Y H:i') }}</small></span></div>
            @empty <p class="empty-box muted">No audited activity.</p>@endforelse
        </div>
    </section>
</div>
@endsection
