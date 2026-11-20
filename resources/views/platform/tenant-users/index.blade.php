@extends('layouts.app')
@section('title','Tenant Users')
@section('header','Tenant user management')
@section('content')
<div class="page-head">
    <div><span class="overline">CENTRALIZED TEAM MANAGEMENT</span><h1>All tenant users</h1><p class="muted">Each row is a business membership. A user may belong to multiple businesses with different permissions.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.users.index') }}">Open account directory →</a>
</div>
<section class="panel">
    <div class="panel-heading"><div><h2>Tenant memberships</h2><p class="muted">{{ number_format($users->total()) }} matching memberships · scoped status controls and login history</p></div></div>
    <form method="GET" action="{{ route('platform.tenant-users.index') }}" class="tenant-filters">
        <label>Search
            <input type="search" name="search" maxlength="120" value="{{ request('search') }}" placeholder="Name, email or business">
        </label>
        <label>Business
            <select name="tenant_id"><option value="">All businesses</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected((string)request('tenant_id')===(string)$tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Store
            <select name="store_id"><option value="">All stores</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" @selected((string)request('store_id')===(string)$store->id)>{{ $store->name }} · #{{ $store->tenant_id }}</option>
                @endforeach
            </select>
        </label>
        <label>Role
            <select name="role"><option value="">All roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}" @selected(request('role')===$role->slug)>{{ $role->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Member status
            <select name="status"><option value="">All statuses</option>
                @foreach(['active'=>'Active','inactive'=>'Inactive','blocked'=>'Blocked','suspended'=>'Suspended (legacy)'] as $key=>$name)
                    <option value="{{ $key }}" @selected(request('status')===$key)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>Sort
            <select name="sort"><option value="created_at" @selected(request('sort','created_at')==='created_at')>Date added</option><option value="user" @selected(request('sort')==='user')>User name</option><option value="business" @selected(request('sort')==='business')>Business name</option></select>
        </label>
        <label>Direction
            <select name="direction"><option value="desc" @selected(request('direction','desc')==='desc')>Descending</option><option value="asc" @selected(request('direction')==='asc')>Ascending</option></select>
        </label>
        <label>Per page
            <select name="per_page">@foreach([12,25,50] as $count)<option value="{{ $count }}" @selected((int)request('per_page',12)===$count)>{{ $count }}</option>@endforeach</select>
        </label>
        <div class="tenant-filter-actions"><button class="btn btn-secondary">Filter users</button><a class="btn btn-outline" href="{{ route('platform.tenant-users.index') }}">Reset</a></div>
    </form>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Member</th><th>Business</th><th>Role</th><th>Membership</th><th>Status</th><th>Last login</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($users as $member)
                    <tr>
                        <td><div class="table-identity"><span class="circle-initial">{{ mb_strtoupper(mb_substr($member->user_name,0,1)) }}</span><div><strong>{{ $member->user_name }}</strong><small>{{ $member->email }}</small></div></div></td>
                        <td>{{ $member->business_name }}</td>
                        <td>{{ $member->is_owner ? 'Owner' : ($member->role_name ?? 'No role') }}</td>
                        <td>{{ $member->is_owner ? 'Business owner' : 'Employee' }}</td>
                        <td><span class="status status-{{ $member->status==='active'?'active':'suspended' }}">{{ ucfirst($member->status) }}</span>@if($member->force_password_reset_at)<small class="table-subtext">Password reset required</small>@endif</td>
                        <td>{{ $member->last_login_at ? \Illuminate\Support\Carbon::parse($member->last_login_at)->format('d M Y H:i') : 'Never recorded' }}</td>
                        <td><a class="btn btn-small btn-outline" href="{{ route('platform.tenant-users.show',[$member->business_slug,$member->user_id]) }}">Manage →</a></td>
                    </tr>
                @empty <tr><td colspan="7" class="empty-row">No tenant users match your filters.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-line">{{ $users->links() }}</div>
</section>
<div class="hint-box" style="margin-top:16px">Blocking or deactivating a business membership only removes access to that business. A forced password reset is a separate global user-account security action requiring permanent Super Admin confirmation.</div>
@endsection
