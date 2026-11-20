@extends('layouts.app')
@section('title','Audit history')
@section('header','Audit history')
@section('content')
<div class="page-head">
    <div><span class="overline">PLATFORM ACCOUNTABILITY</span><h1>Activity & audit log</h1><p class="muted">Review administrative changes, actor identities and business-scoped events.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.audit.export', request()->query()) }}">↓ Export filtered CSV</a>
</div>
<section class="panel">
    <form method="GET" action="{{ route('platform.audit.index') }}" class="filter-row">
        <input type="search" name="action" maxlength="100" value="{{ request('action') }}" placeholder="Action: tenant.*, plan.*, employee.*">
        <input style="max-width:135px" type="number" name="tenant_id" min="1" value="{{ request('tenant_id') }}" placeholder="Business ID">
        <input style="max-width:155px" type="date" name="from" value="{{ request('from') }}" aria-label="From date">
        <input style="max-width:155px" type="date" name="to" value="{{ request('to') }}" aria-label="To date">
        <button class="btn btn-secondary">Filter</button><a class="btn btn-outline" href="{{ route('platform.audit.index') }}">Reset</a>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>When</th><th>Actor</th><th>Business</th><th>Action</th><th>Subject</th><th>IP</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td>{{ $log->created_at?->format('d M Y H:i:s') ?? '—' }}</td>
                <td><strong>{{ $log->actor?->name ?? 'System' }}</strong><small class="table-subtext">{{ $log->actor?->email }}</small></td>
                <td>{{ $log->tenant?->name ?? 'Platform' }}</td>
                <td><span class="status status-trial">{{ $log->action }}</span>
                    @if($log->metadata)<details class="audit-details"><summary>Metadata</summary><code>{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</code></details>@endif
                </td>
                <td>{{ class_basename($log->subject_type ?? '') }} {{ $log->subject_id ? '#'.$log->subject_id : '' }}</td>
                <td>{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty <tr><td colspan="6" class="empty-row">No audit events match the selected filters.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $logs->links() }}</div>
</section>
<p class="muted" style="margin-top:13px">CSV exports are limited to the 5,000 most recent matching records. Audit logs are not editable from this console.</p>
@endsection
