@extends('layouts.app')
@section('title','Registers & Cashier Shifts')
@section('header','Registers & Shifts')
@section('content')
<div class="page-head"><div><span class="overline">POS OPERATIONS</span><h1>Cash registers & shifts</h1><p class="muted">Open, reconcile and close a cash drawer for each cashier. All amounts in {{ $tenant->currency }}.</p></div><a href="{{ route('business.pos.terminal',$tenant) }}" class="btn btn-secondary">POS terminal →</a></div>
<div class="dashboard-grid">
@if(app(\App\Services\TenantAuthorization::class)->allows(auth()->user(),$tenant,'pos.register.manage'))
<section class="panel"><h2>Create register</h2>
<form method="post" action="{{ route('business.pos.registers.create',$tenant) }}" class="form-stack">@csrf
<label>Branch <select name="branch_id" required class="form-control">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
<label>Register name <input class="form-control" name="name" placeholder="Front counter 1" required maxlength="120"></label>
<button class="btn btn-primary">Create register</button></form>
</section>
@endif
<section class="panel"><h2>Open a cashier shift</h2>
<form method="post" action="{{ route('business.pos.shifts.open',$tenant) }}" class="form-stack">@csrf
<label>Register <select name="register_id" class="form-control" required>@foreach($registers as $register)@if($register->is_active && !$openShifts->contains('register_id',$register->id))<option value="{{ $register->id }}">{{ $register->branch_name }} / {{ $register->name }}</option>@endif @endforeach</select></label>
<label>Opening cash ({{ $tenant->currency }}) <input type="number" step=".01" min="0" name="opening_cash" value="0" required class="form-control"></label>
<button class="btn btn-primary">Open my shift</button>
</form><p class="muted">One open shift per register and cashier.</p></section>
</div>
<section class="panel"><h2>Registered cash drawers</h2><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Name</th><th>Branch</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($registers as $register)<tr><td>{{ $register->name }}</td><td>{{ $register->branch_name }}</td><td>{{ $register->is_active?'Active':'Inactive' }}</td><td>@if(app(\App\Services\TenantAuthorization::class)->allows(auth()->user(),$tenant,'pos.register.manage'))
<form method="post" action="{{ route('business.pos.registers.toggle',[$tenant,$register->id]) }}">@csrf @method('PATCH')<button class="btn btn-secondary">{{ $register->is_active?'Deactivate':'Activate' }}</button></form>@else — @endif</td></tr>
@empty<tr><td colspan="4">No registers created.</td></tr>@endforelse
</tbody></table></div></section>
<section class="panel"><h2>Open shifts</h2>
@forelse($openShifts as $shift)
<div class="panel" style="margin-bottom:16px">
<h3>{{ $shift->register_name }} · Shift #{{ $shift->id }}</h3>
<p class="muted">Cashier: {{ $shift->cashier_name }} | Opened: {{ $shift->opened_at }}</p>
<p>Opening cash: <strong>{{ $tenant->currency }} {{ number_format($shift->opening_cash_minor/100,2) }}</strong></p>
<p>Expected cash: <strong>{{ $tenant->currency }} {{ number_format($shift->expected/100,2) }}</strong></p>
@if((int)$shift->user_id === auth()->id() || auth()->user()->isOwnerOf($tenant))
<div class="dashboard-grid">
<div><h3>Cash in / out</h3><form method="post" action="{{ route('business.pos.shifts.movement',[$tenant,$shift->id]) }}" class="form-stack">@csrf
<select name="type" class="form-control"><option value="cash_in">Cash in</option><option value="cash_out">Cash out</option></select>
<label>Amount <input name="amount" class="form-control" type="number" step=".01" min=".01" required></label>
<label>Reason <input name="reason" class="form-control" required maxlength="500"></label><button class="btn btn-secondary">Record movement</button></form></div>
<div><h3>Close & reconcile</h3><form method="post" action="{{ route('business.pos.shifts.close',[$tenant,$shift->id]) }}" class="form-stack">@csrf
<label>Counted cash <input name="closing_cash" class="form-control" type="number" step=".01" min="0" required></label>
<label>Closing note <textarea name="note" class="form-control" maxlength="3000"></textarea></label>
<button class="btn btn-primary" onclick="return confirm('Close this shift? The cash total will be finalized.')">Close shift</button></form></div>
</div>
@endif
</div>
@empty<p class="muted">No open shifts.</p>@endforelse
</section>
<section class="panel"><h2>Recently closed shifts</h2><div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Shift</th><th>Register</th><th>Expected</th><th>Counted</th><th>Variance</th><th>Closed</th></tr></thead><tbody>@forelse($recent as $shift)<tr><td>#{{ $shift->id }}</td><td>{{ $shift->register_name }}</td><td>{{ number_format($shift->expected_cash_minor/100,2) }}</td><td>{{ number_format($shift->closing_cash_minor/100,2) }}</td><td>{{ number_format($shift->cash_difference_minor/100,2) }}</td><td>{{ $shift->closed_at }}</td></tr>@empty<tr><td colspan="6">No closed shifts.</td></tr>@endforelse</tbody></table></div></section>
@endsection