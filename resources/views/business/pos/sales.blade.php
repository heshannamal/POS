@extends('layouts.app')
@section('title','Sales History')
@section('header','Sales')
@section('content')
<div class="page-head"><div><span class="overline">PHASE 7 · TRANSACTIONS</span><h1>Sales history</h1><p class="muted">Completed sales are immutable and tracked by tenant.</p></div><a class="btn btn-primary" href="{{ route('business.pos.terminal',$tenant) }}">Open terminal</a></div>
<section class="panel"><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Sale</th><th>Date</th><th>Items</th><th>Payment</th><th>Total</th><th>Receipt</th></tr></thead><tbody>
@forelse($sales as $sale)<tr><td>#{{ str_pad($sale->id,8,'0',STR_PAD_LEFT) }}</td><td>{{ $sale->completed_at?->format('Y-m-d H:i') }}</td><td>{{ $sale->items->sum('quantity') }}</td><td>{{ $sale->payments->pluck('method')->join(', ') }}</td><td>{{ $sale->currency }} {{ number_format($sale->total_minor/100,2) }}</td><td><a href="{{ route('business.pos.receipt',[$tenant,$sale]) }}">View receipt</a></td></tr>
@empty<tr><td colspan="6">No sales recorded.</td></tr>@endforelse</tbody></table></div>{{ $sales->links() }}</section>
@endsection