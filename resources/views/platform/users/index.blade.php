@extends('layouts.app')
@section('title', 'Customer accounts')
@section('header', 'Customers & users')
@section('content')
@php $canInvite = in_array(auth()->user()->platformRole(), ['super_admin','operations'], true); @endphp
<div class="page-head">
    <div><span class="overline">ACCOUNT DIRECTORY</span><h1>Platform users</h1><p class="muted">Find business owners, employees and invited users. User records are shared across tenants; business data is not.</p></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>User directory</h2><p class="muted">Only verified users can be assigned as a new business owner.</p></div></div>
        <form method="GET" action="{{ route('platform.users.index') }}" class="filter-row">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email">
            <select name="verified" aria-label="Email verification filter">
                <option value="">Any verification status</option>
                <option value="yes" @selected(request('verified')==='yes')>Verified</option>
                <option value="no" @selected(request('verified')==='no')>Not verified</option>
            </select>
            <button class="btn btn-secondary" type="submit">Search users</button>
            <a href="{{ route('platform.users.index') }}" class="btn btn-outline">Reset</a>
        </form>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Account</th><th>Business memberships</th><th>Email verification</th><th>Platform privilege</th><th>Created</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><div class="table-identity"><span class="circle-initial">{{ mb_strtoupper(mb_substr($user->name,0,1)) }}</span><div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div></div></td>
                    <td>{{ $user->tenants_count }}</td>
                    <td><span class="status status-{{ $user->hasVerifiedEmail() ? 'active' : 'expired' }}">{{ $user->hasVerifiedEmail() ? 'Verified' : 'Pending' }}</span></td>
                    <td>{{ $user->is_platform_admin ? ucwords(str_replace('_',' ',$user->platformRole())) : 'Standard user' }}</td>
                    <td>{{ $user->created_at->format('d M Y') }}</td>
                    <td><a href="{{ route('platform.users.show',$user) }}" class="btn btn-small btn-outline">Details →</a></td>
                </tr>
            @empty <tr><td colspan="6" class="empty-row">No matching user accounts.</td></tr>@endforelse
            </tbody>
        </table></div>
        <div class="pagination-line">{{ $users->links() }}</div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Pending account invitations</h2><p class="muted">Single-use links expire in 7 days</p></div></div>
        <div class="stack-list">
            @forelse($invitations as $invitation)
                <div class="stack-row"><span class="circle-initial">✉</span><span class="stack-grow"><strong>{{ $invitation->name }}</strong><small>{{ $invitation->email }} · Expires {{ $invitation->expires_at->format('d M') }}</small></span></div>
            @empty <p class="empty-box muted">No pending invitations.</p>@endforelse
        </div>
    </section>
    @if($canInvite)
        <section class="panel">
            <div class="panel-heading"><div><h2>Invite a customer</h2><p class="muted">Securely create a new verified account via email, without default passwords.</p></div></div>
            <form method="POST" action="{{ route('platform.users.invite') }}" class="form-stack">@csrf
                <label for="new_user_name">Name</label>
                <input id="new_user_name" name="name" maxlength="120" required value="{{ old('name') }}" placeholder="Business owner's name">
                <label for="new_user_email">Email</label>
                <input id="new_user_email" name="email" type="email" maxlength="191" required value="{{ old('email') }}" placeholder="owner@business.com">
                <div class="hint-box">The user receives a one-time invitation link to set their own password. Once accepted, you can create their business from the Businesses page. This invitation also works when public signup is disabled.</div>
                <button class="btn btn-primary" type="submit">Email account invitation →</button>
            </form>
        </section>
    @endif
</div>
@endsection
