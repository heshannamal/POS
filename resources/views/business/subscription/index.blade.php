@extends('layouts.app')
@section('title', 'Subscription & modules')
@section('header', 'Subscription & modules')
@section('content')
<div class="page-head">
    <div><span class="overline">BUSINESS LICENSING</span><h1>Your subscription</h1><p class="muted">Review the current plan and modules. Online upgrades and billing are not enabled yet.</p></div>
</div>
<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Current plan</h2><p class="muted">Plan assigned to this business</p></div></div>
        <div class="trial-highlight">
            <span>{{ strtoupper($tenant->subscription?->status ?? 'unassigned') }}</span>
            <strong>{{ $tenant->subscription?->plan?->name ?? 'No plan' }}</strong>
            <small>{{ $tenant->subscription?->source === 'manual' ? 'Activated by a platform administrator, not billed online' : 'Trial account' }}</small>
        </div>
        @if($tenant->status === 'trial')
            <p class="muted">{{ $tenant->trial?->days_remaining ?? 0 }} days remaining until {{ $tenant->trial?->ends_at?->format('d M Y') }}.</p>
        @endif
        <p class="muted">This page does not collect payments. Contact platform support for manual plan changes until automated billing is released.</p>
    </section>
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Module access</h2><p class="muted">Access requires a released feature and an active plan or override.</p></div></div>
        <div class="stack-list">
            @foreach($modules as $module)
                <div class="stack-row">
                    <span class="circle-initial">◇</span>
                    <span class="stack-grow"><strong>{{ $module->name }}</strong><small>{{ $module->category }} · {{ $module->code }}</small></span>
                    <span class="status status-{{ $allowed[$module->code] ? 'active' : ($module->is_available ? 'expired' : 'trial') }}">{{ $allowed[$module->code] ? 'Enabled' : ($module->is_available ? 'Not licensed' : 'Coming soon') }}</span>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
