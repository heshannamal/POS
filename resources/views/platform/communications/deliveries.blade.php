@extends('layouts.app')
@section('title','Email Delivery Logs')
@section('header','Email deliveries')
@section('content')
<div class="page-head">
    <div><span class="overline">MESSAGE DELIVERY HISTORY</span><h1>Email delivery queue</h1><p class="muted">Audit outgoing email attempts, transport acceptance, test redirects, failures and retries.</p></div>
    <div class="page-actions">
        <a class="btn btn-outline" href="{{ route('platform.communications.settings.index') }}">Email settings</a>
        <a class="btn btn-secondary" href="{{ route('platform.communications.templates.index') }}">Templates →</a>
    </div>
</div>
<div class="stat-grid">
    <div class="stat-card tint-blue"><span class="stat-icon">◷</span><span class="stat-label">Queued / processing</span><strong>{{ number_format($stats['queued']) }}</strong><small>Awaiting worker / retry</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Accepted / logged</span><strong>{{ number_format($stats['accepted']) }}</strong><small>Not guaranteed delivered</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">!</span><span class="stat-label">Failed</span><strong>{{ number_format($stats['failed']) }}</strong><small>Eligible for manual retry</small></div>
</div>
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>Delivery records</h2><p class="muted">Encrypted email bodies are not exposed in log lists.</p></div></div>
    <form method="GET" action="{{ route('platform.communications.deliveries.index') }}" class="tenant-filters">
        <label>Recipient email<input type="search" name="search" maxlength="120" value="{{ request('search') }}" placeholder="recipient@example.com"></label>
        <label>Status<select name="status">
            <option value="">All</option>
            @foreach(['queued','sending','retrying','accepted','logged','failed','skipped'] as $code)<option value="{{ $code }}" @selected(request('status')===$code)>{{ ucfirst($code) }}</option>@endforeach
        </select></label>
        <label>Event<select name="event_key"><option value="">All events</option>
            @foreach(config('communications.events',[]) as $key=>$value)<option value="{{ $key }}" @selected(request('event_key')===$key)>{{ $value['name'] }}</option>@endforeach
        </select></label>
        <div class="tenant-filter-actions"><button class="btn btn-secondary">Filter</button><a class="btn btn-outline" href="{{ route('platform.communications.deliveries.index') }}">Clear</a></div>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>When</th><th>Event</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Attempts / transport</th><th></th></tr></thead>
        <tbody>
            @forelse($deliveries as $delivery)
                <tr>
                    <td>{{ $delivery->created_at?->format('d M Y H:i') }}</td>
                    <td>{{ $delivery->event_key }}</td>
                    <td><strong>{{ $delivery->recipient_email }}</strong>@if($delivery->original_email)<small class="table-subtext">Test redirect for: {{ $delivery->original_email }}</small>@endif</td>
                    <td>{{ \Illuminate\Support\Str::limit($delivery->subject,65) }}</td>
                    <td><span class="status status-{{ in_array($delivery->status,['accepted','logged'])?'active':($delivery->status==='failed'?'suspended':'trial') }}">{{ ucfirst($delivery->status) }}</span>@if($delivery->last_error)<small class="table-subtext">{{ \Illuminate\Support\Str::limit($delivery->last_error,130) }}</small>@endif</td>
                    <td>{{ $delivery->attempts }} · {{ $delivery->transport ?? '—' }}</td>
                    <td>@if(in_array($delivery->status,['failed','skipped']))
                        <form method="POST" action="{{ route('platform.communications.deliveries.retry',$delivery) }}">@csrf
                            <button class="btn btn-small btn-outline" type="submit" data-confirm="Retry this exact recipient and message snapshot?">Retry</button>
                        </form>@else — @endif
                    </td>
                </tr>
            @empty <tr><td colspan="7" class="empty-row">No email delivery records yet. Email must be enabled, and a recipient rule must permit it.</td></tr>@endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $deliveries->links() }}</div>
</section>
@endsection
