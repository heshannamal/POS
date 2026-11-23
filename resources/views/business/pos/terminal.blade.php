@extends('layouts.app')
@section('title','POS Terminal')
@section('header','Point of Sale')
@section('content')
<div class="page-head"><div><span class="overline">PHASE 7 · SALES</span><h1>Sales terminal</h1><p class="muted">Stock and prices are rechecked by the server on checkout. Amounts are in {{ $tenant->currency }}.</p></div><a class="btn btn-secondary" href="{{ route('business.pos.sales',$tenant) }}">Sales history →</a></div>
<form method="get" class="panel"><label>Branch <select name="branch_id" class="form-control">@foreach($branches as $store)<option value="{{ $store->id }}" @selected($branch?->id===$store->id)>{{ $store->name }}</option>@endforeach</select></label><label>Search name / SKU / barcode <input name="search" class="form-control" value="{{ request('search') }}"></label><button class="btn btn-secondary">Search</button></form>
@if($branch)
<div class="dashboard-grid">
<section class="panel panel-wide"><h2>Available products</h2><div class="stat-grid">
@forelse($products as $product)
<div class="stat-card"><strong>{{ $product->name }}</strong><small>{{ $product->sku }} · {{ $product->barcode }}</small><p>{{ $tenant->currency }} {{ number_format($product->price_minor/100,2) }}</p><small>{{ $product->track_stock?'Stock: '.($product->stocks->first()?->quantity??0):'Service / untracked' }}</small>
<button type="button" class="btn btn-secondary js-add-product"
 data-product="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->price_minor }}"
 @disabled($product->track_stock && ($product->stocks->first()?->quantity??0)<=0)>Add to cart</button></div>
@empty<p class="muted">No matching active products.</p>@endforelse
</div></section>
<section class="panel"><h2>Cart</h2>
<form method="post" action="{{ route('business.pos.checkout',$tenant) }}" id="pos-checkout">@csrf
<input type="hidden" name="branch_id" value="{{ $branch->id }}">
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<div id="pos-cart-lines"><p class="muted">Your cart is empty.</p></div>
<p><strong>Subtotal: {{ $tenant->currency }} <span id="pos-total">0.00</span></strong></p>
<input type="hidden" name="discount_minor" value="0">
<label>Payment method <select name="payment_method" class="form-control"><option value="cash">Cash</option></select></label>
<p class="muted">Cash-only in this foundation release. External payments require a verified integration.</p>
<button class="btn btn-primary" type="submit" id="pos-pay" disabled>Complete sale</button></form></section>
</div>
<script>
(() => {
 const cart = new Map(), lines = document.getElementById('pos-cart-lines'), total = document.getElementById('pos-total');
 const pay = document.getElementById('pos-pay'), form = document.getElementById('pos-checkout');
 document.querySelectorAll('.js-add-product').forEach(btn => btn.addEventListener('click', () => {
  const id=btn.dataset.product, prev=cart.get(id);
  cart.set(id,{name:btn.dataset.name,price:Number(btn.dataset.price),quantity:(prev?.quantity||0)+1});
  draw();
 }));
 function draw(){
  lines.replaceChildren(); let amount=0,index=0;
  for(const [id,item] of cart){
   const row=document.createElement('div');row.style.marginBottom='12px';
   const name=document.createElement('strong');name.textContent=item.name+' × '+item.quantity;
   const remove=document.createElement('button');remove.type='button';remove.className='btn btn-secondary';remove.textContent='Remove';
   remove.addEventListener('click',()=>{cart.delete(id);draw();});
   const pid=document.createElement('input');pid.type='hidden';pid.name='items['+index+'][product_id]';pid.value=id;
   const qty=document.createElement('input');qty.type='hidden';qty.name='items['+index+'][quantity]';qty.value=item.quantity;
   row.append(name,document.createTextNode(' '),remove,pid,qty);lines.append(row);
   amount+=item.price*item.quantity;index++;
  }
  if(!index){const empty=document.createElement('p');empty.textContent='Your cart is empty.';lines.append(empty);}
  total.textContent=(amount/100).toFixed(2);pay.disabled=!index;
 }
 form.addEventListener('submit',()=>{pay.disabled=true;});
})();
</script>
@else<section class="panel">No active branch. Ask the business administrator to activate a store.</section>@endif
@endsection