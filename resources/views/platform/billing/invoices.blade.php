@extends('layouts.app')
@section('title','Billing invoices')
@section('header','Subscription invoices')
@section('content')
<div class="page-head">
    <div><span class="overline">FINANCIAL OPERATIONS</span><h1>Invoices & receipts</h1><p class="muted">Amounts are immutable snapshots captured at payment submission. Receipts exist only for independently verified payments.</p></div>
    <div class="page-actions">
        <a class="btn btn-outline" href="{{ route('platform.billing.coupons.index') }}">Coupons</a>
        <a class="btn btn-secondary" href="{{ route('platform.billing.reports.index') }}">Billing reports →</a>
    </div>
</div>
<div class="stat-grid">
    <div class="stat-card tint-peach"><span class="stat-icon">◷</span><span class="stat-label">Pending verification</span><strong>{{ $stats['pending'] }}</strong><small>Payment not yet approved</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Paid</span><strong>{{ $stats['paid'] }}</strong><small>Independently verified</small></div>
    <div class="stat-card tint-lavender"><span class="stat-icon">×</span><span class="stat-label">Voided</span><strong>{{ $stats['void'] }}</strong><small>Payment request rejected</small></div>
</div>
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>Invoice directory</h2><p class="muted">{{ $invoices->total() }} matching invoices</p></div></div>
    <form method="GET" class="tenant-filters">
        <label>Invoice / business<input type="search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="Invoice number or business name"></label>
        <label>Status<select name="status"><option value="">All</option>@foreach(['pending','paid','void'] as $v)<option value="{{ $v }}" @selected(request('status')===$v)>{{ ucfirst($v) }}</option>@endforeach</select></label>
        <label>Currency<select name="currency"><option value="">All</option>@foreach(['LKR','USD'] as $currency)<option value="{{ $currency }}" @selected(request('currency')===$currency)>{{ $currency }}</option>@endforeach</select></label>
        <label>From<input type="date" name="from" value="{{ request('from') }}"></label>
        <label>To<input type="date" name="to" value="{{ request('to') }}"></label>
        <div class="tenant-filter-actions"><button class="btn btn-secondary">Filter</button><a class="btn btn-outline" href="{{ route('platform.billing.invoices.index') }}">Clear</a></div>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Invoice</th><th>Business</th><th>Plan</th><th>Total</th><th>Refunded</th><th>Status</th><th>Issued</th><th></th></tr></thead>
        <tbody>
        @forelse($invoices as $invoice)
            <tr>
                <td><strong>{{ $invoice->number }}</strong><small class="table-subtext">{{ ucfirst($invoice->billing_cycle) }}</small></td>
                <td>{{ $invoice->tenant?->name }}</td>
                <td>{{ $invoice->plan_name }}</td>
                <td>{{ $invoice->currency }} {{ number_format($invoice->total_minor/100,2) }}</td>
                <td>{{ $invoice->currency }} {{ number_format($invoice->refunded_minor/100,2) }}</td>
                <td><span class="status status-{{ $invoice->status==='paid'?'active':($invoice->status==='void'?'suspended':'trial') }}">{{ ucfirst($invoice->status) }}</span></td>
                <td>{{ $invoice->issued_at?->format('d M Y') }}</td>
                <td><a class="btn btn-small btn-outline" href="{{ route('platform.billing.invoices.show',$invoice) }}">Details →</a></td>
            </tr>
        @empty <tr><td colspan="8" class="empty-row">No invoices match your filters.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $invoices->links() }}</div>
</section>
@endsection
