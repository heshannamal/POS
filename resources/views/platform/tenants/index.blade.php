@extends('layouts.app')
@section('title','Businesses')
@section('header','Businesses & tenants')
@section('content')
<div class="page-head">
    <div><span class="overline">TENANT MANAGEMENT</span><h1>All businesses</h1><p class="muted">Find business workspaces by owner, status, plan, country or registration date. Archived businesses are kept separately.</p></div>
    <div class="page-actions">
        @if(auth()->user()->isPermanentPlatformAdmin() && auth()->user()->platformRole()==='super_admin')
            <a class="btn btn-outline" href="{{ route('platform.tenants.archived') }}">Archived businesses</a>
        @endif
        <a class="btn btn-primary" href="{{ route('platform.tenants.create') }}">＋ Create business</a>
    </div>
</div>
<section class="panel">
    <div class="panel-heading">
        <div><h2>Business directory</h2><p class="muted">{{ $tenants->total() }} matching workspaces · profile, branches and lifecycle management</p></div>
    </div>
    <form method="GET" action="{{ route('platform.tenants.index') }}" class="tenant-filters">
        <label>Search business / owner
            <input name="search" type="search" maxlength="120" value="{{ request('search') }}" placeholder="Business, email or workspace ID">
        </label>
        <label>Status
            <select name="status">
                <option value="">All statuses</option>
                @foreach(['trial'=>'Running trial','trial_inactive'=>'Inactive trial','expired'=>'Expired trial','active'=>'Active','inactive'=>'Inactive business','suspended'=>'Suspended'] as $key=>$name)
                    <option value="{{ $key }}" @selected(request('status')===$key)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>Subscription plan
            <select name="plan">
                <option value="">All plans</option>
                @foreach($plans as $plan)
                    <option value="{{ $plan->slug }}" @selected(request('plan')===$plan->slug)>{{ $plan->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Country
            <select name="country">
                <option value="">All countries</option>
                @foreach($countries as $code)
                    <option value="{{ $code }}" @selected(strtoupper((string)request('country'))===$code)>{{ $code }}</option>
                @endforeach
            </select>
        </label>
        <label>Registered from
            <input type="date" name="from" value="{{ request('from') }}">
        </label>
        <label>Registered to
            <input type="date" name="to" value="{{ request('to') }}">
        </label>
        <label>Sort by
            <select name="sort">
                @foreach(['created_at'=>'Registration date','name'=>'Business name','status'=>'Status','country'=>'Country'] as $key=>$label)
                    <option value="{{ $key }}" @selected(request('sort','created_at')===$key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Direction
            <select name="direction">
                <option value="desc" @selected(request('direction','desc')==='desc')>Descending</option>
                <option value="asc" @selected(request('direction')==='asc')>Ascending</option>
            </select>
        </label>
        <label>Per page
            <select name="per_page">
                @foreach([12,25,50] as $count)<option value="{{ $count }}" @selected((int)request('per_page',12)===$count)>{{ $count }}</option>@endforeach
            </select>
        </label>
        <div class="tenant-filter-actions">
            <button class="btn btn-secondary" type="submit">Apply filters</button>
            <a href="{{ route('platform.tenants.index') }}" class="btn btn-outline">Clear</a>
        </div>
    </form>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Business</th><th>Contact</th><th>Country</th><th>Plan</th><th>Stores / Users</th><th>Onboarding</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($tenants as $tenant)
                    <tr>
                        <td>
                            <div class="table-identity">
                                @if($tenant->logo_path)
                                    <img class="tenant-logo-small" src="{{ route('platform.tenants.logo',$tenant) }}" alt="" loading="lazy">
                                @else
                                    <span class="circle-initial">{{ mb_strtoupper(mb_substr($tenant->name,0,1)) }}</span>
                                @endif
                                <div><strong>{{ $tenant->name }}</strong><small>{{ $tenant->slug }}</small></div>
                            </div>
                        </td>
                        <td>{{ $tenant->contact_email ?: '—' }}<small class="table-subtext">{{ $tenant->phone }}</small></td>
                        <td>{{ $tenant->country ?: '—' }}</td>
                        <td>{{ $tenant->subscription?->plan?->name ?? 'Unassigned' }}</td>
                        <td>{{ $tenant->branches_count }} stores / {{ $tenant->users_count }} users</td>
                        <td>
                            <span class="status status-{{ $tenant->onboarding_status === 'completed' ? 'active' : 'trial' }}">{{ ucfirst($tenant->onboarding_status ?? 'incomplete') }}</span>
                            <div class="tenant-progress" role="progressbar" aria-label="Onboarding progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $tenant->onboarding_progress }}">
                                <span style="width:{{ $tenant->onboarding_progress }}%"></span>
                            </div>
                        </td>
                        <td><span class="status status-{{ $tenant->effective_status }}">{{ ucfirst($tenant->effective_status) }}</span></td>
                        <td><a class="btn btn-small btn-outline" href="{{ route('platform.tenants.show',$tenant) }}">Manage →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-row">No businesses match these filters. Adjust the search or clear filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-line">{{ $tenants->links() }}</div>
</section>
@endsection
