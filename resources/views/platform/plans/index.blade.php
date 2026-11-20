@extends('layouts.app')
@section('title', 'Plans and modules')
@section('header', 'Plans & modules')
@section('content')
<div class="page-head">
    <div><span class="overline">SUBSCRIPTION CATALOG</span><h1>Plans & module licensing</h1><p class="muted">Define packages and feature access. Payment collection is not enabled yet.</p></div>
    <a class="btn btn-primary" href="{{ route('platform.plans.create') }}">＋ Create plan</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Subscription plans</h2><p class="muted">Prices are stored in the smallest currency unit. Configure them before publishing.</p></div></div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Plan</th><th>Monthly price</th><th>Modules included</th><th>Visibility</th><th></th></tr></thead>
                <tbody>
                    @foreach($plans as $plan)
                        <tr>
                            <td><strong>{{ $plan->name }}</strong><div class="muted">{{ $plan->slug }}</div></td>
                            <td>{{ $plan->currency }} {{ number_format($plan->monthly_price_minor / 100, 2) }}</td>
                            <td>{{ $plan->modules->count() }}</td>
                            <td><span class="status status-{{ $plan->is_active ? ($plan->is_public ? 'active' : 'trial') : 'suspended' }}">{{ ! $plan->is_active ? 'Inactive' : ($plan->is_public ? 'Published flag' : 'Unpublished') }}</span></td>
                            <td><a href="{{ route('platform.plans.edit', $plan) }}" class="btn btn-small btn-outline">Configure →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Module registry</h2><p class="muted">Feature release state and billing placeholder</p></div></div>
        <div class="stack-list">
            @foreach($modules as $module)
                <div class="stack-row">
                    <span class="circle-initial">◇</span>
                    <span class="stack-grow"><strong>{{ $module->name }}</strong><small>{{ $module->code }} · {{ $module->category }}</small></span>
                    <span class="status status-{{ $module->is_available ? 'active' : 'expired' }}">{{ $module->is_available ? 'Ready' : 'Planned' }}</span>
                </div>
            @endforeach
        </div>
        <p class="muted subtle-note">A planned module is not accessible even if included in a plan. Module prices and checkout will be activated in the billing phase.</p>
    </section>
</div>
@endsection
