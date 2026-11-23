@extends('layouts.app')
@section('title','Billing reports')
@section('header','Subscription billing reports')
@section('content')
<div class="page-head">
    <div><span class="overline">FINANCIAL ANALYTICS</span><h1>Billing & revenue overview</h1><p class="muted">Actual revenue reflects verified paid invoices, less externally verified refunds. Separate currencies are never summed together.</p></div>
    <div class="page-actions">
        <a class="btn btn-outline" href="{{ route('platform.billing.reports.export',array_merge(request()->query(),['mode'=>'summary'])) }}">Summary CSV ↓</a>
        <a class="btn btn-secondary" href="{{ route('platform.billing.reports.export',array_merge(request()->query(),['mode'=>'ledger'])) }}">Invoice Ledger CSV ↓</a>
    </div>
</div>
<section class="panel">
    <div class="panel-heading"><div><h2>Report period</h2><p class="muted">Filter by invoice issue date, not settlement date.</p></div></div>
    <form method="GET" class="tenant-filters">
        <label>From<input name="from" type="date" value="{{ request('from') }}"></label>
        <label>To<input name="to" type="date" value="{{ request('to') }}"></label>
        <div class="tenant-filter-actions"><button class="btn btn-secondary">Apply filter</button><a class="btn btn-outline" href="{{ route('platform.billing.reports.index') }}">Reset</a></div>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Currency</th><th>Issued</th><th>Paid</th><th>Verified paid</th><th>Refunds</th><th>Net verified</th><th>Awaiting verification</th></tr></thead>
        <tbody>
        @foreach($summary as $row)
            <tr>
                <td><strong>{{ $row['currency'] }}</strong></td>
                <td>{{ $row['issued_count'] }}</td><td>{{ $row['paid_count'] }}</td>
                <td>{{ number_format($row['gross_minor']/100,2) }}</td>
                <td>{{ number_format($row['refunded_minor']/100,2) }}</td>
                <td><strong>{{ number_format(($row['gross_minor']-$row['refunded_minor'])/100,2) }}</strong></td>
                <td>{{ number_format($row['unpaid_minor']/100,2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@foreach(['Subscription plans'=>$byPlan,'Businesses'=>$byTenant,'Monthly revenue'=>$byMonth] as $heading=>$rows)
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>{{ $heading }}</h2><p class="muted">Paid invoice totals less independently verified refunds, separated by currency.</p></div></div>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>{{ $heading==='Monthly revenue'?'Month':'Name' }}</th><th>Currency</th><th>Paid invoices</th><th>Gross paid</th><th>Refunded</th><th>Net</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr><td><strong>{{ $row['name'] }}</strong></td><td>{{ $row['currency'] }}</td><td>{{ $row['count'] }}</td>
                    <td>{{ number_format($row['gross_minor']/100,2) }}</td>
                    <td>{{ number_format($row['refunded_minor']/100,2) }}</td>
                    <td><strong>{{ number_format($row['net_minor']/100,2) }}</strong></td>
                </tr>
            @empty <tr><td colspan="6" class="empty-row">No paid invoices in this report period.</td></tr> @endforelse
        </tbody>
    </table></div>
</section>
@endforeach
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>Latest invoices</h2><p class="muted">Individual documents and payment verification history.</p></div></div>
    <div class="stack-list">
        @forelse($recent as $invoice)
            <div class="stack-row">
                <span class="circle-initial">▤</span>
                <span class="stack-grow"><strong>{{ $invoice->number }} · {{ $invoice->tenant?->name }}</strong>
                    <small>{{ $invoice->currency }} {{ number_format($invoice->total_minor/100,2) }} · {{ ucfirst($invoice->status) }}</small>
                </span>
                <a class="btn btn-small btn-outline" href="{{ route('platform.billing.invoices.show',$invoice) }}">Details →</a>
            </div>
        @empty <p class="muted empty-box">No invoices yet.</p>@endforelse
    </div>
</section>
<p class="muted" style="margin-top:15px">Both CSV exports use integer minor units to avoid rounding in Excel or other spreadsheet software. A native .xlsx exporter is planned for advanced analytics.</p>
@endsection
