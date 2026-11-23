@extends('layouts.app')
@section('title','POS Reports')
@section('header','POS Reports')
@section('content')
<div class="page-head"><div><span class="overline">RETAIL ANALYTICS</span><h1>Sales & inventory reports</h1><p class="muted">Gross receipts and refunds are shown separately by transaction date. Values in {{ $tenant->currency }}.</p></div><a class="btn btn-secondary" href="{{ route('business.pos.reports.csv',array_merge(['tenant'=>$tenant],$filters)) }}">Export sales CSV</a></div>
<form method="get" class="panel"><div class="form-grid"><label>From <input name="from" type="date" value="{{ $filters['from']??'' }}" class="form-control"></label><label>To <input name="to" type="date" value="{{ $filters['to']??'' }}" class="form-control"></label><label>Branch <select name="branch_id" class="form-control"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id']??null)==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label></div><button class="btn btn-primary">Apply filters</button></form>
<div class="stat-grid">
<div class="stat-card tint-mint"><span class="stat-label">Completed sales</span><strong>{{ $saleCount }}</strong></div>
<div class="stat-card tint-blue"><span class="stat-label">Gross receipts</span><strong>{{ number_format($gross/100,2) }}</strong></div>
<div class="stat-card tint-peach"><span class="stat-label">Refunds</span><strong>{{ number_format($refund/100,2) }}</strong></div>
<div class="stat-card tint-lavender"><span class="stat-label">Net cash receipts</span><strong>{{ number_format(($gross-$refund)/100,2) }}</strong></div>
</div>
<div class="stat-grid"><div class="stat-card"><span class="stat-label">Current stock valuation at recorded costs</span><strong>{{ $tenant->currency }} {{ number_format($stock->valuation_minor/100,2) }}</strong></div><div class="stat-card"><span class="stat-label">Low-stock branch/product rows</span><strong>{{ $stock->low_stock_count }}</strong></div></div>
<div class="dashboard-grid">
<section class="panel"><h2>Sales by branch</h2><table class="data-table" style="width:100%"><thead><tr><th>Branch</th><th>Sales</th><th>Revenue</th></tr></thead><tbody>@foreach($byBranch as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->sale_count }}</td><td>{{ number_format($row->total_minor/100,2) }}</td></tr>@endforeach</tbody></table></section>
<section class="panel"><h2>Sales by cashier</h2><table class="data-table" style="width:100%"><thead><tr><th>Cashier</th><th>Sales</th><th>Revenue</th></tr></thead><tbody>@foreach($byCashier as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->sale_count }}</td><td>{{ number_format($row->total_minor/100,2) }}</td></tr>@endforeach</tbody></table></section>
</div>
<section class="panel"><h2>Top products</h2><p class="muted">Gross sales before refunds. Known-cost totals use snapshots from new sales only; historic missing costs are not estimated.</p><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>SKU</th><th>Product</th><th>Units</th><th>Gross</th><th>Known costs</th></tr></thead><tbody>@forelse($topProducts as $row)<tr><td>{{ $row->sku }}</td><td>{{ $row->name }}</td><td>{{ $row->units }}</td><td>{{ number_format($row->gross_minor/100,2) }}</td><td>{{ number_format($row->known_cost_minor/100,2) }}</td></tr>@empty<tr><td colspan="5">No sales in selected period.</td></tr>@endforelse</tbody></table></div></section>
@endsection