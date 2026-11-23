@extends('layouts.app')
@section('title','Product catalog')
@section('header','Products & Categories')
@section('content')
<div class="page-head"><div><span class="overline">PHASE 7 · CATALOG</span><h1>Products</h1><p class="muted">Tenant-scoped products with SKU, barcode, pricing and categories.</p></div><a class="btn btn-secondary" href="{{ route('business.pos.inventory',$tenant) }}">Manage stock →</a></div>
<div class="dashboard-grid">
<section class="panel">
<h2>Add category</h2>
<form method="post" action="{{ route('business.pos.categories.store',$tenant) }}">@csrf
<label>Name <input class="form-control" name="name" required maxlength="120"></label>
<button class="btn btn-primary" type="submit">Save category</button></form>
</section>
<section class="panel panel-wide">
<h2>Add product</h2>
<form method="post" action="{{ route('business.pos.products.store',$tenant) }}">@csrf
<div class="form-grid">
<label>Name <input class="form-control" name="name" required></label>
<label>SKU <input class="form-control" name="sku" required></label>
<label>Barcode <input class="form-control" name="barcode"></label>
<label>Brand <input class="form-control" name="brand"></label>
<label>Category <select class="form-control" name="category_id"><option value="">Uncategorized</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
<label>Cost ({{ $tenant->currency }}) <input class="form-control" name="cost" type="number" min="0" step=".01" value="0" required></label>
<label>Price ({{ $tenant->currency }}) <input class="form-control" name="price" type="number" min="0" step=".01" required></label>
<label>Stock tracking <select class="form-control" name="track_stock"><option value="1">Enabled</option><option value="0">Disabled (service)</option></select></label>
<input type="hidden" name="is_active" value="1">
</div><label>Description <textarea name="description" class="form-control"></textarea></label>
<button type="submit" class="btn btn-primary">Add product</button></form>
</section></div>
<section class="panel"><div class="panel-heading"><h2>Product directory</h2><form method="get"><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, SKU or barcode"><button class="btn btn-secondary">Search</button></form></div>
<div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($products as $product)
<tr><td><strong>{{ $product->name }}</strong><br><small>{{ $product->barcode }}</small></td><td>{{ $product->sku }}</td><td>{{ $product->category?->name ?? '—' }}</td><td>{{ $tenant->currency }} {{ number_format($product->price_minor/100,2) }}</td><td>{{ $product->is_active?'Active':'Inactive' }}</td><td>
<details><summary>Edit</summary><form method="post" action="{{ route('business.pos.products.update',[$tenant,$product]) }}">@csrf @method('PUT')
<input name="name" value="{{ $product->name }}" required placeholder="Name" class="form-control">
<input name="sku" value="{{ $product->sku }}" required placeholder="SKU" class="form-control">
<input name="barcode" value="{{ $product->barcode }}" placeholder="Barcode" class="form-control">
<input name="brand" value="{{ $product->brand }}" placeholder="Brand" class="form-control">
<textarea name="description" class="form-control">{{ $product->description }}</textarea>
<select name="category_id" class="form-control"><option value="">Uncategorized</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($product->category_id===$category->id)>{{ $category->name }}</option>@endforeach</select>
<label>Cost <input name="cost" type="number" step=".01" min="0" value="{{ number_format($product->cost_minor/100,2,'.','') }}" required></label>
<label>Price <input name="price" type="number" step=".01" min="0" value="{{ number_format($product->price_minor/100,2,'.','') }}" required></label>
<label>Track stock <select name="track_stock"><option value="1" @selected($product->track_stock)>Yes</option><option value="0" @selected(!$product->track_stock)>No</option></select></label>
<label>Active <select name="is_active"><option value="1" @selected($product->is_active)>Yes</option><option value="0" @selected(!$product->is_active)>No</option></select></label>
<button class="btn btn-primary" type="submit">Save changes</button></form>
<form method="post" action="{{ route('business.pos.products.archive',[$tenant,$product]) }}" onsubmit="return confirm('Archive product? Previous sales will remain.');">@csrf @method('DELETE')<button class="btn btn-secondary">Archive</button></form></details></td></tr>
@empty<tr><td colspan="6">No products yet.</td></tr>@endforelse
</tbody></table></div>{{ $products->links() }}</section>
@endsection