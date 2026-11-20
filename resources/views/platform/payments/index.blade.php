@extends('layouts.app')
@section('title','Payments & settlements readiness')
@section('header','Payment setup')
@section('content')
<div class="page-head">
    <div><span class="overline">PAYMENTS & MERCHANT FINANCE</span><h1>Gateway integration readiness</h1><p class="muted">Plan merchant payment acceptance and settlement safely before enabling live checkout.</p></div>
    <span class="status status-expired">Not connected</span>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">◈</span><span class="stat-label">Payment gateway</span><strong class="stat-text">Pending</strong><small>No live acquirer connected</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">▤</span><span class="stat-label">Merchant wallets</span><strong class="stat-text">Not live</strong><small>No merchant funds are held</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Subscription billing</span><strong class="stat-text">Manual</strong><small>Licenses only, no collection</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">!</span><span class="stat-label">Settlement engine</span><strong class="stat-text">Pending</strong><small>No payouts or balances to show</small></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Required integration milestones</h2><p class="muted">The admin team should complete these before processing customer-to-merchant payments.</p></div></div>
        <div class="setup-checklist">
            <div><span class="circle-initial">1</span><div><strong>Approved acquiring or payment partner model</strong><small>Confirm merchant-of-record, aggregator, sub-merchant and settlement permissions in writing</small></div></div>
            <div><span class="circle-initial">2</span><div><strong>Merchant onboarding & verification</strong><small>Identify business ownership, bank account ownership and required regulatory controls</small></div></div>
            <div><span class="circle-initial">3</span><div><strong>Payment intents & webhook verification</strong><small>Use server-side signing, provider IDs, duplicate protection and reliable transaction state transitions</small></div></div>
            <div><span class="circle-initial">4</span><div><strong>Separate financial ledgers</strong><small>Separate platform subscription revenue from funds owed to independent merchants</small></div></div>
            <div><span class="circle-initial">5</span><div><strong>Refunds, chargebacks & settlement reconciliation</strong><small>Implement approved settlement schedules, bank reconciliation, audit and dual authorization</small></div></div>
            <div><span class="circle-initial">6</span><div><strong>Controlled production rollout</strong><small>Penetration testing, backups, financial reconciliation tests and transaction monitoring</small></div></div>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Available today</h2><p class="muted">What this POS Cloud build actually provides</p></div></div>
        <div class="module-list">
            <span>✓ Plan & add-on price catalog</span>
            <span>✓ Manual licensing and override audit</span>
            <span>✓ Tenant-owned business records</span>
            <span>✓ Trial expiration & conversion history</span>
            <span>◷ No card payments or charge records</span>
            <span>◷ No merchant liabilities or payout execution</span>
        </div>
        <div class="hint-box" style="margin-top:18px">Do not enter live gateway secrets in forms or the database until provider-specific encrypted configuration and access controls are implemented. Secrets belong in an approved secure deployment environment.</div>
    </section>
</div>
@endsection
