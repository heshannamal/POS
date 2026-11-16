@extends('layouts.app')
@section('title',$tenant->name)
@section('header',$tenant->name)
@section('content')
<div class="page-head">
    <div><span class="overline">BUSINESS DETAILS</span><h1>{{ $tenant->name }}</h1><p class="muted">{{ $tenant->slug }} · Created {{ $tenant->created_at->format('d M Y') }}</p></div>
    <div class="page-actions"><span class="status status-{{ $tenant->effective_status }}">{{ ucfirst($tenant->effective_status) }}</span><a class="btn btn-outline" href="{{ route('platform.tenants.index') }}">← All businesses</a></div>
</div>
<div class="detail-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Business profile</h2><p class="muted">Core tenant information</p></div></div>
        <dl class="detail-list">
            <div><dt>Contact email</dt><dd>{{ $tenant->contact_email ?? '—' }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $tenant->phone ?? '—' }}</dd></div>
            <div><dt>Address</dt><dd>{{ $tenant->address ?? '—' }}</dd></div>
            <div><dt>Currency</dt><dd>{{ $tenant->currency }}</dd></div>
            <div><dt>Timezone</dt><dd>{{ $tenant->timezone }}</dd></div>
        </dl>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Trial management</h2><p class="muted">Trial data remains when the business upgrades</p></div></div>
        @if($tenant->trial)
            <div class="trial-highlight">
                <span>Trial remaining</span><strong>{{ $tenant->trial->days_remaining }} days</strong>
                <small>{{ $tenant->trial->started_at->format('d M Y') }} — {{ $tenant->trial->ends_at->format('d M Y') }}</small>
            </div>
            @if($tenant->status === 'trial')
                <form method="POST" action="{{ route('platform.tenants.trial.extend', $tenant) }}" class="form-stack">@csrf
                    <label for="days">Extend trial (days)</label>
                    <input id="days" type="number" name="days" min="1" max="90" value="7" required>
                    <label for="notes">Internal reason (optional)</label>
                    <input id="notes" type="text" name="notes" maxlength="1000" placeholder="Reason for extension">
                    <button class="btn btn-secondary" type="submit">Extend trial</button>
                </form>
            @endif
        @endif
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Members</h2><p class="muted">Users assigned to this business</p></div></div>
        <div class="stack-list">
            @foreach($tenant->users as $member)
                <div class="stack-row"><span class="circle-initial">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span><span class="stack-grow"><strong>{{ $member->name }}</strong><small>{{ $member->email }}</small></span><span class="status status-active">{{ $member->pivot->is_owner ? 'Owner' : ucfirst($member->pivot->status) }}</span></div>
            @endforeach
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Branches</h2><p class="muted">Isolated business locations</p></div></div>
        <div class="stack-list">
            @foreach($tenant->branches as $branch)
                <div class="stack-row"><span class="circle-initial">▦</span><span class="stack-grow"><strong>{{ $branch->name }}</strong><small>{{ $branch->code }} · {{ $branch->address ?? 'No address' }}</small></span>@if($branch->is_default)<span class="status status-trial">Main</span>@endif</div>
            @endforeach
        </div>
    </section>
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Account status</h2><p class="muted">Administrative override — subscription payments are not yet integrated</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.status', $tenant) }}" class="inline-form">@csrf @method('PATCH')
            <label for="status">Business status</label>
            <select id="status" name="status">
                @foreach(['trial' => 'Trial', 'active' => 'Active (manual)', 'suspended' => 'Suspended'] as $value => $label)
                    <option value="{{ $value }}" @selected($tenant->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary" type="submit" data-confirm="Update this business account status?">Save status</button>
        </form>
    </section>
</div>
@endsection
