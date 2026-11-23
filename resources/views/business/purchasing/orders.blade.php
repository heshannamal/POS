@extends('layouts.app')
@section('title','Purchase Orders')
@section('header','Purchase Orders')
@section('content')
<div class="page-head"><div><span class="overline">PHASE 8 · PURCHASING</span><h1>Purchase orders</h1><p class="muted">Draft purchase orders must be approved before goods can be received.</p></div><a class="btn btn-secondary" href="{{ route('business.purchasing.suppliers',$tenant) }}">Suppliers →</a></div>
<section class="panel"><h2>Create purchase order</h2><form method="post" action="{{ route('business.purchasing.orders.store',$tenant) }}" id="purchase-create">@csrf
<div class="form-grid"><label>Branch<select name="branch_id" class="form-control" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label><label>Supplier<select name="supplier_id" class="form-control" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label><label>Expected delivery<input type="date" name="expected_date" class="form-control"></label><label>Discount in {{ $tenant->currency }} minor units<input name="discount_minor" type="number" min="0" value="0" class="form-control"></label></div>
<div id="purchase-lines"></div><button type="button" class="btn btn-secondary" id="add-purchase-line">+ Add product</button>
<label>Notes<textarea class="form-control" name="note"></textarea></label><button class="btn btn-primary">Save draft purchase order</button></form></section>
<section class="panel"><h2>Recent purchase orders</h2><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>#</th><th>Supplier</th><th>Branch</th><th>Status</th><th>Total</th><th>Received value</th><th>View</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>#{{ $order->id }}</td><td>{{ $order->supplier_name }}</td><td>{{ $order->branch_name }}</td><td>{{ ucfirst($order->status) }}</td><td>{{ $order->currency }} {{ number_format($order->total_minor/100,2) }}</td><td>{{ number_format($order->received_minor/100,2) }}</td><td><a href="{{ route('business.purchasing.orders.show',[$tenant,$order->id]) }}">Details</a></td></tr>@empty<tr><td colspan="7">No purchase orders.</td></tr>@endforelse
</tbody></table></div>{{ $orders->links() }}</section>
<script>
(() => {
 const products=@json($products->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'sku'=>$p->sku,'cost'=>number_format($p->cost_minor/100,2,'.','')])->values());
 let count=0; const wrap=document.getElementById('purchase-lines');
 document.getElementById('add-purchase-line').addEventListener('click',()=>{
  if(count>=100)return;
  const idx=count++; const box=document.createElement('div');box.className='panel';box.style.marginBottom='12px';
  const select=document.createElement('select');select.name='items['+idx+'][product_id]';select.className='form-control';select.required=true;
  const placeholder=document.createElement('option');placeholder.textContent='Choose product';placeholder.value='';select.append(placeholder);
  for(const p of products){const opt=document.createElement('option');opt.value=p.id;opt.textContent=p.name+' ('+p.sku+')';select.append(opt);}
  const qty=document.createElement('input');qty.type='number';qty.name='items['+idx+'][quantity]';qty.min='1';qty.value='1';qty.required=true;qty.className='form-control';
  const cost=document.createElement('input');cost.type='number';cost.name='items['+idx+'][unit_cost]';cost.step='.01';cost.min='0';cost.required=true;cost.className='form-control';
  select.addEventListener('change',()=>{const p=products.find(x=>String(x.id)===select.value);cost.value=p?.cost||'';});
  const remove=document.createElement('button');remove.type='button';remove.className='btn btn-secondary';remove.textContent='Remove';remove.addEventListener('click',()=>box.remove());
  for(const [title,el] of [['Product',select],['Quantity',qty],['Cost per unit',cost]]){const label=document.createElement('label');label.textContent=title;label.append(el);box.append(label);}
  box.append(remove);wrap.append(box);
 });
 document.getElementById('add-purchase-line').click();
})();
</script>
@endsection