@extends('layouts.auth')
@section('title','My security sessions')
@section('content')
<span class="overline">SECURE SESSION MANAGEMENT</span>
<h2>Signed-in devices and sessions</h2>
<p class="muted">Review recent application sessions. Session records show the last reported IP address, device and activity time; an active entry is not a guaranteed live connection.</p>
@if(auth()->user()->is_platform_admin && auth()->user()->two_factor_confirmed_at)
    <div class="hint-box">Authenticator enabled. <a href="{{ route('security.recovery') }}">Manage recovery codes</a>.</div>
@elseif(auth()->user()->is_platform_admin)
    <a class="btn btn-secondary" href="{{ route('security.setup') }}">Enable authenticator protection</a>
@endif
<div class="security-session-list">
@forelse($sessions as $session)
    <div class="security-session-card">
        <div><strong>{{ $session->session_hash === $currentHash ? 'This device · ' : '' }}{{ $session->ip_address ?? 'Unknown IP' }}</strong>
            <small>{{ \Illuminate\Support\Str::limit($session->user_agent ?? 'Unknown device',110) }}</small>
            <small>Last seen {{ $session->last_seen_at?->format('d M Y H:i') }} · {{ $session->revoked_at ? 'Revoked' : 'Not revoked' }}</small>
        </div>
        @if(!$session->revoked_at)
            <form method="POST" action="{{ route('security.sessions.revoke',$session) }}" class="form-stack">@csrf @method('DELETE')
                <input type="password" name="password" autocomplete="current-password" aria-label="Confirm your password" required placeholder="Password to revoke">
                <button type="submit" class="btn btn-small btn-outline" data-confirm="Revoke this session?">Revoke</button>
            </form>
        @endif
    </div>
@empty <p class="muted">No session records available yet.</p> @endforelse
</div>
<div class="pagination-line">{{ $sessions->links() }}</div>
<p class="auth-switch"><a href="{{ auth()->user()->is_platform_admin ? route('platform.dashboard') : route('onboarding.create') }}">Back to dashboard →</a></p>
@endsection
