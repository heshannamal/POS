@extends('layouts.app')
@section('title','Invoice '.$invoice->number)
@section('header','Invoice details')
@section('content')
<div class="page-head">
    <div><span class="overline">PRIVATE BILLING DOCUMENT</span><h1>{{ $invoice->number }}</h1><p class="muted">{{ $invoice->bill_to_name }} · {{ $invoice->issued_at?->format('d M Y') }}</p></div>
    <div class="page-actions">
        <a class="btn btn-outline" href="{{ route('business.billing.index',$tenant) }}">← History</a>
        <a class="btn btn-secondary" href="{{ route('business.billing.pdf',[$tenant,$invoice]) }}">Invoice PDF ↓</a>
        @if($invoice->status==='paid')<a class="btn btn-primary" href="{{ route('business.billing.pdf',[$tenant,$invoice,'type'=>'receipt']) }}">Receipt PDF ↓</a>@endif
    </div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Invoice breakdown</h2><p class="muted">All prices are in {{ $invoice->currency }}.</p></div></div>
        <dl class="detail-list">
            <div><dt>Plan</dt><dd>{{ $invoice->plan_name }} ({{ $invoice->billing_cycle }})</dd></div>
            <div><dt>List price</dt><dd>{{ number_format($invoice->list_price_minor/100,2) }}</dd></div>
            <div><dt>Plan discount</dt><dd>− {{ number_format($invoice->plan_discount_minor/100,2) }}</dd></div>
            <div><dt>Coupon</dt><dd>{{ $invoice->coupon_code ?? 'None' }} · − {{ number_format($invoice->coupon_discount_minor/100,2) }}</dd></div>
            <div><dt>Tax ({{ number_format($invoice->tax_basis_points/100,2) }}%)</dt><dd>{{ number_format($invoice->tax_minor/100,2) }}</dd></div>
            <div><dt>Total</dt><dd><strong>{{ $invoice->currency }} {{ number_format($invoice->total_minor/100,2) }}</strong></dd></div>
            <div><dt>Status</dt><dd>{{ ucfirst($invoice->status) }}</dd></div>
            <div><dt>Receipt</dt><dd>{{ $invoice->receipt_number ?? 'Not yet issued' }}</dd></div>
            <div><dt>Subscription period</dt><dd>{{ $invoice->period_start?->format('d M Y') ?? '—' }} – {{ $invoice->period_end?->format('d M Y') ?? '—' }}</dd></div>
        </dl>
        <div class="hint-box">A pending invoice confirms only that payment evidence was submitted; it does not mean funds have been cleared. Your plan activates only after platform approval.</div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Credit notes</h2><p class="muted">Manual externally verified refund records.</p></div></div>
        <div class="stack-list">
            @forelse($invoice->refunds as $refund)
                <div class="stack-row"><span class="circle-initial">↩</span>
                    <span class="stack-grow"><strong>{{ $refund->credit_note_number }}</strong><small>{{ $refund->currency }} {{ number_format($refund->amount_minor/100,2) }} · {{ $refund->verified_at?->format('d M Y') }}</small></span>
                    <a class="btn btn-small btn-outline" href="{{ route('business.billing.credit-note',[$tenant,$refund]) }}">PDF ↓</a>
                </div>
            @empty <p class="muted empty-box">No verified refunds.</p> @endforelse
        </div>
    </section>
</div>
@endsection
