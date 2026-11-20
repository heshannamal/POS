@extends('layouts.app')
@section('title','Businesses')
@section('header','Businesses & tenants')
@section('content')
<div class="page-head">
    <div><span class="overline">TENANT MANAGEMENT</span><h1>All businesses</h1><p class="muted">View, search and manage isolated business workspaces.</p></div>
    <a class="btn btn-primary" href="{{ route('platform.tenants.create') }}">＋ Create business</a>
</div>
<section class="panel">
    <form method="GET" action="{{ route('platform.tenants.index') }}" class="filter-row">
        <input name="search" type="search" value="{{ request('search') }}" placeholder="Search name, email or workspace..." aria-label="Search businesses">
        <select name="status" aria-label="Status filter">
            <option value="">All statuses</option>
            @foreach(['trial' => 'Trial', 'active' => 'Active', 'suspended' => 'Suspended'] as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-secondary">Apply filters</button>
        @if(request()->hasAny(['search','status']))<a class="btn btn-outline" href="{{ route('platform.tenants.index') }}">Reset</a>@endif
    </form>
    <div class="table-scroll">
        <table class="data-table"><thead><tr><th>Business</th><th>Contact</th><th>Members</th><th>Trial ends</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($tenants as $tenant)
                    <tr>
                        <td><div class="table-identity"><span class="circle-initial">{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}</span><div><strong>{{ $tenant->name }}</strong><small>{{ $tenant->slug }}</small></div></div></td>
                        <td>{{ $tenant->contact_email ?: '—' }}</td>
                        <td>{{ $tenant->users_count }}</td>
                        <td>{{ $tenant->trial?->ends_at?->format('d M Y') ?? '—' }}</td>
                        <td><span class="status status-{{ $tenant->effective_status }}">{{ ucfirst($tenant->effective_status) }}</span></td>
                        <td><a class="btn btn-small btn-outline" href="{{ route('platform.tenants.show', $tenant) }}">Manage →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">No matching businesses. Try adjusting your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-line">{{ $tenants->links() }}</div>
</section>
@endsection
