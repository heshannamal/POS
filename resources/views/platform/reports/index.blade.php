@extends('layouts.app')
@section('title','Platform analytics')
@section('header','Platform analytics')
@section('content')
<div class="page-head">
    <div><span class="overline">OPERATIONAL ANALYTICS</span><h1>Growth & activation</h1><p class="muted">Real registrations, manual activations and support activity. No estimated sales or settlement figures.</p></div>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">▦</span><span class="stat-label">Total businesses</span><strong>{{ number_format($stats['total']) }}</strong><small>Since platform launch</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">↗</span><span class="stat-label">New this month</span><strong>{{ number_format($stats['new_month']) }}</strong><small>Business registrations</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">✓</span><span class="stat-label">Trial conversions</span><strong>{{ number_format($stats['converted']) }}</strong><small>{{ $stats['conversion_rate'] }}% of all businesses</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">✉</span><span class="stat-label">Open support tickets</span><strong>{{ $stats['open_tickets'] }}</strong><small>Pending platform attention</small></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>New businesses by month</h2><p class="muted">Last 12 calendar months · actual created accounts</p></div></div>
        <div class="growth-chart" role="img" aria-label="Monthly registered businesses">
            @foreach($months as $month)
                <div class="growth-col" title="{{ $month['label'] }}: {{ $month['count'] }} businesses">
                    <span class="growth-value">{{ $month['count'] }}</span>
                    <div class="growth-track"><div class="growth-fill" style="height:{{ max(3, round(($month['count'] / $top) * 100)) }}%"></div></div>
                    <small>{{ $month['label'] }}</small>
                </div>
            @endforeach
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Operational signals</h2><p class="muted">Counts from active platform data</p></div></div>
        <div class="detail-list">
            <div><dt>Manually assigned subscriptions</dt><dd>{{ number_format($stats['manual_subscriptions']) }}</dd></div>
            <div><dt>Audit events this week</dt><dd>{{ number_format($stats['activity_week']) }}</dd></div>
            <div><dt>Trial conversions</dt><dd>{{ number_format($stats['converted']) }}</dd></div>
            <div><dt>Conversion ratio</dt><dd>{{ $stats['conversion_rate'] }}%</dd></div>
        </div>
    </section>
</div>
<section class="panel" style="margin-top:16px">
    <div class="panel-heading"><div><h2>Businesses by subscription plan</h2><p class="muted">Plan assignments, including trial and manually activated accounts</p></div></div>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Plan</th><th>Code</th><th>Assigned businesses</th><th>Monthly catalog price</th><th>Billing</th></tr></thead>
        <tbody>
            @foreach($planTotals as $plan)
                <tr><td><strong>{{ $plan->name }}</strong></td><td>{{ $plan->slug }}</td><td>{{ $plan->subscriptions_count }}</td><td>{{ $plan->currency }} {{ number_format($plan->monthly_price_minor/100, 2) }}</td><td><span class="status status-trial">Not collected</span></td></tr>
            @endforeach
        </tbody>
    </table></div>
</section>
@endsection
