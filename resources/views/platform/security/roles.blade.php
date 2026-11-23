@extends('layouts.app')
@section('title','Custom administrator roles')
@section('header','Granular administrator permissions')
@section('content')
<div class="page-head">
    <div><span class="overline">LEAST-PRIVILEGE PLATFORM ACCESS</span><h1>Custom administrator roles</h1>
        <p class="muted">Combine selected scopes with individual view/manage permissions. These roles cannot inherit Super Admin, payment approval or permanent-only actions.</p>
    </div>
    <a href="{{ route('platform.admins.index') }}" class="btn btn-outline">← Administrator team</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Create granular role</h2><p class="muted">Choose one or more approved scopes, then only the permissions that belong to those scopes.</p></div></div>
        <form method="POST" action="{{ route('platform.security.roles.store') }}" class="form-stack">@csrf
            <div class="tenant-fields-grid">
                <div><label for="custom_name">Role name</label><input id="custom_name" name="name" required maxlength="100" placeholder="Retail Support Analyst"></div>
                <div><label for="custom_slug">Role identifier</label><input id="custom_slug" name="slug" required pattern="[a-z][a-z0-9_-]*" maxlength="60" placeholder="retail_support"></div>
            </div>
            <label for="custom_description">Description</label>
            <textarea id="custom_description" name="description" rows="2" maxlength="1000"></textarea>
            <h2>Access scopes</h2>
            <div class="security-permission-grid">
                @foreach($scopes as $scope=>$name)
                    <label class="check-line"><input type="checkbox" name="scopes[]" value="{{ $scope }}">{{ $name }}</label>
                @endforeach
            </div>
            <h2>Detailed permissions</h2>
            <div class="security-permission-grid">
                @foreach($permissions as $code=>$name)
                    <label class="check-line"><input type="checkbox" name="permissions[]" value="{{ $code }}">{{ $name }}</label>
                @endforeach
            </div>
            <input type="hidden" name="is_active" value="0">
            <label class="check-line"><input type="checkbox" name="is_active" value="1" checked> Role active</label>
            <label for="role_password">Your Super Admin password</label>
            <input name="admin_password" id="role_password" type="password" autocomplete="current-password" required>
            <button class="btn btn-primary" type="submit">Create custom role →</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Safety restrictions</h2><p class="muted">Built-in administrative boundaries remain in force.</p></div></div>
        <p class="muted">Custom roles can access only specifically selected pages and actions within their scope. A scope alone is insufficient: permissions must also be granted.</p>
        <p class="muted">No custom role can manage permanent administrators, approve financial receipts or change Super Admin-only payment settings.</p>
        <div class="hint-box">After creating a role, visit <a href="{{ route('platform.admins.index') }}">Administrator team</a> and assign it to an existing verified user. Updating a custom role invalidates sessions of admins who use it.</div>
    </section>
</div>
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>Existing custom roles</h2><p class="muted">Changes require password confirmation and are recorded in the audit log.</p></div></div>
    @forelse($roles as $role)
        <details class="security-role-card">
            <summary><strong>{{ $role->name }}</strong> <code>custom_{{ $role->id }}</code>
                <span class="status status-{{ $role->is_active?'active':'suspended' }}">{{ $role->is_active?'Active':'Inactive' }}</span>
            </summary>
            <form method="POST" action="{{ route('platform.security.roles.update',$role) }}" class="form-stack" style="margin-top:16px">
                @csrf @method('PUT')
                <div class="tenant-fields-grid">
                    <div><label>Name</label><input name="name" maxlength="100" required value="{{ $role->name }}"></div>
                    <div><label>Identifier</label><input name="slug" maxlength="60" pattern="[a-z][a-z0-9_-]*" required value="{{ $role->slug }}"></div>
                </div>
                <label>Description</label><textarea name="description" rows="2" maxlength="1000">{{ $role->description }}</textarea>
                <h2>Scopes</h2><div class="security-permission-grid">
                    @foreach($scopes as $scope=>$name)<label class="check-line"><input type="checkbox" name="scopes[]" value="{{ $scope }}" @checked(in_array($scope,$role->scopes??[]))>{{ $name }}</label>@endforeach
                </div>
                <h2>Permissions</h2><div class="security-permission-grid">
                    @foreach($permissions as $code=>$name)<label class="check-line"><input type="checkbox" name="permissions[]" value="{{ $code }}" @checked(in_array($code,$role->permissions??[]))>{{ $name }}</label>@endforeach
                </div>
                <input type="hidden" name="is_active" value="0">
                <label class="check-line"><input type="checkbox" name="is_active" value="1" @checked($role->is_active)> Active</label>
                <label>Your password</label><input name="admin_password" type="password" autocomplete="current-password" required>
                <button class="btn btn-secondary" data-confirm="Update this role and invalidate sessions of administrators assigned to it?">Save role</button>
            </form>
        </details>
    @empty <p class="muted empty-box">No custom roles yet. The original built-in roles remain available.</p>@endforelse
</section>
@endsection
