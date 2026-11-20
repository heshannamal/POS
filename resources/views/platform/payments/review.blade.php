@extends('layouts.app')
@section('title','Review payment #'.$payment->id)
@section('header','Payment #'.str_pad($payment->id,6,'0',STR_PAD_LEFT))
@section('content')
<div class="page-head">
    <div><span class="overline">SUBSCRIPTION PAYMENT VERIFICATION</span><h1>Request #{{ str_pad($payment->id,6,'0',STR_PAD_LEFT) }}</h1><p class="muted">{{ $payment->tenant?->name }} · Submitted {{ $payment->created_at->format('d M Y H:i') }}</p></div>
    <a class="btn btn-outline" href="{{ route('platform.payments.review.index') }}">← Review queue</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Payment details</h2><p class="muted">Review the evidence and separately verify the payment with the actual bank or payment processor.</p></div>
        <span class="status status-{{ $payment->status === 'approved' ? 'active' : ($payment->status === 'rejected' ? 'suspended' : 'trial') }}">{{ ucfirst($payment->status) }}</span></div>
        <dl class="detail-list">
            <div><dt>Business</dt><dd>{{ $payment->tenant?->name }} ({{ $payment->tenant?->slug }})</dd></div>
            <div><dt>Submitted by</dt><dd>{{ $payment->submitter?->name }} · {{ $payment->submitter?->email }}</dd></div>
            <div><dt>Subscription plan</dt><dd>{{ $payment->plan?->name }}</dd></div>
            <div><dt>Billing period</dt><dd>{{ ucfirst($payment->billing_cycle) }}</dd></div>
            <div><dt>Payment method</dt><dd>{{ $payment->method === 'asset' ? 'Uploaded payment proof' : 'Card' }}</dd></div>
            <div><dt>Payable amount</dt><dd><strong>{{ $payment->currency }} {{ number_format($payment->requested_amount_minor/100, 2) }}</strong></dd></div>
            <div><dt>Customer reference</dt><dd>{{ $payment->customer_reference ?: 'Not provided' }}</dd></div>
            <div><dt>Uploaded evidence</dt><dd>
                @if($payment->method === 'asset' && $payment->proof_path)
                    <a href="{{ route('platform.payments.review.receipt',$payment) }}" class="btn btn-small btn-outline">Download private evidence ↓</a>
                @else
                    {{ $payment->provider_verified_at ? 'Provider confirmed' : 'No verified card capture' }}
                @endif
            </dd></div>
            @if($payment->status !== 'pending')
                <div><dt>Administrator decision</dt><dd>{{ ucfirst($payment->status) }} by {{ $payment->reviewer?->name ?? 'Unknown' }} on {{ $payment->reviewed_at?->format('d M Y H:i') }}</dd></div>
                <div><dt>Verified amount</dt><dd>{{ $payment->approved_amount_minor !== null ? $payment->currency.' '.number_format($payment->approved_amount_minor/100,2) : '—' }}</dd></div>
                <div><dt>Bank / acquirer reference</dt><dd>{{ $payment->review_reference ?? '—' }}</dd></div>
                <div><dt>Review notes</dt><dd>{{ $payment->review_notes ?? '—' }}</dd></div>
            @endif
        </dl>
        <div class="hint-box">An uploaded file only documents a claim. Before approving, open the separate bank/acquirer records and confirm the funds, currency, amount, and unique reference. Never use a receipt image as sole evidence.</div>
    </section>
    @if($payment->status === 'pending')
        <section class="panel">
            <div class="panel-heading"><div><h2>Approve verified payment</h2><p class="muted">Activates the selected subscription only after approval</p></div></div>
            <form method="POST" action="{{ route('platform.payments.review.approve',$payment) }}" class="form-stack">
                @csrf
                <label for="approved_amount">Amount independently verified ({{ $payment->currency }})</label>
                <input id="approved_amount" name="approved_amount" type="number" min="0.01" max="10000000" step="0.01" required value="{{ old('approved_amount',number_format($payment->requested_amount_minor/100,2,'.','')) }}">
                <label for="review_reference">Unique bank or acquirer transaction reference</label>
                <input id="review_reference" name="review_reference" maxlength="120" required pattern="[a-zA-Z0-9_.-]+" value="{{ old('review_reference') }}" placeholder="Confirmed external transaction ID">
                <label for="review_notes">Internal verification notes (optional)</label>
                <textarea name="review_notes" id="review_notes" rows="3" maxlength="2000" placeholder="Reconciliation method and notes">{{ old('review_notes') }}</textarea>
                <label class="check-line"><input type="checkbox" name="confirm_funds_checked" value="1" required> I independently verified this transaction with the bank/acquirer and confirmed the amount and currency.</label>
                <button class="btn btn-primary" type="submit" data-confirm="Confirm independently verified payment and activate this subscription?">Approve payment & activate</button>
            </form>
            <hr style="margin:22px 0;border:0;border-top:1px solid var(--border)">
            <form method="POST" action="{{ route('platform.payments.review.reject',$payment) }}" class="form-stack">
                @csrf
                <label for="reason">Reject payment request — reason required</label>
                <textarea id="reason" name="reason" rows="3" minlength="5" maxlength="2000" required placeholder="Missing or invalid payment, amount not confirmed, duplicate bank reference..."></textarea>
                <button class="btn btn-outline" type="submit" data-confirm="Reject this request without changing the subscription?">Reject request</button>
            </form>
        </section>
    @else
        <section class="panel">
            <div class="panel-heading"><div><h2>Review completed</h2><p class="muted">Decisions are final to prevent duplicate approvals and are recorded in the audit log.</p></div></div>
            <div class="trial-highlight"><span>Outcome</span><strong>{{ ucfirst($payment->status) }}</strong><small>{{ $payment->reviewed_at?->format('d M Y H:i') }}</small></div>
        </section>
    @endif
</div>
@endsection
