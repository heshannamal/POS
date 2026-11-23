@extends('layouts.app')
@section('title','Sales Receipt')
@section('header','Receipt')
@section('content')
<div class="page-head"><div><span class="overline">SALE COMPLETED</span><h1>Receipt #{{ str_pad($sale->id,8,'0',STR_PAD_LEFT) }}</h1><p class="muted">{{ $tenant->name }} · {{ $sale->completed_at?->format('Y-m-d H:i:s') }}</p></div><button class="btn btn-secondary" type="button" onclick="window.print()">Print receipt</button></div>
<style>
@media print {
  @page { size: 80mm auto; margin: 4mm; }
  body { background:#fff !important; color:#111 !important; font-family:Arial, sans-serif; }
  .sidebar,.topbar,.page-head,.btn,.topbar-actions { display:none !important; }
  .app-shell,.main-area,.page-content { display:block !important; margin:0 !important; padding:0 !important; width:100% !important; }
  #pos-thermal-receipt { width:72mm !important; max-width:72mm !important; margin:0 auto !important; padding:0 !important; border:0 !important; box-shadow:none !important; background:#fff !important; color:#111 !important; font-size:10px; }
  #pos-thermal-receipt table { width:100%; font-size:10px; border-collapse:collapse; }
  #pos-thermal-receipt td,#pos-thermal-receipt th { padding:3px 1px !important; }
}
</style>
<section id="pos-thermal-receipt" class="panel" style="max-width:680px"><h2>{{ $tenant->name }}</h2><p>{{ $tenant->address }}</p><table class="data-table" style="width:100%"><thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Line total</th></tr></thead><tbody>@foreach($sale->items as $item)<tr><td>{{ $item->name }} ({{ $item->sku }})</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->unit_price_minor/100,2) }}</td><td>{{ number_format($item->line_total_minor/100,2) }}</td></tr>@endforeach</tbody></table>
<p>Subtotal: {{ $sale->currency }} {{ number_format($sale->subtotal_minor/100,2) }}</p><p>Discount: {{ number_format($sale->discount_minor/100,2) }}</p>
<h2>Total: {{ $sale->currency }} {{ number_format($sale->total_minor/100,2) }}</h2>
@foreach($sale->payments as $payment)<p>Payment: {{ str_replace('_',' ',ucfirst($payment->method)) }} — {{ number_format($payment->amount_minor/100,2) }} @if($payment->reference) (Ref: {{ $payment->reference }}) @endif</p>@endforeach
<p class="muted">Thank you for your purchase.</p></section>
<a class="btn btn-primary" href="{{ route('business.pos.terminal',$tenant) }}">New sale</a>
@if(app(\App\Services\TenantAuthorization::class)->allows(auth()->user(),$tenant,'pos.returns.manage'))
<a class="btn btn-secondary" href="{{ route('business.pos.returns.create',[$tenant,$sale]) }}">Return items</a>
@endif
@endsection