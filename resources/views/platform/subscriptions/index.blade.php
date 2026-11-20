@extends('layouts.app')
@section('title','Subscriptions')
@section('header','Subscription registry')
@section('content')
<div class="page-head"><div><span class="overline">LICENSING OVERVIEW</span><h1>Business subscriptions</h1><p class="muted">Live subscription records, trial conversions and manually activated plans. No online payments have been processed.</p></div></div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">▤</span><span class="stat-label">Total subscriptions</span><strong>{{ number_format($stats['all']) }}</strong><small>Business plan records</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Manually active</span><strong>{{ number_format($stats['active']) }}</strong><small>Not proof of payment</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">◷</span><span class="stat-label">Trialing</span><strong>{{ number_format($stats['trialing']) }}</strong><small>Trials may have expired</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">!</span><span class="stat-label">Paused</span><strong>{{ number_format($stats['paused']) }}</strong><small>Restricted subscriptions</small></div>
</div>
<section class="panel">
    <form method="GET" action="{{ route('platform.subscriptions.index') }}" class="filter-row">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search business name or slug">
        <select name="status"><option value="">All statuses</option>
            @foreach(['trialing' => 'Trialing', 'active' => 'Active', 'paused' => 'Paused', 'cancelled' => 'Cancelled'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="plan"><option value="">All plans</option>
            @foreach($plans as $plan)<option value="{{ $plan->slug }}" @selected(request('plan') === $plan->slug)>{{ $plan->name }}</option>@endforeach
        </select>
        <button class="btn btn-secondary">Apply filters</button>
        <a class="btn btn-outline" href="{{ route('platform.subscriptions.index') }}">Reset</a>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Business</th><th>Plan</th><th>Status</th><th>Source</th><th>Activated / started</th><th>End date</th><th></th></tr></thead>
        <tbody>
        @forelse($subscriptions as $subscription)
            <tr>
                <td><strong>{{ $subscription->tenant?->name ?? 'Deleted business' }}</strong><small class="table-subtext">{{ $subscription->tenant?->slug }}</small></td>
                <td>{{ $subscription->plan?->name ?? '—' }}</td>
                <td><span class="status status-{{ $subscription->status === 'active' ? 'active' : ($subscription->status === 'trialing' ? 'trial' : 'suspended') }}">{{ ucfirst($subscription->status) }}</span></td>
                <td>{{ ucfirst($subscription->source) }} {{ $subscription->source === 'manual' ? '(unverified payment)' : '' }}</td>
                <td>{{ $subscription->started_at?->format('d M Y') ?? '—' }}</td>
                <td>{{ $subscription->ends_at?->format('d M Y') ?? '—' }}</td>
                <td>@if($subscription->tenant)<a class="text-link" href="{{ route('platform.tenants.show', $subscription->tenant) }}">Open →</a>@endif</td>
            </tr>
        @empty <tr><td colspan="7" class="empty-row">No subscriptions match your filters.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $subscriptions->links() }}</div>
</section>
<div class="hint-box" style="margin-top:16px">The platform has no billing ledger, collection receipts or merchant settlement engine yet. These are license states, not financial reporting.</div>
@endsection
