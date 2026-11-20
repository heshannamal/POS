@extends('layouts.app')
@section('title', 'Trial management')
@section('header', 'Trial management')
@section('content')
<div class="page-head">
    <div><span class="overline">TRIAL LIFECYCLE</span><h1>Trials & conversions</h1><p class="muted">Monitor current trials, expired accounts, and manual plan activations without removing business data.</p></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Business trials</h2><p class="muted">Manage each trial from its business details page</p></div></div>
        <div class="tab-row">
            @foreach(['running' => 'Running', 'expired' => 'Expired', 'converted' => 'Converted', 'suspended' => 'Suspended', 'all' => 'All'] as $value => $label)
                <a class="tab-pill {{ $state === $value ? 'is-active' : '' }}" href="{{ route('platform.trials.index', ['state' => $value]) }}">{{ $label }}</a>
            @endforeach
        </div>
        <form action="{{ route('platform.trials.index') }}" method="GET" class="filter-row">
            <input type="hidden" name="state" value="{{ $state }}">
            <input name="search" type="search" placeholder="Search business or contact..." value="{{ request('search') }}">
            <button class="btn btn-outline" type="submit">Search</button>
        </form>
        <div class="table-scroll">
            <table class="data-table"><thead><tr><th>Business</th><th>Plan</th><th>Started</th><th>Expires</th><th>Remaining</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr>
                            <td><strong>{{ $tenant->name }}</strong><small class="table-subtext">{{ $tenant->contact_email }}</small></td>
                            <td>{{ $tenant->subscription?->plan?->name ?? '—' }}</td>
                            <td>{{ $tenant->trial?->started_at?->format('d M Y') }}</td>
                            <td>{{ $tenant->trial?->ends_at?->format('d M Y') }}</td>
                            <td><span class="status status-{{ $tenant->effective_status }}">{{ $tenant->effective_status === 'trial' ? $tenant->trial->days_remaining.' days' : ucfirst($tenant->effective_status) }}</span></td>
                            <td><a class="btn btn-small btn-outline" href="{{ route('platform.tenants.show', $tenant) }}">Manage →</a></td>
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
