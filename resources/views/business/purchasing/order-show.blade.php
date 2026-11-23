@extends('layouts.app')
@section('title','Purchase Order')
@section('header','Purchase Order #'.$purchase->id)
@section('content')
<div class="page-head"><div><span class="overline">PURCHASE ORDER</span><h1>PO #{{ $purchase->id }} · {{ ucfirst($purchase->status) }}</h1><p class="muted">{{ $purchase->supplier_name }} · {{ $purchase->branch_name }} · {{ $purchase->currency }} {{ number_format($purchase->total_minor/100,2) }}</p></div><button class="btn btn-secondary" onclick="window.print()">Print PO</button></div>
<section class="panel"><h2>Purchased products</h2><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Unit cost</th><th>Line total</th></tr></thead><tbody>@foreach($items as $item)<tr><td>{{ $item->name }} · {{ $item->sku }}</td><td>{{ $item->quantity }}</td><td>{{ $item->received_quantity }}</td><td>{{ number_format($item->unit_cost_minor/100,2) }}</td><td>{{ number_format($item->line_total_minor/100,2) }}</td></tr>@endforeach</tbody></table></div>
<p>Subtotal {{ number_format($purchase->subtotal_minor/100,2) }} · Discount {{ number_format($purchase->discount_minor/100,2) }} · <strong>Total {{ $purchase->currency }} {{ number_format($purchase->total_minor/100,2) }}</strong></p>
<p>Goods received value: {{ number_format($purchase->received_minor/100,2) }} · Supplier payments recorded: {{ number_format($paid/100,2) }} · <strong>Due on received goods: {{ number_format(($purchase->received_minor-$paid)/100,2) }}</strong></p><p class="muted">{{ $purchase->note }}</p>
@if($purchase->status==='draft')
@if(app(\App\Services\TenantAuthorization::class)->allows(auth()->user(),$tenant,'pos.purchases.manage'))
<form method="post" action="{{ route('business.purchasing.orders.approve',[$tenant,$purchase->id]) }}">@csrf<button class="btn btn-primary" onclick="return confirm('Approve purchase order?')">Approve PO</button></form>
<form method="post" action="{{ route('business.purchasing.orders.cancel',[$tenant,$purchase->id]) }}">@csrf<button class="btn btn-secondary">Cancel draft</button></form>@endif
@endif
@if($purchase->status==='approved' && app(\App\Services\TenantAuthorization::class)->allows(auth()->user(),$tenant,'pos.purchases.manage'))
<form method="post" action="{{ route('business.purchasing.orders.cancel',[$tenant,$purchase->id]) }}">@csrf<button class="btn btn-secondary">Cancel unreceived PO</button></form>@endif
</section>
@if(in_array($purchase->status,['approved','partial']) && app(\App\Services\TenantAuthorization::class)->allows(auth()->user(),$tenant,'pos.receiving.manage'))
<section class="panel"><h2>Goods receiving (GRN)</h2><form method="post" action="{{ route('business.purchasing.receipts.store',[$tenant,$purchase->id]) }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>Delivery note / invoice reference<input name="reference" class="form-control" maxlength="120"></label>
@foreach($items as $i=>$item)@php $remaining=$item->quantity-$item->received_quantity; @endphp
@if($remaining>0)<label>{{ $item->name }} · outstanding {{ $remaining }}<input type="hidden" name="items[{{ $i }}][order_item_id]" value="{{ $item->id }}"><input type="number" name="items[{{ $i }}][quantity]" class="form-control" min="0" max="{{ $remaining }}" value="0" required></label>@endif
@endforeach<button class="btn btn-primary" onclick="return confirm('Post receipt and add goods to inventory?')">Receive goods</button></form></section>
@endif
@if($purchase->received_minor>$paid && app(\App\Services\TenantAuthorization::class)->allows(auth()->user(),$tenant,'pos.payables.manage'))
<section class="panel"><h2>Record supplier payment</h2><p class="muted">Record only externally confirmed payments. This form does not initiate bank or card transfers.</p>
<form method="post" action="{{ route('business.purchasing.payments.store',[$tenant,$purchase->id]) }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>Amount {{ $purchase->currency }}<input class="form-control" name="amount" type="number" min=".01" step=".01" max="{{ number_format(($purchase->received_minor-$paid)/100,2,'.','') }}" required></label>
<label>Method<select class="form-control" name="method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="external_card">External card</option><option value="other">Other</option></select></label>
<label>Payment reference<input name="reference" class="form-control" maxlength="120" required></label><button class="btn btn-primary">Record payment</button></form></section>
@endif
<section class="panel"><h2>Goods receipts & payments</h2><h3>Goods receipts</h3>@forelse($receipts as $receipt)<p>GRN #{{ $receipt->id }} · {{ $receipt->reference }} · {{ $receipt->received_at }}</p>@empty<p class="muted">No goods received yet.</p>@endforelse
<h3>Payments</h3>@forelse($payments as $payment)<p>{{ $payment->method }} · {{ $purchase->currency }} {{ number_format($payment->amount_minor/100,2) }} · {{ $payment->reference }} · {{ $payment->paid_at }}</p>@empty<p class="muted">No payments recorded.</p>@endforelse</section>
@endsection