@extends('layouts.app')
@section('title', 'Payment setup')
@section('header', 'Payment setup')
@section('content')
<div class="page-head">
    <div>
        <span class="overline">SUBSCRIPTION PAYMENT SETTINGS</span>
        <h1>Accepted payment methods</h1>
        <p class="muted">Choose which methods customers can use for platform subscriptions. Every submitted payment requires an explicit administrator review.</p>
    </div>
    <a href="{{ route('platform.payments.review.index') }}" class="btn btn-primary">Review payment requests →</a>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender">
        <span class="stat-icon">▤</span><span class="stat-label">Pending payment reviews</span>
        <strong>{{ number_format($pendingCount) }}</strong><small>Awaiting manual verification</small>
    </div>
    <div class="stat-card tint-mint">
        <span class="stat-icon">✓</span><span class="stat-label">Approved requests</span>
        <strong>{{ number_format($approvedCount) }}</strong><small>Recorded administrator decisions</small>
    </div>
    <div class="stat-card tint-blue">
        <span class="stat-icon">◇</span><span class="stat-label">Available payment methods</span>
        <strong>{{ $methods->filter(fn($method) => $method->is_operational)->count() }}</strong>
        <small>Currently functional for customers</small>
    </div>
    <div class="stat-card tint-peach">
        <span class="stat-icon">!</span><span class="stat-label">Card gateway</span>
        <strong class="stat-text">Not integrated</strong>
        <small>Never collect raw card information</small>
    </div>
</div>
<div class="dashboard-grid">
    @foreach($methods as $method)
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>{{ $method->code === 'card' ? 'Card payment gateway' : 'Upload payment proof / asset' }}</h2>
                    <p class="muted">{{ $method->code === 'card' ? 'Card payment activation preference' : 'Bank transfer receipt / payment evidence upload' }}</p>
                </div>
                <span class="status status-{{ $method->is_operational ? 'active' : 'trial' }}">{{ $method->is_operational ? 'Available' : ($method->is_enabled ? 'Enabled • integration pending' : 'Disabled') }}</span>
            </div>
            @if($method->code === 'card')
                <div class="hint-box">
                    Card payment collection is not yet connected to an approved provider. Switching this preference on saves your intent, but cannot expose an unverified checkout or activate card charges. Provider checkout, signed callbacks and capture verification must be implemented before it becomes operational.
                </div>
            @else
                <div class="hint-box">
                    Customers submit a JPG, PNG or PDF payment receipt (maximum 5 MB). Receipts are stored privately. The billing administrator must check the actual bank transaction, enter the verified amount and a unique transaction reference, then approve or reject.
                </div>
            @endif
            @if(auth()->user()->isPermanentPlatformAdmin() && auth()->user()->platformRole() === 'super_admin')
                <form method="POST" action="{{ route('platform.payments.methods.update', $method) }}" class="form-stack">
                    @csrf @method('PUT')
                    <label for="enabled-{{ $method->id }}">Method availability preference</label>
                    <select name="is_enabled" id="enabled-{{ $method->id }}">
                        <option value="0" @selected(!$method->is_enabled)>Inactive / Off</option>
                        <option value="1" @selected($method->is_enabled)>Active / On</option>
                    </select>
                    <label for="instructions-{{ $method->id }}">{{ $method->code === 'asset' ? 'Payment instructions shown to customers' : 'Card gateway information (non-secret notes only)' }}</label>
                    <textarea id="instructions-{{ $method->id }}" name="instructions" rows="5" maxlength="3000" placeholder="{{ $method->code === 'asset' ? 'Bank name, account holder, payment reference guidance...' : 'Payment provider name or integration notes. Never paste API keys here.' }}">{{ old('instructions', $method->instructions) }}</textarea>
                    <button class="btn btn-secondary" type="submit">Save {{ $method->name }} settings</button>
                </form>
            @else
                <p class="muted">Only a permanent Super Administrator can change payment method availability. Current preference: {{ $method->is_enabled ? 'On' : 'Off' }}.</p>
            @endif
        </section>
    @endforeach
</div>
<section class="panel" style="margin-top:18px">
    <div class="panel-heading">
        <div><h2>Latest subscription payment submissions</h2><p class="muted">Amounts in this table are customer-requested amounts, not automatically verified payments.</p></div>
        <a class="text-link" href="{{ route('platform.payments.review.index') }}">All payment reviews →</a>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Reference</th><th>Business</th><th>Plan</th><th>Method</th><th>Payable amount</th><th>Status</th><th>Created</th><th></th></tr></thead>
            <tbody>
            @forelse($recent as $payment)
                <tr>
                    <td><strong>#{{ str_pad($payment->id,6,'0',STR_PAD_LEFT) }}</strong></td>
                    <td>{{ $payment->tenant?->name }}</td>
                    <td>{{ $payment->plan?->name }}</td>
                    <td>{{ $payment->method === 'asset' ? 'Uploaded proof' : 'Card' }}</td>
                    <td>{{ $payment->currency }} {{ number_format($payment->requested_amount_minor/100, 2) }}</td>
                    <td><span class="status status-{{ $payment->status === 'approved' ? 'active' : ($payment->status === 'rejected' ? 'suspended' : 'trial') }}">{{ ucfirst($payment->status) }}</span></td>
                    <td>{{ $payment->created_at->format('d M Y') }}</td>
                    <td><a class="btn btn-small btn-outline" href="{{ route('platform.payments.review.show',$payment) }}">Review →</a></td>
                </tr>
            @empty <tr><td colspan="8" class="empty-row">No payment requests have been submitted.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="hint-box" style="margin-top:16px">This workflow is for POS Cloud subscription fees only. Customer retail card payments and merchant settlements require a separate approved acquiring, ledger and payout system.</div>
@endsection
