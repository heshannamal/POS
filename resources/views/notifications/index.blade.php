@extends('layouts.app')
@section('title','Notifications')
@section('header','My notifications')
@section('content')
@php $platformMode=request()->routeIs('platform.*'); @endphp
<div class="page-head">
    <div><span class="overline">PERSONAL NOTIFICATION CENTER</span><h1>Inbox & preferences</h1><p class="muted">Messages intended for your account. {{ $platformMode?'Platform notifications across your authorized roles.':'Notifications for '.$tenant->name.'.' }}</p></div>
    <form method="POST" action="{{ $platformMode ? route('platform.notifications.read-all') : route('business.notifications.read-all',$tenant) }}">@csrf
        <button type="submit" class="btn btn-secondary" @disabled(!$unreadCount)>Mark all read</button>
    </form>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">◉</span><span class="stat-label">Unread</span><strong>{{ $unreadCount }}</strong><small>For your account</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">✉</span><span class="stat-label">Showing</span><strong>{{ $notifications->total() }}</strong><small>Matching notifications</small></div>
</div>
<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Your notifications</h2><p class="muted">Only notifications addressed to you can be read or marked read.</p></div></div>
        <form method="GET" class="tenant-filters">
            <label>Status
                <select name="status"><option value="">All messages</option><option value="unread" @selected(request('status')==='unread')>Unread</option><option value="read" @selected(request('status')==='read')>Read</option></select>
            </label>
            <label>Event
                <select name="event"><option value="">All events</option>
                    @foreach(config('communications.events',[]) as $key=>$value)<option value="{{ $key }}" @selected(request('event')===$key)>{{ $value['name'] }}</option>@endforeach
                </select>
            </label>
            <div class="tenant-filter-actions"><button class="btn btn-secondary">Filter</button></div>
        </form>
        <div class="communication-inbox-list">
            @forelse($notifications as $notification)
                <article class="communication-notification {{ $notification->read_at?'':'unread' }}">
                    <div class="communication-notification-icon">{{ $notification->read_at?'✓':'✉' }}</div>
                    <div class="communication-notification-body">
                        <div class="message-meta"><strong>{{ $notification->title }}</strong><small>{{ $notification->created_at?->format('d M Y H:i') }}</small></div>
                        <small class="muted">{{ (config('communications.events',[])[$notification->event_key]['name'] ?? $notification->event_key) }}</small>
                        <p>{{ $notification->message }}</p>
                        <div class="communication-inbox-actions">
                            @if($notification->action_url)<a class="btn btn-small btn-outline" href="{{ $notification->action_url }}">Open related page →</a>@endif
                            @if(!$notification->read_at)
                                <form method="POST" action="{{ $platformMode?route('platform.notifications.read',$notification):route('business.notifications.read',[$tenant,$notification]) }}">@csrf
                                    <button class="btn btn-small btn-secondary">Mark read</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty <p class="empty-box muted">No notifications match these filters.</p> @endforelse
        </div>
        <div class="pagination-line">{{ $notifications->links() }}</div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>My notification preferences</h2><p class="muted">Choose which event types can reach you. Changes apply to future notifications.</p></div></div>
        <div class="communication-preferences">
            @foreach(config('communications.events',[]) as $key=>$definition)
                @php $pref=$preferences->get($key); @endphp
                <form method="POST" action="{{ $platformMode?route('platform.notifications.preferences'):route('business.notifications.preferences',$tenant) }}" class="form-stack">
                    @csrf @method('PUT')
                    <strong>{{ $definition['name'] }}</strong>
                    <input type="hidden" name="event_key" value="{{ $key }}">
                    <label class="check-line"><input type="hidden" name="email_enabled" value="0"><input type="checkbox" name="email_enabled" value="1" @checked($pref?->email_enabled??true)> Email</label>
                    <label class="check-line"><input type="hidden" name="in_app_enabled" value="0"><input type="checkbox" name="in_app_enabled" value="1" @checked($key==='security.alert'||($pref?->in_app_enabled??true)) @disabled($key==='security.alert')> In-app</label>
                    <button type="submit" class="btn btn-small btn-outline">Save preferences</button>
                </form>
            @endforeach
        </div>
        <p class="muted" style="margin-top:15px">Security alerts remain in-app for administrators. These preferences do not change the platform-wide email kill switch or other users' settings.</p>
    </section>
</div>
@endsection
