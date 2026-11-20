@extends('layouts.app')
@section('title','Payment reviews')
@section('header','Subscription payment reviews')
@section('content')
<div class="page-head">
    <div><span class="overline">BILLING OPERATIONS</span><h1>Verify submitted payments</h1><p class="muted">Only approve after independent verification of the actual bank transfer or confirmed card capture.</p></div>
    <a href="{{ route('platform.payments.index') }}" class="btn btn-outline">← Payment setup</a>
</div>
<div class="stat-grid">
    <div class="stat-card tint-peach"><span class="stat-icon">◷</span><span class="stat-label">Pending</span><strong>{{ $stats['pending'] }}</strong><small>Needs review</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Approved</span><strong>{{ $stats['approved'] }}</strong><small>Verified manually</small></div>
    <div class="stat-card tint-lavender"><span class="stat-icon">×</span><span class="stat-label">Rejected</span><strong>{{ $stats['rejected'] }}</strong><small>Not activated</small></div>
</div>
<section class="panel">
    <form method="GET" action="{{ route('platform.payments.review.index') }}" class="filter-row">
        <input type="search" name="search" placeholder="Business or transaction reference..." value="{{ request('search') }}">
        <select name="status" aria-label="Payment status">
            <option value="">All statuses</option>
            @foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $code=>$name)<option value="{{ $code }}" @selected(request('status')===$code)>{{ $name }}</option>@endforeach
        </select>
        <select name="method" aria-label="Payment method">
            <option value="">All methods</option><option value="asset" @selected(request('method')==='asset')>Uploaded proof</option><option value="card" @selected(request('method')==='card')>Card</option>
        </select>
        <button class="btn btn-secondary">Filter</button>
        <a href="{{ route('platform.payments.review.index') }}" class="btn btn-outline">Reset</a>
    </form>
    <div class="table-scroll">
        <table class="data-table"><thead><tr><th>Request</th><th>Business</th><th>Plan</th><th>Method</th><th>Payable</th><th>Verified</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td><strong>#{{ str_pad($payment->id,6,'0',STR_PAD_LEFT) }}</strong><small class="table-subtext">{{ $payment->customer_reference ?: 'No customer reference' }}</small></td>
                    <td><strong>{{ $payment->tenant?->name ?? '—' }}</strong><small class="table-subtext">{{ $payment->submitter?->email }}</small></td>
                    <td>{{ $payment->plan?->name }}</td>
                    <td>{{ $payment->method === 'asset' ? 'Proof upload' : 'Card' }}</td>
                    <td>{{ $payment->currency }} {{ number_format($payment->requested_amount_minor/100,2) }}</td>
                    <td>{{ $payment->approved_amount_minor !== null ? $payment->currency.' '.number_format($payment->approved_amount_minor/100,2) : 'Not verified' }}</td>
                    <td><span class="status status-{{ $payment->status === 'approved' ? 'active' : ($payment->status === 'rejected' ? 'suspended' : 'trial') }}">{{ ucfirst($payment->status) }}</span></td>
                    <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                    <td><a class="btn btn-small btn-outline" href="{{ route('platform.payments.review.show',$payment) }}">Open →</a></td>
                </tr>
            @empty <tr><td colspan="9" class="empty-row">No payment requests match the filters.</td></tr>
            @endforelse
        </tbody></table>
    </div>
    <div class="pagination-line">{{ $payments->links() }}</div>
</section>
@endsection
