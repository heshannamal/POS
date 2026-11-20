@extends('layouts.app')
@section('title','Business dashboard')
@section('header','Welcome, '.auth()->user()->name.' 👋')
@section('content')
<div class="page-head">
    <div><span class="overline">YOUR WORKSPACE</span><h1>{{ $tenant->name }}</h1><p class="muted">Your business foundation is ready. Future modules will appear as they are enabled.</p></div>
    <span class="status status-{{ $tenant->effective_status }}">{{ ucfirst($tenant->effective_status) }}</span>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">▦</span><span class="stat-label">Branches</span><strong>{{ $tenant->branches_count }}</strong><small>Business locations</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">♙</span><span class="stat-label">Team members</span><strong>{{ $tenant->users_count }}</strong><small>Authorized workspace users</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">◷</span><span class="stat-label">Trial days left</span><strong>{{ $tenant->trial?->days_remaining ?? '—' }}</strong><small>Original data will be preserved</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">✳</span><span class="stat-label">Workspace</span><strong class="stat-text">Ready</strong><small>{{ $tenant->currency }} · {{ $tenant->timezone }}</small></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Your business setup</h2><p class="muted">The first building blocks are configured</p></div></div>
        <div class="setup-checklist">
            <div><span class="check-icon">✓</span><div><strong>Business account created</strong><small>{{ $tenant->name }}</small></div></div>
            <div><span class="check-icon">✓</span><div><strong>Owner access established</strong><small>Permission-aware business membership</small></div></div>
            <div><span class="check-icon">✓</span><div><strong>Main branch created</strong><small>Your first business location</small></div></div>
            <div><span class="check-icon">✓</span><div><strong>Trial enabled</strong><small>Your account will retain all business data after upgrading</small></div></div>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Trial & subscription</h2><p class="muted">Account status</p></div></div>
        <div class="trial-highlight"><span>Current status</span><strong>{{ ucfirst($tenant->effective_status) }}</strong><small>{{ $tenant->trial?->ends_at?->format('d M Y') ?? 'No trial date' }}</small></div>
        <p class="muted">See which modules are enabled for your business. Automated subscription checkout is not yet available.</p><a href="{{ route('business.subscription.index', $tenant) }}" class="btn btn-secondary">View subscription →</a>
    </section>
</div>
@endsection
