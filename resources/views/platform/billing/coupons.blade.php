@extends('layouts.app')
@section('title','Billing coupons')
@section('header','Discount codes')
@section('content')
@php $canManage=auth()->user()->isPermanentPlatformAdmin() && in_array(auth()->user()->platformRole(),['super_admin','billing'],true); @endphp
<div class="page-head">
    <div><span class="overline">SUBSCRIPTION DISCOUNTS</span><h1>Coupons & discount codes</h1><p class="muted">Percentage or currency-fixed codes with date and usage limits. Coupons only reduce an invoice; they never activate a subscription.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.billing.invoices.index') }}">← Invoices</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Coupon registry</h2><p class="muted">Saved discounts are reserved by pending requests and redeemed only when a payment is approved.</p></div></div>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Code</th><th>Discount</th><th>Used / reserved</th><th>Expiration</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($coupons as $coupon)
                    <tr>
                        <td><strong>{{ $coupon->code }}</strong><small class="table-subtext">{{ $coupon->description }}</small></td>
                        <td>{{ $coupon->discount_type==='percent' ? number_format($coupon->discount_value/100,2).'%' : ($coupon->currency.' '.number_format($coupon->discount_value/100,2)) }}</td>
                        <td>{{ $coupon->redeemed_count }} redeemed · {{ $coupon->reserved_count }} pending</td>
                        <td>{{ $coupon->expires_at?->format('d M Y') ?? 'No expiry' }}</td>
                        <td><span class="status status-{{ $coupon->is_active?'active':'suspended' }}">{{ $coupon->is_active?'Active':'Inactive' }}</span></td>
                        <td>
                            @if($canManage)
                                <form method="POST" action="{{ route('platform.billing.coupons.update',$coupon) }}" class="form-stack">@csrf @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $coupon->is_active?'0':'1' }}">
                                    <input type="hidden" name="expires_at" value="{{ $coupon->expires_at?->format('Y-m-d H:i:s') }}">
                                    <button class="btn btn-small btn-outline" data-confirm="Toggle code availability for future submissions?">{{ $coupon->is_active?'Deactivate':'Activate' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty <tr><td colspan="6" class="empty-row">No discount codes created.</td></tr> @endforelse
            </tbody>
        </table></div>
        <div class="pagination-line">{{ $coupons->links() }}</div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Create coupon</h2><p class="muted">Codes cannot be deleted after use. Set explicit limits.</p></div></div>
        @if($canManage)
            <form method="POST" action="{{ route('platform.billing.coupons.store') }}" class="form-stack">@csrf
                <label>Code</label><input name="code" placeholder="WELCOME10" minlength="3" maxlength="40" required pattern="[A-Za-z0-9_-]+">
                <label>Description</label><input name="description" maxlength="255">
                <label>Discount type</label>
                <select name="discount_type" required><option value="percent">Percentage</option><option value="fixed">Fixed amount</option></select>
                <label>Discount value (e.g. 10.00)</label><input name="value" type="number" min="0.01" max="10000000" step="0.01" required>
                <label>Currency (required for fixed)</label><select name="currency"><option value="">Percentage / any</option><option value="LKR">LKR</option><option value="USD">USD</option></select>
                <label>Total usage limit (optional)</label><input name="max_uses" type="number" min="1">
                <label>Max uses per business</label><input name="max_uses_per_tenant" type="number" min="1" value="1" required>
                <label>Starts at (optional)</label><input name="starts_at" type="datetime-local">
                <label>Expires at (optional)</label><input name="expires_at" type="datetime-local">
                <input type="hidden" name="is_active" value="0"><label class="check-line"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <button type="submit" class="btn btn-primary">Create coupon →</button>
            </form>
        @else
            <p class="muted">Only permanent Billing and Super Administrators can change discount codes.</p>
        @endif
    </section>
</div>
@endsection
