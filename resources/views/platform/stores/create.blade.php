@extends('layouts.app')
@section('title','Add Store')
@section('header','Create store')
@section('content')
<div class="page-head">
    <div><span class="overline">NEW STORE</span><h1>Create store / branch</h1><p class="muted">Add a store to an existing business without creating another tenant, employee account or subscription.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.stores.index') }}">← Global stores</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Store details</h2><p class="muted">Active business required. Store limits are enforced by its subscription plan.</p></div></div>
        <form method="POST" action="{{ route('platform.stores.store') }}" class="form-stack">
            @csrf
            <label for="new_store_tenant">Business *</label>
            <select name="tenant_id" id="new_store_tenant" required>
                <option value="">Choose an existing business</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected((string)old('tenant_id',request('tenant_id'))===(string)$tenant->id)>{{ $tenant->name }} · {{ $tenant->subscription?->plan?->name ?? 'No plan' }}</option>
                @endforeach
            </select>
            <div class="tenant-fields-grid">
                <div><label for="new_store_name">Store name *</label><input id="new_store_name" name="name" maxlength="120" required value="{{ old('name') }}" placeholder="Downtown Store"></div>
                <div><label for="new_store_code">Unique store code *</label><input id="new_store_code" name="code" maxlength="30" pattern="[a-zA-Z0-9_-]+" required value="{{ old('code') }}" placeholder="DOWNTOWN"></div>
                <div><label for="new_store_phone">Phone</label><input id="new_store_phone" name="phone" maxlength="30" value="{{ old('phone') }}" placeholder="Branch telephone"></div>
                <div><label for="new_store_timezone">Timezone</label><select name="timezone" id="new_store_timezone"><option value="">Inherit business timezone</option>
                    @foreach(timezone_identifiers_list() as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone')===$timezone)>{{ $timezone }}</option>
                    @endforeach
                </select></div>
            </div>
            <label for="new_store_address">Full address</label>
            <textarea name="address" id="new_store_address" rows="4" maxlength="1000" placeholder="Street, city, postal code">{{ old('address') }}</textarea>
            <div class="hint-box">This creates an active, non-default store. Manager, staff, devices and overrides can be configured after creation.</div>
            <button class="btn btn-primary" type="submit">Create store →</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Need a new business?</h2><p class="muted">Business onboarding is separate from store registration.</p></div></div>
        <p class="muted">A business has its own owner, plan, country, trial and user roles. Each store belongs to exactly one business.</p>
        <a class="btn btn-outline" href="{{ route('platform.tenants.create') }}">Create a business →</a>
    </section>
</div>
@endsection
