@extends('layouts.app')
@section('title','Platform overview')
@section('header','Good to see you, '.auth()->user()->name.' 👋')
@section('content')
<div class="page-head">
    <div><span class="overline">OVERVIEW</span><h1>Platform at a glance</h1><p class="muted">Your businesses and trial activity — all figures are live.</p></div>
    <a href="{{ route('platform.tenants.create') }}" class="btn btn-primary">＋ Add business</a>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">▦</span><span class="stat-label">Total businesses</span><strong>{{ number_format($stats['businesses']) }}</strong><small>All registered tenants</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Active businesses</span><strong>{{ number_format($stats['active']) }}</strong><small>Manually activated accounts</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">◷</span><span class="stat-label">Active trials</span><strong>{{ number_format($stats['trial']) }}</strong><small>Trials currently running</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">!</span><span class="stat-label">Expired trials</span><strong>{{ number_format($stats['expired']) }}</strong><small>Awaiting activation or extension</small></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Recent businesses</h2><p class="muted">New workspaces created on the platform</p></div><a class="text-link" href="{{ route('platform.tenants.index') }}">View all →</a></div>
        <div class="table-scroll">
            <table class="data-table"><thead><tr><th>Business</th><th>Members</th><th>Status</th><th>Created</th><th></th></tr></thead>
                <tbody>
                    @forelse($recent as $tenant)
                        <tr><td><div class="table-identity"><span class="circle-initial">{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}</span><div><strong>{{ $tenant->name }}</strong><small>{{ $tenant->slug }}</small></div></div></td>
                            <td>{{ $tenant->users_count }}</td>
                            <td><span class="status status-{{ $tenant->effective_status }}">{{ ucfirst($tenant->effective_status) }}</span></td>
                            <td>{{ $tenant->created_at->format('d M Y') }}</td>
                            <td><a class="text-link" href="{{ route('platform.tenants.show', $tenant) }}">Details →</a></td></tr>
                    @empty <tr><td colspan="5" class="empty-row">No businesses yet. Create your first workspace.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Trials ending soon</h2><p class="muted">Within the next seven days</p></div></div>
        <div class="stack-list">
            @forelse($expiring as $tenant)
                <a class="stack-row" href="{{ route('platform.tenants.show', $tenant) }}"><span class="circle-initial">{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}</span>
                    <span class="stack-grow"><strong>{{ $tenant->name }}</strong><small>{{ $tenant->trial->ends_at->format('d M Y') }}</small></span>
                    <span class="status status-trial">{{ $tenant->trial->days_remaining }} days</span></a>
            @empty <p class="muted empty-box">No trials are expiring this week.</p> @endforelse
        </div>
    </section>
</div>
<div class="dashboard-grid lower-grid">
    <section class="panel"><div class="panel-heading"><div><h2>Foundation modules</h2><p class="muted">Current implementation</p></div></div>
        <div class="module-list"><span>✓ Tenant isolation and branches</span><span>✓ Authentication and verified email</span><span>✓ Trial account provisioning</span><span>✓ Owner roles and permission schema</span></div>
    </section>
    <section class="panel panel-wide"><div class="panel-heading"><div><h2>Getting started</h2><p class="muted">First platform setup tasks</p></div></div>
        <div class="quick-actions">
            <a href="{{ route('platform.tenants.create') }}" class="quick-action"><span>▦</span><strong>Add a business</strong><small>Provision an owner and trial</small></a>
            <a href="{{ route('platform.tenants.index', ['status' => 'trial']) }}" class="quick-action"><span>◷</span><strong>Manage trials</strong><small>Review, extend and activate</small></a>
            <a href="{{ route('onboarding.create') }}" class="quick-action"><span>＋</span><strong>Create your workspace</strong><small>Try business onboarding</small></a>
        </div>
    </section>
</div>
@endsection
