@extends('layouts.app')
@section('title','Support inbox')
@section('header','Customer support inbox')
@section('content')
<div class="page-head"><div><span class="overline">CUSTOMER SUCCESS</span><h1>Support desk</h1><p class="muted">Respond to business requests, assign tickets and track resolution. Messages are stored in the platform.</p></div></div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">✉</span><span class="stat-label">Open tickets</span><strong>{{ $stats['open'] }}</strong><small>Awaiting triage</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">↻</span><span class="stat-label">In progress</span><strong>{{ $stats['in_progress'] }}</strong><small>Assigned to team</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">◷</span><span class="stat-label">Waiting on customer</span><strong>{{ $stats['waiting'] }}</strong><small>Replies requested</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Resolved / closed</span><strong>{{ $stats['resolved'] }}</strong><small>All recorded resolutions</small></div>
</div>
<section class="panel">
    <form action="{{ route('platform.support.index') }}" method="GET" class="filter-row">
        <input type="search" name="search" placeholder="Search subject or business..." value="{{ request('search') }}">
        <select name="status">
            <option value="">All statuses</option>
            @foreach(['open'=>'Open','in_progress'=>'In progress','waiting_customer'=>'Waiting for customer','resolved'=>'Resolved','closed'=>'Closed'] as $key=>$label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="priority">
            <option value="">All priorities</option>
            @foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $key=>$label)
                <option value="{{ $key }}" @selected(request('priority') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary">Filter</button>
        <a href="{{ route('platform.support.index') }}" class="btn btn-outline">Reset</a>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Ticket</th><th>Business</th><th>Requested by</th><th>Priority</th><th>Status</th><th>Assigned to</th><th>Updated</th><th></th></tr></thead>
        <tbody>
            @forelse($tickets as $ticket)
                <tr>
                    <td><strong>#{{ str_pad($ticket->id,6,'0',STR_PAD_LEFT) }} · {{ $ticket->subject }}</strong><small class="table-subtext">{{ ucfirst($ticket->category) }}</small></td>
                    <td>{{ $ticket->tenant?->name }}</td>
                    <td>{{ $ticket->creator?->name }}</td>
                    <td><span class="status status-{{ $ticket->priority === 'urgent' ? 'suspended' : 'trial' }}">{{ ucfirst($ticket->priority) }}</span></td>
                    <td>{{ ucwords(str_replace('_',' ',$ticket->status)) }}</td>
                    <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                    <td>{{ $ticket->updated_at?->format('d M, H:i') }}</td>
                    <td><a class="btn btn-small btn-outline" href="{{ route('platform.support.show', $ticket) }}">Open →</a></td>
                </tr>
            @empty <tr><td colspan="8" class="empty-row">No support tickets match the selected filters.</td></tr>@endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $tickets->links() }}</div>
</section>
@endsection
