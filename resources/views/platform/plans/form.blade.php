@extends('layouts.app')
@section('title', $plan ? 'Edit plan' : 'Create plan')
@section('header', $plan ? 'Configure '.$plan->name : 'Create a plan')
@section('content')
<div class="page-head">
    <div><span class="overline">PLAN CONFIGURATION</span><h1>{{ $plan ? 'Edit subscription plan' : 'New subscription plan' }}</h1><p class="muted">Select included modules, set pricing and control catalog visibility.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.plans.index') }}">← All plans</a>
</div>
<form action="{{ $plan ? route('platform.plans.update', $plan) : route('platform.plans.store') }}" method="POST" class="detail-grid">
    @csrf
    @if($plan) @method('PUT') @endif
    <section class="panel">
        <div class="panel-heading"><div><h2>Plan details</h2><p class="muted">Changes to inclusions affect all tenants assigned this plan</p></div></div>
        <div class="form-stack">
            <label for="name">Plan name</label>
            <input name="name" id="name" maxlength="120" required value="{{ old('name', $plan?->name) }}" placeholder="Business Pro">
            <label for="slug">Plan code</label>
            <input name="slug" id="slug" maxlength="80" required value="{{ old('slug', $plan?->slug) }}" pattern="[a-zA-Z0-9_-]+" placeholder="business-pro">
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="3">{{ old('description', $plan?->description) }}</textarea>
            <label for="monthly_price">Monthly price</label>
            <input type="number" name="monthly_price" id="monthly_price" min="0" max="10000000" step="0.01" required value="{{ old('monthly_price', $plan ? number_format($plan->monthly_price_minor / 100, 2, '.', '') : '0.00') }}">
            <label for="yearly_price">Yearly price (optional, blank = unavailable)</label>
            <input type="number" name="yearly_price" id="yearly_price" min="0" max="10000000" step="0.01" value="{{ old('yearly_price', $plan?->yearly_price_minor !== null ? number_format($plan->yearly_price_minor / 100, 2, '.', '') : '') }}" placeholder="Annual subscription amount">
            <label for="currency">Currency</label>
            <select name="currency" id="currency">
                @foreach(['LKR', 'USD'] as $currency)
                    <option value="{{ $currency }}" @selected(old('currency', $plan?->currency ?? 'LKR') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
            <div class="plan-discount-box" data-plan-discount>
                <input type="hidden" name="is_discount_available" value="0">
                <label class="check-line" for="is_discount_available" style="gap:12px">
                    <input id="is_discount_available" name="is_discount_available" type="checkbox" value="1"
                        @checked(old('is_discount_available', $plan?->is_discount_available ?? false))>
                    <span><strong>Is discount available?</strong><small class="table-subtext">Apply a percentage discount to monthly and yearly plan prices.</small></span>
                </label>
                <div data-discount-percentage-wrap>
                    <label for="discount_percentage">Discount percentage (%)</label>
                    <input type="number" id="discount_percentage" name="discount_percentage" min="0.01" max="99.99" step="0.01"
                        value="{{ old('discount_percentage', $plan?->discount_percentage) }}" placeholder="e.g. 10.00">
                    <p class="muted">The discount is applied to both billing cycles; existing pending payment requests retain their submitted price.</p>
                </div>
                <div class="hint-box" data-discount-preview aria-live="polite">Price preview appears here.</div>
            </div>
            <label for="max_branches">Maximum active branches (blank = unlimited)</label>
            <input type="number" id="max_branches" name="max_branches" min="1" max="100000" value="{{ old('max_branches', $plan?->max_branches) }}" placeholder="Unlimited">
            <label for="max_employees">Maximum active employees including owner (blank = unlimited)</label>
            <input type="number" id="max_employees" name="max_employees" min="1" max="100000" value="{{ old('max_employees', $plan?->max_employees) }}" placeholder="Unlimited">
            <label for="is_active">Plan availability</label>
            <select id="is_active" name="is_active"><option value="1" @selected(old('is_active', $plan?->is_active ?? true) == 1)>Active</option><option value="0" @selected(old('is_active', $plan?->is_active ?? true) == 0)>Inactive</option></select>
            <label for="is_public">Catalog visibility flag</label>
            <select id="is_public" name="is_public"><option value="0" @selected(old('is_public', $plan?->is_public ?? false) == 0)>Unpublished</option><option value="1" @selected(old('is_public', $plan?->is_public ?? false) == 1)>Published flag</option></select>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Included modules</h2><p class="muted">Planned modules remain unavailable until their software is released.</p></div></div>
        @php $selected = old('modules', $plan?->modules->pluck('id')->all() ?? []); @endphp
        <div class="module-checkboxes">
            @foreach($modules as $module)
                <label class="module-choice">
                    <input type="checkbox" name="modules[]" value="{{ $module->id }}" @checked(in_array($module->id, $selected))>
                    <span><strong>{{ $module->name }}</strong><small>{{ $module->code }} · {{ $module->category }}</small></span>
                    <span class="status status-{{ $module->is_available ? 'active' : 'expired' }}">{{ $module->is_available ? 'Ready' : 'Planned' }}</span>
                </label>
            @endforeach
        </div>
    </section>
    <section class="panel panel-wide">
        <p class="muted">This is a catalog/licensing configuration. No charge, invoice or payment gateway operation occurs when saving.</p>
        <button type="submit" class="btn btn-primary">Save plan & inclusions →</button>
    </section>
</form>
@endsection
