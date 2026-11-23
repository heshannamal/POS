@extends('layouts.app')
@section('title', 'Subscription & modules')
@section('header', 'Subscription & modules')
@section('content')
<div class="page-head">
    <div><span class="overline">BUSINESS LICENSING</span><h1>Your subscription</h1><p class="muted">Review plan features, submit payment evidence, and access the latest billing records. Card checkout is not connected yet.</p></div>
</div>
<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Current plan</h2><p class="muted">Plan assigned to this business</p></div></div>
        <div class="trial-highlight">
            <span>{{ strtoupper($tenant->subscription?->status ?? 'unassigned') }}</span>
            <strong>{{ $tenant->subscription?->plan?->name ?? 'No plan' }}</strong>
            <small>{{ $tenant->subscription?->source === 'manual' ? 'Activated by a platform administrator, not billed online' : 'Trial account' }}</small>
        </div>
        @if($tenant->status === 'trial')
            <p class="muted">{{ $tenant->trial?->days_remaining ?? 0 }} days remaining until {{ $tenant->trial?->ends_at?->format('d M Y') }}.</p>
        @endif
        <p class="muted">Card charges are not automated. Uploaded bank evidence requires independent platform verification before access changes.</p>
        @if($canSubmit)<a class="btn btn-outline" style="margin-top:12px" href="{{ route('business.billing.index',$tenant) }}">Invoices & receipts →</a>@endif
    </section>
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Module access</h2><p class="muted">Access requires a released feature and an active plan or override.</p></div></div>
        <div class="stack-list">
            @foreach($modules as $module)
                <div class="stack-row">
                    <span class="circle-initial">◇</span>
                    <span class="stack-grow"><strong>{{ $module->name }}</strong><small>{{ $module->category }} · {{ $module->code }}</small></span>
                    <span class="status status-{{ $allowed[$module->code] ? 'active' : ($module->is_available ? 'expired' : 'trial') }}">{{ $allowed[$module->code] ? 'Enabled' : ($module->is_available ? 'Not licensed' : 'Coming soon') }}</span>
                </div>
            @endforeach
        </div>
    </section>
</div>

