@extends('layouts.app')
@section('title', 'Trial management')
@section('header', 'Trial management')
@section('content')
<div class="page-head">
    <div><span class="overline">TRIAL LIFECYCLE</span><h1>Trials & conversions</h1><p class="muted">Create trial accounts, pause and resume trials, or extend available and expired trials without deleting business data.</p></div>
    <a class="btn btn-primary" href="{{ route('platform.trials.create') }}">＋ Add trial user</a>
</div>
<div class="stat-grid">
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Running trials</span><strong>{{ $stats['running'] }}</strong><small>Active and not expired</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">◷</span><span class="stat-label">Inactive trials</span><strong>{{ $stats['inactive'] }}</strong><small>Feature access paused</small></div>
    <div class="stat-card tint-lavender"><span class="stat-icon">×</span><span class="stat-label">Expired trials</span><strong>{{ $stats['expired'] }}</strong><small>Eligible for extension</small></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Business trials</h2><p class="muted">Extend or toggle trials directly here. Payment-converted accounts cannot be changed to free trials.</p></div></div>
        <div class="tab-row">
            @foreach(['running' => 'Running', 'inactive' => 'Inactive', 'expired' => 'Expired', 'converted' => 'Converted', 'suspended' => 'Suspended', 'all' => 'All'] as $value => $label)
                <a class="tab-pill {{ $state === $value ? 'is-active' : '' }}" href="{{ route('platform.trials.index', ['state' => $value]) }}">{{ $label }}</a>
            @endforeach
        </div>
        <form action="{{ route('platform.trials.index') }}" method="GET" class="filter-row">
            <input type="hidden" name="state" value="{{ $state }}">
            <input name="search" type="search" placeholder="Search business or contact..." value="{{ request('search') }}">
            <button class="btn btn-outline" type="submit">Search</button>
        </form>
        <div class="table-scroll">
            <table class="data-table"><thead><tr><th>Business / User</th><th>Plan</th><th>Started</th><th>Expires</th><th>Trial status</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr>
                            <td><strong>{{ $tenant->name }}</strong><small class="table-subtext">{{ $tenant->contact_email }}</small></td>
                            <td>{{ $tenant->subscription?->plan?->name ?? '—' }}</td>
                            <td>{{ $tenant->trial?->started_at?->format('d M Y') }}</td>
                            <td>{{ $tenant->trial?->ends_at?->format('d M Y') }}</td>
                            <td><span class="status status-{{ $tenant->effective_status }}">{{ $tenant->effective_status === 'trial' ? $tenant->trial->days_remaining.' days left' : ucfirst($tenant->effective_status) }}</span></td>
                            <td>
                                <div class="trial-action-stack">
                                    <a class="btn btn-small btn-outline" href="{{ route('platform.tenants.show', $tenant) }}">Details →</a>
                                    @if($tenant->status === 'trial' && !$tenant->trial?->converted_at)
                                        <form method="POST" action="{{ route('platform.tenants.trial.extend', $tenant) }}" class="trial-inline-form">
                                            @csrf
                                            <input name="days" type="number" min="1" max="90" value="7" required aria-label="Days to extend {{ $tenant->name }}">
                                            <input type="hidden" name="notes" value="Extended from trial management">
                                            <button type="submit" class="btn btn-small btn-secondary" data-confirm="Extend this business trial by the entered number of days?">Extend</button>
                                        </form>
                                        <form method="POST" action="{{ route('platform.trials.active', $tenant) }}" class="trial-inline-form">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $tenant->trial->is_active ? '0' : '1' }}">
                                            <input name="reason" type="text" maxlength="1000" minlength="5" required
                                                placeholder="Reason for {{ $tenant->trial->is_active ? 'deactivation' : 'reactivation' }}"
                                                aria-label="Reason to {{ $tenant->trial->is_active ? 'deactivate' : 'reactivate' }} {{ $tenant->name }}">
                                            <button type="submit" class="btn btn-small btn-outline"
                                                data-confirm="{{ $tenant->trial->is_active ? 'Deactivate trial access without deleting data?' : 'Reactivate trial while preserving its original expiry date?' }}">
                                                {{ $tenant->trial->is_active ? 'Deactivate' : 'Reactivate' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty <tr><td colspan="6" class="empty-row">No trials match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-line">{{ $tenants->links() }}</div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Trial settings</h2><p class="muted">Applies to newly created businesses only</p></div></div>
        <div class="trial-highlight"><span>Current default</span><strong>{{ $defaultDays }} days</strong><small>Existing trial dates do not change</small></div>
        <form class="form-stack" method="POST" action="{{ route('platform.trials.settings') }}">
            @csrf @method('PUT')
            <label for="default_days">Free trial duration (1–90 days)</label>
            <input id="default_days" name="default_days" min="1" max="90" type="number" required value="{{ old('default_days', $defaultDays) }}">
            <button class="btn btn-primary" type="submit">Save trial settings</button>
        </form>
    </section>
</div>
@endsection
