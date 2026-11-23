@extends('layouts.app')
@section('title','Billing settings')
@section('header','Subscription billing settings')
@section('content')
@php $canEdit=auth()->user()->isPermanentPlatformAdmin() && in_array(auth()->user()->platformRole(),['super_admin','billing'],true); @endphp
<div class="page-head">
    <div><span class="overline">PLATFORM CONFIGURATION</span><h1>Billing defaults & tax</h1><p class="muted">Configured values apply only to future invoices. Existing issued invoices are immutable financial records.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.billing.invoices.index') }}">← Invoices</a>
</div>
<div class="dashboard-grid">
<section class="panel panel-wide">
    <div class="panel-heading"><div><h2>New invoice settings</h2><p class="muted">These are manual configuration rates. Confirm applicable tax obligations with a qualified adviser.</p></div></div>
    @if($canEdit)
    <form method="POST" action="{{ route('platform.billing.settings.update') }}" class="form-stack">@csrf @method('PUT')
        <label>Platform subscription tax rate (%)</label>
        <input name="tax_rate" type="number" min="0" max="100" step="0.01" value="{{ old('tax_rate',$settings['tax_rate']) }}" required>
        <label>Invoice due days</label><input name="invoice_due_days" type="number" min="1" max="60" value="{{ old('invoice_due_days',$settings['invoice_due_days']) }}" required>
        <label>Subscription grace days (account access remains restricted after expiry)</label><input name="grace_days" type="number" min="0" max="60" value="{{ old('grace_days',$settings['grace_days']) }}" required>
        <button type="submit" class="btn btn-primary" data-confirm="Update settings for new invoices only?">Save billing settings</button>
    </form>
    @else
    <dl class="detail-list">
        <div><dt>Tax rate</dt><dd>{{ $settings['tax_rate'] }}%</dd></div>
        <div><dt>Invoice due days</dt><dd>{{ $settings['invoice_due_days'] }}</dd></div>
        <div><dt>Grace days</dt><dd>{{ $settings['grace_days'] }}</dd></div>
    </dl>
    @endif
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Verification boundaries</h2><p class="muted">Preventing mistaken financial activation</p></div></div>
    <p class="muted">Uploaded receipts require independent bank verification. Card payment methods remain non-operational until an approved acquiring gateway is connected.</p>
    <p class="muted">The renewal preference does not authorize automatic card collection or funds transfers. A new paid period requires another verified approval unless the platform administrator explicitly grants a no-charge extension.</p>
    <div class="hint-box">To synchronize ended subscription periods run <code>php artisan billing:maintain</code>. It is also scheduled daily. It never charges a payment instrument.</div>
</section>
</div>
@endsection
