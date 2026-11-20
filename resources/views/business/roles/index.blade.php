@extends('layouts.app')
@section('title', 'Roles and permissions')
@section('header', 'Roles & permissions')
@section('content')
@php $canEdit = app(\App\Services\TenantAuthorization::class)->allows(auth()->user(), $tenant, 'roles.manage'); @endphp
<div class="page-head">
    <div><span class="overline">ACCESS CONTROL</span><h1>Permission matrix</h1><p class="muted">Assign permissions by role within {{ $tenant->name }} only.</p></div>
    <a class="btn btn-outline" href="{{ route('business.employees.index', $tenant) }}">← Employees</a>
</div>
<div class="detail-grid">
    @foreach($roles as $role)
        <section class="panel">
            <div class="panel-heading"><div><h2>{{ $role->name }}</h2><p class="muted">{{ $role->is_system ? 'System default role' : 'Custom role' }} · {{ $role->permissions->count() }} permissions</p></div>
                @if($role->slug === 'owner')<span class="status status-active">Protected</span>@endif
            </div>
            <form method="POST" action="{{ route('business.roles.update', [$tenant, $role]) }}">
                @csrf @method('PUT')
                <label for="role-name-{{ $role->id }}">Role name</label>
                <input id="role-name-{{ $role->id }}" name="name" maxlength="80" required value="{{ $role->name }}" @disabled(! $canEdit || $role->slug === 'owner')>
                <div class="permission-list">
                    @foreach($permissions as $group => $groupPermissions)
                        <div class="permission-group"><strong>{{ $group }}</strong>
                            @foreach($groupPermissions as $permission)
                                <label class="check-line"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id)) @disabled(! $canEdit || $role->slug === 'owner')> {{ $permission->label }}</label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @if($canEdit && $role->slug !== 'owner')<button class="btn btn-secondary" type="submit">Save role permissions</button>@endif
            </form>
        </section>
    @endforeach
    @if($canEdit)
        <section class="panel panel-wide">
            <div class="panel-heading"><div><h2>Create a custom role</h2><p class="muted">Assign only the permissions staff should receive.</p></div></div>
            <form method="POST" action="{{ route('business.roles.store', $tenant) }}" class="form-stack">@csrf
                <label for="new_role_name">Role name</label>
                <input id="new_role_name" name="name" maxlength="80" required placeholder="Inventory Supervisor">
                <div class="permission-list">
                    @foreach($permissions as $group => $groupPermissions)
                        <div class="permission-group"><strong>{{ $group }}</strong>
                            @foreach($groupPermissions as $permission)
                                <label class="check-line"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}"> {{ $permission->label }}</label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <button class="btn btn-primary" type="submit">Create role →</button>
            </form>
        </section>
    @endif
</div>
@endsection
