@extends('layouts.app')
@section('title','Inventory')
@section('header','Branch inventory')
@section('content')
<div class="page-head"><div><span class="overline">PHASE 7 · INVENTORY</span><h1>Stock management</h1><p class="muted">Every adjustment and transfer writes a permanent stock movement and audit record.</p></div><a class="btn btn-secondary" href="{{ route('business.pos.products',$tenant) }}">Products →</a></div>
<form method="get"><label>Branch <select class="form-control" name="branch_id" onchange="this.form.submit()">@foreach($branches as $item)<option value="{{ $item->id }}" @selected($branch?->id===$item->id)>{{ $item->name }}</option>@endforeach</select></label></form>
@if($branch)
<div class="dashboard-grid">
<section class="panel"><h2>Adjust stock</h2><form method="post" action="{{ route('business.pos.inventory.adjust',$tenant) }}">@csrf
<input type="hidden" name="branch_id" value="{{ $branch->id }}">
<label>Product <select class="form-control" name="product_id">@foreach($stocks as $product)<option value="{{ $product->id }}">{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
<label>Change (+ / −) <input class="form-control" name="change" type="number" min="-1000000" max="1000000" required></label>
<label>Reason <select class="form-control" name="reason">@foreach(['opening','purchase','damage','correction','return'] as $reason)<option>{{ $reason }}</option>@endforeach</select></label>
<label>Note <input class="form-control" name="note"></label><button type="submit" class="btn btn-primary">Record adjustment</button></form></section>
<section class="panel"><h2>Transfer stock</h2><form method="post" action="{{ route('business.pos.inventory.transfer',$tenant) }}">@csrf
<input type="hidden" name="from_branch_id" value="{{ $branch->id }}">
<label>Product <select class="form-control" name="product_id">@foreach($stocks as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label>
<label>Destination <select class="form-control" name="to_branch_id">@foreach($branches as $item)@if($item->id!==$branch->id)<option value="{{ $item->id }}">{{ $item->name }}</option>@endif @endforeach</select></label>
<label>Quantity <input class="form-control" name="quantity" type="number" min="1" required></label><button class="btn btn-secondary">Transfer</button></form></section>
</div>
<section class="panel"><h2>{{ $branch->name }} stock balances</h2><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Product</th><th>SKU</th><th>Quantity</th><th>Reorder level</th></tr></thead><tbody>@forelse($stocks as $product)@php $stock=$product->stocks->first(); @endphp<tr><td>{{ $product->name }}</td><td>{{ $product->sku }}</td><td>{{ $stock?->quantity ?? 0 }}</td><td>{{ $stock?->reorder_level ?? 0 }}</td></tr>@empty<tr><td colspan="4">No tracked products.</td></tr>@endforelse</tbody></table></div>{{ $stocks->appends(['branch_id'=>$branch->id])->links() }}</section>
<section class="panel"><h2>Recent stock movements</h2><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>When</th><th>Product</th><th>Reason</th><th>Change</th><th>Balance</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ $movement->created_at }}</td><td>{{ $movement->product_name }}</td><td>{{ $movement->reason }}</td><td>{{ $movement->change }}</td><td>{{ $movement->balance_after }}</td></tr>@empty<tr><td colspan="5">No stock movements.</td></tr>@endforelse</tbody></table></div></section>
@else <section class="panel">Add a branch before managing inventory.</section> @endif
@endsection