@extends('layouts.app')
@section('title','Invoices & billing')
@section('header','Billing history')
@section('content')
<div class="page-head">
    <div><span class="overline">YOUR BUSINESS BILLING</span><h1>Invoices and receipts</h1><p class="muted">Private billing documents for {{ $tenant->name }}. Payment receipts are available after verified platform approval.</p></div>
    <a class="btn btn-outline" href="{{ route('business.subscription.index',$tenant) }}">← Subscription</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Invoice history</h2><p class="muted">Tax, discounts and plan names are captured when the invoice was created.</p></div></div>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Invoice</th><th>Plan</th><th>Total</th><th>Status</th><th>Refunded</th><th></th></tr></thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td><strong>{{ $invoice->number }}</strong><small class="table-subtext">{{ $invoice->issued_at?->format('d M Y') }}</small></td>
                        <td>{{ $invoice->plan_name }} · {{ ucfirst($invoice->billing_cycle) }}</td>
                        <td>{{ $invoice->currency }} {{ number_format($invoice->total_minor/100,2) }}</td>
                        <td><span class="status status-{{ $invoice->status==='paid'?'active':($invoice->status==='void'?'suspended':'trial') }}">{{ ucfirst($invoice->status) }}</span></td>
                        <td>{{ number_format($invoice->refunded_minor/100,2) }}</td>
                        <td><a class="btn btn-small btn-outline" href="{{ route('business.billing.show',[$tenant,$invoice]) }}">View →</a></td>
                    </tr>
                @empty <tr><td colspan="6" class="empty-row">No invoice records for this business yet.</td></tr> @endforelse
            </tbody>
        </table></div>
        <div class="pagination-line">{{ $invoices->links() }}</div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Renewal preferences</h2><p class="muted">Not an authorization to charge a bank card.</p></div></div>
        <dl class="detail-list">
            <div><dt>Current plan</dt><dd>{{ $subscription?->plan?->name ?? '—' }}</dd></div>
            <div><dt>Status</dt><dd>{{ ucfirst($subscription?->status ?? 'unassigned') }}</dd></div>
            <div><dt>Next end date</dt><dd>{{ $subscription?->ends_at?->format('d M Y') ?? 'No fixed date' }}</dd></div>
            <div><dt>Renewal reminder preference</dt><dd>{{ $subscription?->auto_renew?'On':'Off' }}</dd></div>
        </dl>
        <form method="POST" action="{{ route('business.billing.renewal',$tenant) }}" class="form-stack" style="margin-top:16px">
            @csrf @method('PUT')
            <label class="check-line"><input type="hidden" name="auto_renew" value="0">
                <input type="checkbox" name="auto_renew" value="1" @checked($subscription?->auto_renew)>
                I want to renew this plan (reminders only; no automatic charging)</label>
            <label class="check-line"><input type="hidden" name="cancel_at_period_end" value="0">
                <input type="checkbox" name="cancel_at_period_end" value="1" @checked($subscription?->cancel_at_period_end)>
                Cancel access when the current paid period ends</label>
            <button class="btn btn-secondary" type="submit">Save preferences</button>
        </form>
        <p class="muted" style="margin-top:15px">Even with renewal preference enabled, subscriptions only extend after a new independently verified payment or an explicitly recorded administrator extension.</p>
    </section>
</div>
@endsection
