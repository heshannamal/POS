@extends('layouts.app')
@section('title','Returns & Refunds')
@section('header','Returns')
@section('content')
<div class="page-head"><div><span class="overline">POS OPERATIONS</span><h1>Cash refunds & returns</h1><p class="muted">Returns are separate immutable transactions, with optional restocking. A cashier must have an open register.</p></div><a class="btn btn-secondary" href="{{ route('business.pos.sales',$tenant) }}">Find a sale →</a></div>
<section class="panel"><h2>Recorded returns</h2><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Return #</th><th>Sale #</th><th>Amount</th><th>Reason</th><th>Date</th></tr></thead><tbody>
@forelse($returns as $return)<tr><td>#{{ $return->id }}</td><td>#{{ $return->sale_id }}</td><td>{{ $return->currency }} {{ number_format($return->refund_minor/100,2) }}</td><td>{{ $return->reason }}</td><td>{{ $return->created_at }}</td></tr>
@empty<tr><td colspan="5">No refunds yet.</td></tr>@endforelse
</tbody></table></div>{{ $returns->links() }}</section>
@endsection