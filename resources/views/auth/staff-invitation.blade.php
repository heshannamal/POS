@extends('layouts.auth')
@section('title', 'Staff invitation')
@section('content')
<span class="overline">TEAM INVITATION</span>
<h2>Join {{ $invitation->tenant->name }}.</h2>
<p class="muted">You have been invited as <strong>{{ $invitation->role->name }}</strong> using <strong>{{ $invitation->email }}</strong>. This invitation expires on {{ $invitation->expires_at->format('d M Y') }}.</p>
@if(auth()->check())
    @if(auth()->user()->hasVerifiedEmail() && mb_strtolower(auth()->user()->email) === mb_strtolower($invitation->email))
        <form method="POST" action="{{ route('staff.invites.accept', $token) }}">@csrf
            <button class="btn btn-primary" type="submit">Accept invitation →</button>
        </form>
    @elseif(!auth()->user()->hasVerifiedEmail())
        <p class="muted">Verify your account before accepting this invitation.</p>
        <a class="btn btn-secondary" href="{{ route('verification.notice') }}">Verify email</a>
    @else
        <div class="hint-box">You are signed in as {{ auth()->user()->email }}. Sign out and use {{ $invitation->email }} to accept.</div>
        <form method="POST" action="{{ route('logout') }}" class="spaced-form">@csrf <button type="submit" class="btn btn-outline">Sign out</button></form>
    @endif
@else
    <p class="muted">Sign in or register using your invited email, then return here to accept. Your invitation will remain available after verification.</p>
    <div class="button-stack">
        <a href="{{ route('login') }}" class="btn btn-primary">Sign in →</a>
        <a href="{{ route('register') }}" class="btn btn-outline">Create account</a>
    </div>
@endif
@endsection