<section class="panel" style="margin-top:18px">
    <div class="panel-heading">
        <div><h2>Subscription payment methods</h2><p class="muted">An uploaded receipt is reviewed by the platform before access changes. Card processing is not yet integrated.</p></div>
    </div>
    <div class="dashboard-grid">
        <div class="payment-method-choice">
            <strong>◇ Card payment</strong>
            <span class="status status-trial">{{ ($methods['card'] ?? null)?->is_enabled ? 'Enabled in settings; gateway not ready' : 'Inactive' }}</span>
            <p class="muted">A card checkout will appear after an approved payment provider is connected. Do not enter card numbers or CVV on this page.</p>
        </div>
        <div class="payment-method-choice">
            <strong>▤ Upload payment proof / asset</strong>
            <span class="status status-{{ ($methods['asset'] ?? null)?->is_operational ? 'active' : 'trial' }}">{{ ($methods['asset'] ?? null)?->is_operational ? 'Available' : 'Inactive' }}</span>
            <p class="muted">Submit bank transfer evidence for manual review. An administrator must confirm actual funds before approving.</p>
        </div>
    </div>
    @if(($methods['asset'] ?? null)?->is_operational && $canSubmit)
        @if($pendingPayment)
            <div class="hint-box" style="margin-top:16px">
                Request #{{ str_pad($pendingPayment->id,6,'0',STR_PAD_LEFT) }} is awaiting admin review.
                You cannot submit a second payment until it is approved or rejected.
            </div>
        @elseif($tenant->status === 'suspended')
            <div class="hint-box" style="margin-top:16px">This business is suspended. Contact support before submitting a subscription payment.</div>
        @else
            <div class="form-stack" style="margin-top:17px">
                <h2>Submit a payment receipt</h2>
                <p class="muted" style="white-space:pre-wrap">{{ $methods['asset']->instructions }}</p>
            </div>
            @if($plans->isNotEmpty())
                <div class="table-scroll">
                    <table class="data-table">
                        <thead><tr><th>Available plan</th><th>Monthly amount</th><th>Yearly amount</th></tr></thead>
                        <tbody>
                            @foreach($plans as $plan)
                                <tr>
                                    <td><strong>{{ $plan->name }}</strong></td>
                                    <td>@if($plan->discountIsActive())<small class="table-subtext"><s>{{ $plan->currency }} {{ number_format($plan->monthly_price_minor/100,2) }}</s></small>@endif
                                        <strong>{{ $plan->currency }} {{ number_format($plan->priceForCycleMinor('monthly')/100,2) }}</strong>
                                        @if($plan->discountIsActive())<span class="status status-active">{{ $plan->discount_percentage }}% off</span>@endif</td>
                                    <td>{{ $plan->yearly_price_minor !== null ? $plan->currency.' '.number_format($plan->priceForCycleMinor('yearly')/100,2) : 'Not offered' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('business.subscription.payments.store',$tenant) }}"
                      enctype="multipart/form-data" class="form-stack" style="margin-top:17px"
                      data-billing-quote-url="{{ route('business.subscription.payments.quote',$tenant) }}">
                    @csrf
                    <label for="payment_plan_id">Subscription plan</label>
                    <select name="plan_id" id="payment_plan_id" required>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected(old('plan_id')==$plan->id)>{{ $plan->name }} · {{ $plan->currency }} {{ number_format($plan->priceForCycleMinor('monthly')/100,2) }}/month</option>
                        @endforeach
                    </select>
                    <label for="billing_cycle">Billing period</label>
                    <select id="billing_cycle" name="billing_cycle" required>
                        <option value="monthly" @selected(old('billing_cycle')==='monthly')>Monthly</option>
                        <option value="yearly" @selected(old('billing_cycle')==='yearly')>Yearly (only when plan offers annual pricing)</option>
                    </select>
                    <label for="coupon_code">Discount code (optional)</label>
                    <input name="coupon_code" id="coupon_code" maxlength="40" pattern="[A-Za-z0-9_-]+" value="{{ old('coupon_code') }}" placeholder="WELCOME10">
                    <p class="muted">The final payable amount includes applicable plan discounts, coupon savings, and configured subscription tax. A coupon never activates a subscription without verification.</p>
                    <div id="billing-quote" class="hint-box" aria-live="polite" role="status">Choose a plan and billing period to calculate the final invoice amount.</div>
                    <label for="customer_reference">Your bank transaction reference (optional)</label>
                    <input id="customer_reference" name="customer_reference" maxlength="120" value="{{ old('customer_reference') }}" placeholder="Receipt/transfer reference">
                    <label for="evidence">Attach bank transfer receipt or payment evidence *</label>
                    <input name="evidence" id="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required>
                    <p class="muted">JPG, PNG or PDF, up to 5 MB. The file stays private and does not prove payment until verified by the platform.</p>
                    <button type="submit" class="btn btn-primary" data-confirm="Submit this payment proof for manual verification? The platform must approve before your subscription changes.">Submit for verification →</button>
                </form>
            @else
                <p class="muted">No published subscription plans match this business currency ({{ $tenant->currency }}). Contact support.</p>
            @endif
        @endif
    @else
        <div class="hint-box" style="margin-top:17px">
            @if(!$canSubmit)
                Only the business owner or authorized account settings manager can submit payment evidence.
            @else
                Payment-proof uploads are currently disabled by the platform.
            @endif
        </div>
    @endif
</section>
<section class="panel" style="margin-top:18px">
    <div class="panel-heading">
        <div><h2>Payment submission history</h2><p class="muted">Customer submissions and administrator verification decisions for this business.</p></div>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Reference</th><th>Invoice</th><th>Plan</th><th>Cycle</th><th>Method</th><th>Requested</th><th>Verified</th><th>Status</th><th>Reviewed</th><th>Evidence</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td><strong>#{{ str_pad($payment->id,6,'0',STR_PAD_LEFT) }}</strong></td>
                    <td>@if($canSubmit && $payment->billingInvoice)<a class="text-link" href="{{ route('business.billing.show',[$tenant,$payment->billingInvoice]) }}">{{ $payment->billingInvoice->number }}</a>@else — @endif</td>
                    <td>{{ $payment->plan?->name }}</td>
                    <td>{{ ucfirst($payment->billing_cycle) }}</td>
                    <td>{{ $payment->method === 'asset' ? 'Uploaded proof' : 'Card' }}</td>
                    <td>{{ $payment->currency }} {{ number_format($payment->requested_amount_minor/100,2) }}</td>
                    <td>{{ $payment->approved_amount_minor !== null ? $payment->currency.' '.number_format($payment->approved_amount_minor/100,2) : '—' }}</td>
                    <td><span class="status status-{{ $payment->status === 'approved' ? 'active' : ($payment->status === 'rejected' ? 'suspended' : 'trial') }}">{{ ucfirst($payment->status) }}</span></td>
                    <td>{{ $payment->reviewed_at?->format('d M Y') ?? 'Awaiting review' }}</td>
                    <td>@if($payment->method==='asset' && $canSubmit)
                        <a class="text-link" href="{{ route('business.subscription.payments.receipt',[$tenant,$payment]) }}">Download</a>
                        @else — @endif</td>
                </tr>
            @empty <tr><td colspan="10" class="empty-row">No payment requests have been submitted.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
