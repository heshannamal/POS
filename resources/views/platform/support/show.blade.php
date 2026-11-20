@extends('layouts.app')
@section('title','Support ticket #'.$ticket->id)
@section('header','Ticket #'.str_pad($ticket->id,6,'0',STR_PAD_LEFT))
@section('content')
<div class="page-head">
    <div><span class="overline">SUPPORT CASE</span><h1>{{ $ticket->subject }}</h1><p class="muted">{{ $ticket->tenant?->name }} · {{ $ticket->creator?->email }} · Created {{ $ticket->created_at->format('d M Y H:i') }}</p></div>
    <a href="{{ route('platform.support.index') }}" class="btn btn-outline">← Support inbox</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Conversation</h2><p class="muted">Internal notes are invisible to business users. Messages are stored in your database; email notifications are not configured yet.</p></div></div>
        <div class="conversation-feed">
            @forelse($ticket->messages->sortBy('id') as $message)
                <article class="message-card {{ $message->is_internal ? 'message-internal' : '' }}">
                    <div class="message-meta"><strong>{{ $message->sender?->name ?? 'Unknown user' }}</strong>
                        <span>{{ $message->created_at->format('d M Y, H:i') }}</span>
                        @if($message->is_internal)<span class="status status-trial">Internal note</span>@endif
                    </div>
                    <div class="message-body">{{ $message->message }}</div>
                </article>
            @empty <p class="muted">No messages.</p>@endforelse
        </div>
        <form method="POST" action="{{ route('platform.support.reply', $ticket) }}" class="form-stack" style="margin-top:24px">@csrf
            <label for="reply">Reply or internal note</label>
            <textarea id="reply" name="message" rows="5" maxlength="5000" required placeholder="Write an accurate, helpful response...">{{ old('message') }}</textarea>
            <label class="check-line"><input type="checkbox" name="is_internal" value="1" @checked(old('is_internal'))> Save as private internal note (not visible to the customer)</label>
            <button type="submit" class="btn btn-primary">Save response →</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Ticket settings</h2><p class="muted">Update priority, assignment and resolution</p></div></div>
        <form method="POST" action="{{ route('platform.support.update', $ticket) }}" class="form-stack">
            @csrf @method('PATCH')
            <label for="status">Current status</label>
            <select id="status" name="status">
                @foreach(['open'=>'Open','in_progress'=>'In progress','waiting_customer'=>'Waiting for customer','resolved'=>'Resolved','closed'=>'Closed'] as $status=>$label)
                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ $label }}</option>
                @endforeach
            </select>
            <label for="priority">Priority</label>
            <select id="priority" name="priority">
                @foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $priority=>$label)
                    <option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ $label }}</option>
                @endforeach
            </select>
            <label for="assigned_to">Assigned administrator</label>
            <select id="assigned_to" name="assigned_to">
                <option value="">Unassigned</option>
                @foreach($assignees as $admin)<option value="{{ $admin->id }}" @selected($ticket->assigned_to === $admin->id)>{{ $admin->name }}</option>@endforeach
            </select>
            <button class="btn btn-secondary" type="submit">Save ticket settings</button>
        </form>
    </section>
</div>
@endsection
