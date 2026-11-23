@extends('layouts.app')
@section('title','Return Sale')
@section('header','Return & Refund')
@section('content')
<div class="page-head"><div><span class="overline">CASH REFUND</span><h1>Return from sale #{{ $sale->id }}</h1><p class="muted">Choose quantities sold; returns cannot exceed the original quantities. Discounts are allocated proportionally.</p></div><a class="btn btn-secondary" href="{{ route('business.pos.receipt',[$tenant,$sale]) }}">Back to receipt</a></div>
<section class="panel">
@if($shifts->isEmpty())<div class="flash-error">Open your own register shift in this sale's branch before refunding. <a href="{{ route('business.pos.registers',$tenant) }}">Registers</a></div>@endif
<form method="post" action="{{ route('business.pos.returns.store',[$tenant,$sale]) }}" class="form-stack">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label>Active shift <select name="shift_id" class="form-control" required>@foreach($shifts as $shift)<option value="{{ $shift->id }}">#{{ $shift->id }} — {{ $shift->name }}</option>@endforeach</select></label>
<table class="data-table" style="width:100%"><thead><tr><th>Item</th><th>Sold</th><th>Returned already</th><th>Return now</th><th>Restock?</th></tr></thead><tbody>
@foreach($sale->items as $i=>$item)
@php $remaining=max(0,$item->quantity-(int)($returned[$item->id]??0)); @endphp
<tr><td>{{ $item->name }} ({{ $item->sku }})</td><td>{{ $item->quantity }}</td><td>{{ $returned[$item->id]??0 }}</td>
<td><input type="hidden" name="items[{{ $i }}][sale_item_id]" value="{{ $item->id }}"><input class="form-control" type="number" min="0" max="{{ $remaining }}" name="items[{{ $i }}][quantity]" value="0" required></td>
<td><input type="hidden" name="items[{{ $i }}][restock]" value="0"><input type="checkbox" name="items[{{ $i }}][restock]" value="1" aria-label="Restock {{ $item->name }}"></td></tr>
@endforeach</tbody></table>
<label>Reason <textarea class="form-control" name="reason" maxlength="500" required placeholder="Customer return reason"></textarea></label>
<p class="muted">Only cash refunds are supported. The server computes the exact refund and checks register cash availability.</p>
<button class="btn btn-primary" @disabled($shifts->isEmpty()) onclick="return confirm('Confirm the cash refund and inventory changes?')">Issue cash refund</button></form></section>
@endsection