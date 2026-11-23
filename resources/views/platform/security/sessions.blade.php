@extends('layouts.app')
@section('title','All account sessions')
@section('header','Platform session management')
@section('content')
<div class="page-head">
    <div><span class="overline">SUPER ADMINISTRATOR SECURITY</span><h1>Platform sessions</h1><p class="muted">Recent application sessions across account types. Revocations are enforced on the next authenticated request.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.security.index') }}">← Security center</a>
</div>
<section class="panel">
    <form method="GET" action="{{ route('platform.security.sessions.index') }}" class="filter-row">
        <input name="search" value="{{ request('search') }}" type="search" maxlength="120" placeholder="User email or IP">
        <select name="status"><option value="">Any status</option><option value="active" @selected(request('status')==='active')>Non-revoked</option><option value="revoked" @selected(request('status')==='revoked')>Revoked</option></select>
        <button class="btn btn-secondary">Filter</button><a class="btn btn-outline" href="{{ route('platform.security.sessions.index') }}">Reset</a>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>User</th><th>IP</th><th>Device / browser</th><th>Last seen</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($sessions as $session)
            <tr>
                <td><strong>{{ $session->user?->name ?? 'Deleted user' }}</strong><small class="table-subtext">{{ $session->user?->email }}</small></td>
                <td>{{ $session->ip_address ?? 'Unknown' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($session->user_agent ?? 'Unknown', 80) }}</td>
                <td>{{ $session->last_seen_at?->format('d M Y H:i') }}</td>
                <td><span class="status status-{{ $session->revoked_at?'suspended':'active' }}">{{ $session->revoked_at?'Revoked':'Non-revoked' }}</span></td>
                <td>
                    @if(!$session->revoked_at && $session->user_id!==auth()->id())
                        <form method="POST" action="{{ route('platform.security.sessions.revoke',$session) }}" class="security-alert-action">
                            @csrf @method('DELETE')
                            <input type="text" name="reason" minlength="10" maxlength="1000" required placeholder="Revocation reason" aria-label="Reason">
                            <input type="password" name="password" autocomplete="current-password" required placeholder="Admin password" aria-label="Administrator password">
                            <button class="btn btn-small btn-outline" data-confirm="Revoke this user's session?">Revoke</button>
                        </form>
                    @else — @endif
                </td>
            </tr>
        @empty <tr><td colspan="6" class="empty-row">No sessions found.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $sessions->links() }}</div>
</section>
<div class="hint-box" style="margin-top:16px">Session timestamps indicate last contact, not guaranteed live connectivity. Only permanent Super Administrators can revoke other users' sessions.</div>
@endsection
