@extends('layouts.app')
@section('title','Subscriptions')
@section('header','Subscription registry')
@section('content')
<div class="page-head"><div><span class="overline">LICENSING OVERVIEW</span><h1>Business subscriptions</h1><p class="muted">Active subscriptions, verified payment renewals, manual extensions, and expiry tracking. Card checkout is not connected.</p></div></div>
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
            @foreach(['trialing' => 'Trialing', 'active' => 'Active', 'paused' => 'Paused', 'past_due'=>'Past due','expired'=>'Expired', 'cancelled' => 'Cancelled'] as $value => $label)
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
        <thead><tr><th>Business</th><th>Plan</th><th>Status</th><th>Source</th><th>Activated / started</th><th>End date</th><th>Renewal</th><th></th></tr></thead>
        <tbody>
        @forelse($subscriptions as $subscription)
            <tr>
                <td><strong>{{ $subscription->tenant?->name ?? 'Deleted business' }}</strong><small class="table-subtext">{{ $subscription->tenant?->slug }}</small></td>
                <td>{{ $subscription->plan?->name ?? '—' }}</td>
                <td><span class="status status-{{ $subscription->status === 'active' ? 'active' : ($subscription->status === 'trialing' ? 'trial' : 'suspended') }}">{{ ucfirst($subscription->status) }}</span></td>
                <td>{{ ucfirst($subscription->source) }} {{ $subscription->source === 'manual' ? '(unverified payment)' : '' }}</td>
                <td>{{ $subscription->started_at?->format('d M Y') ?? '—' }}</td>
                <td>{{ $subscription->ends_at?->format('d M Y') ?? '—' }}</td>
                <td>{{ $subscription->auto_renew?'Reminder requested':'Manual' }}{{ $subscription->cancel_at_period_end?' · Cancel at end':'' }}</td>
                <td>@if($subscription->tenant)<a class="text-link" href="{{ route('platform.tenants.show', $subscription->tenant) }}">Open →</a>@endif</td>
            </tr>
        @empty <tr><td colspan="8" class="empty-row">No subscriptions match your filters.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $subscriptions->links() }}</div>
</section>
<div class="hint-box" style="margin-top:16px">Paid invoices now have verified receipts. The platform does not charge cards automatically. For approved invoices and revenue totals, open <a href="{{ route('platform.billing.reports.index') }}">Billing Reports</a>. The renewal preference is informational until a connected gateway is released.</div>
@php $billingAdmin=auth()->user()->isPermanentPlatformAdmin() && in_array(auth()->user()->platformRole(),['super_admin','billing'],true); @endphp
@if($billingAdmin)
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>Manually extend a paid subscription</h2><p class="muted">Explicit no-charge administrator override only. Does not generate an invoice or record payment.</p></div></div>
    <form method="POST" id="manual-extension" class="form-stack" action="{{ route('platform.billing.subscriptions.extend', ['tenant'=>optional($subscriptions->first()?->tenant)->slug ?? 'invalid']) }}">@csrf
        <label>Choose a business</label>
        <select name="tenant_id_picker" id="extension_tenant" required>
            <option value="">Choose a business from this page</option>
            @foreach($subscriptions as $subscription)
                @if($subscription->tenant && $subscription->status!=='trialing')
                    <option value="{{ route('platform.billing.subscriptions.extend',$subscription->tenant) }}">{{ $subscription->tenant->name }} · {{ $subscription->plan?->name }}</option>
                @endif
            @endforeach
        </select>
        <label>Additional days</label><input name="days" type="number" min="1" max="365" required value="30">
        <label>Administrator reason</label><textarea name="reason" rows="2" minlength="10" maxlength="1000" required></textarea>
        <label class="check-line"><input type="checkbox" name="confirm_no_payment" value="1" required> I confirm this is a manual extension with no payment collected.</label>
        <button class="btn btn-secondary" type="submit" data-confirm="Extend this subscription for free and write an audit record?">Extend without charge</button>
    </form>
</section>
<script>
document.querySelector('#manual-extension')?.addEventListener('submit', function(event) {
    const url = document.querySelector('#extension_tenant')?.value;
    if (!url) { event.preventDefault(); return; }
    this.action=url;
});
</script>
@endif
@endsection
