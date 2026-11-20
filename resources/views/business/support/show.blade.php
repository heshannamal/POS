@extends('layouts.app')
@section('title','Support request #'.$ticket->id)
@section('header','Support request #'.str_pad($ticket->id,6,'0',STR_PAD_LEFT))
@section('content')
<div class="page-head">
    <div><span class="overline">SUPPORT REQUEST</span><h1>{{ $ticket->subject }}</h1><p class="muted">Status: {{ ucwords(str_replace('_',' ',$ticket->status)) }} · Opened {{ $ticket->created_at->format('d M Y') }}</p></div>
    <a href="{{ route('business.support.index',$tenant) }}" class="btn btn-outline">← All requests</a>
</div>
<section class="panel" style="max-width:950px">
    <div class="panel-heading"><div><h2>Conversation</h2><p class="muted">Messages from your business and the platform support team.</p></div></div>
    <div class="conversation-feed">
        @foreach($messages as $message)
            <article class="message-card">
                <div class="message-meta"><strong>{{ $message->sender?->name ?? 'Platform support' }}</strong><span>{{ $message->created_at->format('d M Y H:i') }}</span></div>
                <div class="message-body">{{ $message->message }}</div>
            </article>
        @endforeach
    </div>
    <form method="POST" action="{{ route('business.support.reply',[$tenant,$ticket]) }}" class="form-stack" style="margin-top:24px">@csrf
        <label for="message">Add a reply</label>
        <textarea id="message" name="message" rows="5" maxlength="5000" required placeholder="Your reply...">{{ old('message') }}</textarea>
        <button type="submit" class="btn btn-primary">Send reply →</button>
    </form>
</section>
@endsection
