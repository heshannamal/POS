@extends('layouts.app')
@section('title','Sales Receipt')
@section('header','Receipt')
@section('content')
<div class="page-head"><div><span class="overline">SALE COMPLETED</span><h1>Receipt #{{ str_pad($sale->id,8,'0',STR_PAD_LEFT) }}</h1><p class="muted">{{ $tenant->name }} · {{ $sale->completed_at?->format('Y-m-d H:i:s') }}</p></div><button class="btn btn-secondary" type="button" onclick="window.print()">Print receipt</button></div>
<section class="panel" style="max-width:680px"><h2>{{ $tenant->name }}</h2><p>{{ $tenant->address }}</p><table class="data-table" style="width:100%"><thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Line total</th></tr></thead><tbody>@foreach($sale->items as $item)<tr><td>{{ $item->name }} ({{ $item->sku }})</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->unit_price_minor/100,2) }}</td><td>{{ number_format($item->line_total_minor/100,2) }}</td></tr>@endforeach</tbody></table>
<p>Subtotal: {{ $sale->currency }} {{ number_format($sale->subtotal_minor/100,2) }}</p><p>Discount: {{ number_format($sale->discount_minor/100,2) }}</p>
<h2>Total: {{ $sale->currency }} {{ number_format($sale->total_minor/100,2) }}</h2>
@foreach($sale->payments as $payment)<p>Payment: {{ str_replace('_',' ',ucfirst($payment->method)) }} — {{ number_format($payment->amount_minor/100,2) }} @if($payment->reference) (Ref: {{ $payment->reference }}) @endif</p>@endforeach
<p class="muted">Thank you for your purchase.</p></section>
<a class="btn btn-primary" href="{{ route('business.pos.terminal',$tenant) }}">New sale</a>
@endsection