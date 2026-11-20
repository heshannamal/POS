@extends('layouts.app')
@section('title','Global Stores')
@section('header','Stores & branches')
@section('content')
<div class="page-head">
    <div><span class="overline">MULTI-TENANT OPERATIONS</span><h1>Global stores directory</h1><p class="muted">View every active business's locations, managers, device inventory and assigned staff.</p></div>
    @if(in_array(auth()->user()->platformRole(),['super_admin','operations'],true))
        <a class="btn btn-primary" href="{{ route('platform.stores.create') }}">＋ Add store</a>
    @endif
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">▦</span><span class="stat-label">All stores</span><strong>{{ number_format($stats['all']) }}</strong><small>Across non-archived businesses</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">✓</span><span class="stat-label">Active stores</span><strong>{{ number_format($stats['active']) }}</strong><small>Store record is active</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">◷</span><span class="stat-label">Closed</span><strong>{{ number_format($stats['closed']) }}</strong><small>Locations paused or closed</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">!</span><span class="stat-label">Suspended</span><strong>{{ number_format($stats['suspended']) }}</strong><small>Restricted store operations</small></div>
</div>
<section class="panel">
    <div class="panel-heading"><div><h2>Store directory</h2><p class="muted">{{ number_format($stores->total()) }} matching locations</p></div></div>
    <form method="GET" action="{{ route('platform.stores.index') }}" class="tenant-filters">
        <label>Search
            <input type="search" name="search" maxlength="120" value="{{ request('search') }}" placeholder="Store, code, business, manager">
        </label>
        <label>Business
            <select name="tenant_id"><option value="">All businesses</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected((string)request('tenant_id')===(string)$tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Country
            <select name="country"><option value="">All countries</option>
                @foreach($countries as $country)<option value="{{ $country }}" @selected(request('country')===$country)>{{ $country }}</option>@endforeach
            </select>
        </label>
        <label>Store status
            <select name="status"><option value="">Any status</option>
                @foreach(['active'=>'Active','closed'=>'Closed','suspended'=>'Suspended'] as $key=>$name)
                    <option value="{{ $key }}" @selected(request('status')===$key)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>Sort
            <select name="sort">
                @foreach(['created_at'=>'Date added','name'=>'Store name','status'=>'Status'] as $key=>$name)
                    <option value="{{ $key }}" @selected(request('sort','created_at')===$key)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>Direction
            <select name="direction"><option value="desc" @selected(request('direction','desc')==='desc')>Descending</option><option value="asc" @selected(request('direction')==='asc')>Ascending</option></select>
        </label>
        <label>Per page
            <select name="per_page">@foreach([12,25,50] as $count)<option value="{{ $count }}" @selected((int)request('per_page',12)===$count)>{{ $count }}</option>@endforeach</select>
        </label>
        <div class="tenant-filter-actions"><button class="btn btn-secondary">Apply filters</button><a class="btn btn-outline" href="{{ route('platform.stores.index') }}">Clear</a></div>
    </form>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Store / code</th><th>Business</th><th>Manager</th><th>Assigned staff</th><th>Devices</th><th>Status</th><th>Timezone</th><th></th></tr></thead>
            <tbody>
                @forelse($stores as $store)
                    <tr>
                        <td><div class="table-identity"><span class="circle-initial">▦</span><div><strong>{{ $store->name }}</strong><small>{{ $store->code }}{{ $store->is_default ? ' · Default' : '' }}</small></div></div></td>
                        <td><strong>{{ $store->tenant?->name }}</strong><small class="table-subtext">{{ $store->tenant?->country ?? 'No country' }}</small></td>
                        <td>{{ $store->manager?->name ?? 'Not assigned' }}</td>
                        <td>{{ $store->staff_count }}</td>
                        <td>{{ $store->devices_count }}</td>
                        <td><span class="status status-{{ $store->status==='active'?'active':'suspended' }}">{{ ucfirst($store->status) }}</span></td>
                        <td>{{ $store->timezone ?: $store->tenant?->timezone }}</td>
                        <td><a class="btn btn-small btn-outline" href="{{ route('platform.stores.show',$store) }}">Manage →</a></td>
                    </tr>
                @empty <tr><td colspan="8" class="empty-row">No store records match the selected filters.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-line">{{ $stores->links() }}</div>
</section>
<div class="hint-box" style="margin-top:16px">Device records are a registration inventory. They do not represent a live connection or POS transaction status until an authenticated device API is implemented.</div>
@endsection
