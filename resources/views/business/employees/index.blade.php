@extends('layouts.app')
@section('title', 'Employees')
@section('header', 'Employees & team')
@section('content')
@php $canAssign = app(\App\Services\TenantAuthorization::class)->allows(auth()->user(), $tenant, 'roles.manage'); @endphp
<div class="page-head">
    <div><span class="overline">EMPLOYEE ACCESS</span><h1>Team members</h1><p class="muted">Invite staff, manage access and keep every action scoped to {{ $tenant->name }}.</p></div>
    @if($canAssign)<a class="btn btn-secondary" href="{{ route('business.roles.index', $tenant) }}">Manage roles →</a>@endif
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Employees ({{ $employees->count() }})</h2><p class="muted">Owner accounts are protected from removal or role changes.</p></div></div>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Employee</th><th>Current role</th><th>Status</th><th>Access</th></tr></thead>
            <tbody>
                @foreach($employees as $employee)
                    <tr>
                        <td><div class="table-identity"><span class="circle-initial">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span><div><strong>{{ $employee->name }}</strong><small>{{ $employee->email }}</small></div></div></td>
                        <td>{{ $employee->pivot->is_owner ? 'Owner' : ($assignedRoles[$employee->id][0]->name ?? 'Not assigned') }}</td>
                        <td><span class="status status-{{ $employee->pivot->status === 'active' ? 'active' : 'suspended' }}">{{ ucfirst($employee->pivot->status) }}</span></td>
                        <td>
                            @if($canAssign && ! $employee->pivot->is_owner && $employee->id !== auth()->id())
                                <form method="POST" action="{{ route('business.employees.update', [$tenant, $employee]) }}" class="compact-form">@csrf @method('PUT')
                                    <select name="role_id" aria-label="Role for {{ $employee->name }}" required>
                                        @foreach($roles->where('slug', '!=', 'owner') as $role)
                                            <option value="{{ $role->id }}" @selected(isset($assignedRoles[$employee->id]) && $assignedRoles[$employee->id]->contains('name', $role->name))>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="status" aria-label="Access status for {{ $employee->name }}"><option value="active" @selected($employee->pivot->status === 'active')>Active</option><option value="suspended" @selected($employee->pivot->status === 'suspended')>Suspended</option></select>
                                    <button type="submit" class="btn btn-small btn-outline">Save</button>
                                </form>
                            @else <span class="muted">—</span> @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Pending invitations</h2><p class="muted">Invitations expire after seven days</p></div></div>
        <div class="stack-list">
            @forelse($invitations as $invitation)
                <div class="stack-row"><span class="circle-initial">✉</span><span class="stack-grow"><strong>{{ $invitation->email }}</strong><small>{{ $invitation->role?->name }} · Expires {{ $invitation->expires_at->format('d M') }}</small></span></div>
            @empty <p class="muted empty-box">No pending invitations.</p> @endforelse
        </div>
    </section>
    @if($canAssign)
        <section class="panel">
            <div class="panel-heading"><div><h2>Invite an employee</h2><p class="muted">Staff must sign in with a verified email and accept their invitation.</p></div></div>
            <form class="form-stack" method="POST" action="{{ route('business.employees.invite', $tenant) }}">@csrf
                <label for="invite_email">Employee email</label>
                <input id="invite_email" type="email" name="email" maxlength="191" required value="{{ old('email') }}" placeholder="employee@example.com">
                <label for="role_id">Assigned role</label>
                <select id="role_id" name="role_id" required>
                    @foreach($roles->where('slug', '!=', 'owner') as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary" type="submit">Send invitation →</button>
            </form>
        </section>
    @endif
</div>
@endsection
