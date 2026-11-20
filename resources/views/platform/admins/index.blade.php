@extends('layouts.app')
@section('title', 'Platform administrators')
@section('header', 'Administrator team')
@section('content')
<div class="page-head"><div><span class="overline">SECURE ACCESS MANAGEMENT</span><h1>Platform administrator team</h1><p class="muted">Review privileged accounts and assign least-privilege platform roles. Changes require your password.</p></div></div>
<section class="panel">
    <div class="panel-heading"><div><h2>Administrator accounts</h2><p class="muted">Permanent administrators can be assigned a scoped role. Temporary developers cannot grant administrator access.</p></div></div>
    <div class="table-scroll">
        <table class="data-table"><thead><tr><th>Administrator</th><th>Current role</th><th>Access</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($administrators as $admin)
                <tr>
                    <td><div class="table-identity"><span class="circle-initial">{{ mb_strtoupper(mb_substr($admin->name,0,1)) }}</span><div><strong>{{ $admin->name }}</strong><small>{{ $admin->email }}</small></div></div></td>
                    <td><span class="status status-{{ $admin->platformRole() === 'super_admin' ? 'active' : 'trial' }}">{{ ucwords(str_replace('_',' ', $admin->platformRole())) }}</span></td>
                    <td>{{ $admin->temporary_admin_expires_at ? 'Temporary · until '.$admin->temporary_admin_expires_at->format('d M Y H:i') : 'Permanent' }}</td>
                    <td>
                        @if($admin->id !== auth()->id())
                            <form method="POST" action="{{ route('platform.admins.update', $admin) }}" class="compact-form">
                                @csrf @method('PATCH')
                                <select name="role" aria-label="Role for {{ $admin->name }}">
                                    @foreach($roles as $role)<option value="{{ $role }}" @selected($admin->platformRole() === $role)>{{ ucwords(str_replace('_',' ',$role)) }}</option>@endforeach
                                </select>
                                <select name="enabled" aria-label="Admin access for {{ $admin->name }}">
                                    <option value="1" selected>Keep active</option><option value="0">Revoke admin</option>
                                </select>
                                <input style="min-width:145px;max-width:180px" name="admin_password" type="password" autocomplete="current-password" required placeholder="Your password" aria-label="Confirm your password">
                                <button class="btn btn-small btn-outline" type="submit" data-confirm="Change this platform administrator's privileges?">Save</button>
                            </form>
                        @else <span class="muted">Your account — protected</span> @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="pagination-line">{{ $administrators->links() }}</div>
</section>
<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Grant administrator access</h2><p class="muted">Only promote an existing user who has verified their email.</p></div></div>
        <form method="POST" action="{{ route('platform.admins.store') }}" class="form-stack">@csrf
            <label for="admin_email">Verified account email</label>
            <input id="admin_email" type="email" name="email" maxlength="191" required placeholder="team@example.com" value="{{ old('email') }}">
            <label for="admin_role">Administrator role</label>
            <select id="admin_role" name="role" required>
                @foreach($roles as $role)<option value="{{ $role }}" @selected(old('role','operations') === $role)>{{ ucwords(str_replace('_',' ',$role)) }}</option>@endforeach
            </select>
            <label for="admin_password">Confirm your password</label>
            <input type="password" id="admin_password" name="admin_password" autocomplete="current-password" required placeholder="Current admin password">
            <button class="btn btn-primary" type="submit" data-confirm="Grant platform administrator access to this account?">Grant admin role</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Find existing users</h2><p class="muted">Search verified accounts to locate the email to promote.</p></div></div>
        <form method="GET" action="{{ route('platform.admins.index') }}" class="filter-row">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by email or name">
            <button class="btn btn-outline" type="submit">Search</button>
        </form>
        <div class="stack-list">
            @forelse($candidates as $candidate)
                <div class="stack-row"><span class="circle-initial">{{ mb_strtoupper(mb_substr($candidate->name,0,1)) }}</span><span class="stack-grow"><strong>{{ $candidate->name }}</strong><small>{{ $candidate->email }}</small></span><span class="status status-active">Verified</span></div>
            @empty <p class="muted empty-box">Search to find an existing verified account.</p>@endforelse
        </div>
    </section>
</div>
@endsection
