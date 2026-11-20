@extends('layouts.app')
@section('title', $module ? 'Configure module' : 'Create planned module')
@section('header', $module ? $module->name : 'New planned module')
@section('content')
<div class="page-head">
    <div><span class="overline">MODULE CONFIGURATION</span><h1>{{ $module ? $module->name : 'Create a future module' }}</h1><p class="muted">Maintain the licensing catalog without activating unfinished features.</p></div>
    <a href="{{ route('platform.modules.index') }}" class="btn btn-outline">← Module registry</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide form-panel">
        <form method="POST" action="{{ $module ? route('platform.modules.update', $module) : route('platform.modules.store') }}" class="form-stack">
            @csrf @if($module) @method('PUT') @endif
            @if($module)
                <div class="hint-box">Module code: <strong>{{ $module->code }}</strong> · {{ $module->is_available ? 'Released in application code' : 'Not yet released' }}. The code and release status are intentionally immutable in this admin form.</div>
            @else
                <label for="code">Unique module code *</label>
                <input id="code" name="code" maxlength="80" required pattern="[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)+" value="{{ old('code') }}" placeholder="integrations.accounting">
            @endif
            <label for="name">Display name *</label>
            <input id="name" name="name" maxlength="120" required value="{{ old('name', $module?->name) }}" placeholder="Accounting integration">
            <label for="category">Category *</label>
            <input id="category" name="category" maxlength="60" required value="{{ old('category', $module?->category ?? 'General') }}" placeholder="Integrations">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" maxlength="3000">{{ old('description', $module?->description) }}</textarea>
            <label for="monthly_price">Monthly add-on price (LKR, catalog only)</label>
            <input type="number" name="monthly_price" id="monthly_price" step="0.01" min="0" max="10000000" required value="{{ old('monthly_price', $module ? number_format($module->monthly_price_minor / 100, 2, '.', '') : '0.00') }}">
            <button class="btn btn-primary" type="submit">Save module →</button>
        </form>
    </section>
    @if($module)
        <section class="panel">
            <div class="panel-heading"><div><h2>Included in plans</h2><p class="muted">Plan entitlements are edited under Plans</p></div></div>
            <div class="stack-list">
                @forelse($module->plans as $plan)
                    <div class="stack-row"><span class="circle-initial">▥</span><span class="stack-grow"><strong>{{ $plan->name }}</strong><small>{{ $plan->slug }}</small></span><a class="text-link" href="{{ route('platform.plans.edit', $plan) }}">Configure</a></div>
                @empty <p class="empty-box muted">Not included in any plan.</p>@endforelse
            </div>
        </section>
    @endif
</div>
@endsection
