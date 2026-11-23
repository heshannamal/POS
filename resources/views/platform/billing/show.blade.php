@extends('layouts.app')
@section('title','Invoice '.$invoice->number)
@section('header','Billing invoice details')
@section('content')
@php $financialAdmin=auth()->user()->isPermanentPlatformAdmin() && in_array(auth()->user()->platformRole(),['super_admin','billing'],true); @endphp
<div class="page-head">
    <div><span class="overline">BILLING DOCUMENT</span><h1>{{ $invoice->number }}</h1><p class="muted">{{ $invoice->bill_to_name }} · {{ $invoice->issued_at?->format('d M Y') }} · {{ $invoice->currency }}</p></div>
    <div class="page-actions">
        <a class="btn btn-outline" href="{{ route('platform.billing.invoices.index') }}">← Invoices</a>
        <a class="btn btn-secondary" href="{{ route('platform.billing.invoices.pdf',$invoice) }}">Download invoice PDF ↓</a>
        @if($invoice->status==='paid')<a class="btn btn-primary" href="{{ route('platform.billing.invoices.pdf',[$invoice,'type'=>'receipt']) }}">Download receipt ↓</a>@endif
    </div>
</div>
<div class="stat-grid">
    <div class="stat-card tint-blue"><span class="stat-icon">▤</span><span class="stat-label">Total invoiced</span><strong class="stat-text">{{ $invoice->currency }} {{ number_format($invoice->total_minor/100,2) }}</strong><small>{{ ucfirst($invoice->billing_cycle) }} billing</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Payment status</span><strong class="stat-text">{{ ucfirst($invoice->status) }}</strong><small>{{ $invoice->paid_at?->format('d M Y') ?? 'Awaiting bank review' }}</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">↩</span><span class="stat-label">Externally verified refunds</span><strong class="stat-text">{{ $invoice->currency }} {{ number_format($invoice->refunded_minor/100,2) }}</strong><small>Does not initiate transfers</small></div>
</div>
<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Invoice breakdown</h2><p class="muted">Captured at the moment the customer submitted the payment request.</p></div></div>
        <dl class="detail-list">
            <div><dt>Business</dt><dd>{{ $invoice->bill_to_name }}</dd></div>
            <div><dt>Address</dt><dd>{{ $invoice->bill_to_address ?? '—' }}</dd></div>
            <div><dt>Plan</dt><dd>{{ $invoice->plan_name }} ({{ $invoice->billing_cycle }})</dd></div>
            <div><dt>List price</dt><dd>{{ $invoice->currency }} {{ number_format($invoice->list_price_minor/100,2) }}</dd></div>
            <div><dt>Plan discount</dt><dd>− {{ number_format($invoice->plan_discount_minor/100,2) }}</dd></div>
            <div><dt>Coupon ({{ $invoice->coupon_code ?? 'none' }})</dt><dd>− {{ number_format($invoice->coupon_discount_minor/100,2) }}</dd></div>
            <div><dt>After discounts</dt><dd>{{ number_format($invoice->subtotal_minor/100,2) }}</dd></div>
            <div><dt>Tax ({{ number_format($invoice->tax_basis_points/100,2) }}%)</dt><dd>{{ number_format($invoice->tax_minor/100,2) }}</dd></div>
            <div><dt><strong>Total</strong></dt><dd><strong>{{ $invoice->currency }} {{ number_format($invoice->total_minor/100,2) }}</strong></dd></div>
            <div><dt>Payment reference</dt><dd>{{ $invoice->payment?->review_reference ?? 'Not independently verified' }}</dd></div>
            <div><dt>Receipt number</dt><dd>{{ $invoice->receipt_number ?? 'Issued only after verified approval' }}</dd></div>
            <div><dt>Subscription period</dt><dd>{{ $invoice->period_start?->format('d M Y') ?? '—' }} – {{ $invoice->period_end?->format('d M Y') ?? '—' }}</dd></div>
        </dl>
        @if($invoice->payment)
            <a href="{{ route('platform.payments.review.show',$invoice->payment) }}" class="btn btn-outline" style="margin-top:15px">View original payment verification →</a>
        @endif
        @if($financialAdmin && $invoice->status!=='void')
            <form action="{{ route('platform.billing.invoices.resend',$invoice) }}" method="POST" style="margin-top:15px">@csrf
                <button class="btn btn-secondary" data-confirm="Request a new owner notification using current Phase 5 email safety settings?">Resend invoice notification</button>
            </form>
        @endif
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Credit notes / refunds</h2><p class="muted">Recorded after refund settlement is independently confirmed.</p></div></div>
        <div class="stack-list">
            @forelse($invoice->refunds as $refund)
                <div class="stack-row"><span class="circle-initial">↩</span>
                    <span class="stack-grow"><strong>{{ $refund->credit_note_number }}</strong>
                        <small>{{ $refund->currency }} {{ number_format($refund->amount_minor/100,2) }} · {{ $refund->verified_at?->format('d M Y') }}</small>
                        <small>{{ $refund->external_reference }}</small>
                    </span>
                    <a class="btn btn-small btn-outline" href="{{ route('platform.billing.credit-note',$refund) }}">PDF ↓</a>
                </div>
            @empty <p class="muted empty-box">No verified refunds recorded.</p> @endforelse
        </div>
        @if($financialAdmin && $invoice->status==='paid' && $invoice->total_minor>$invoice->refunded_minor)
            <hr style="border:0;border-top:1px solid var(--border);margin:20px 0">
            <h2>Record external refund</h2>
            <p class="muted">This form does not send a bank transfer or call a card provider. First verify the refund in the bank or acquirer console.</p>
            <form method="POST" action="{{ route('platform.billing.invoices.refund',$invoice) }}" class="form-stack">@csrf
                <label>Verified amount ({{ $invoice->currency }})</label>
                <input name="amount" type="number" min="0.01" max="{{ number_format(($invoice->total_minor-$invoice->refunded_minor)/100,2,'.','') }}" step="0.01" required>
                <label>External refund reference</label>
                <input name="external_reference" pattern="[A-Za-z0-9_.-]+" maxlength="120" required>
                <label>Verified method</label>
                <select name="method"><option value="bank">Bank transfer</option><option value="card">Card processor</option><option value="other">Other external method</option></select>
                <label>Detailed reason</label>
                <textarea name="reason" minlength="10" rows="3" maxlength="2000" required></textarea>
                <label class="check-line"><input type="checkbox" name="confirm_external_refund" value="1" required> I independently verified the refund settled outside POS Cloud.</label>
                <button class="btn btn-primary" data-confirm="Record externally settled refund and create credit note?">Record verified refund →</button>
            </form>
        @endif
    </section>
</div>
@endsection
