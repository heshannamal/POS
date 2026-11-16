@extends('layouts.app')
@section('title','Add business')
@section('header','Create a business')
@section('content')
<div class="page-head">
    <div><span class="overline">NEW TENANT</span><h1>Provision a workspace</h1><p class="muted">Each business receives isolated data, an owner account, a main branch and a trial.</p></div>
    <a href="{{ route('platform.tenants.index') }}" class="btn btn-outline">← Back to businesses</a>
</div>
<section class="panel form-panel">
    <div class="panel-heading"><div><h2>Business information</h2><p class="muted">Owner must already have a verified POS Cloud account.</p></div></div>
    <form method="POST" action="{{ route('platform.tenants.store') }}" class="form-stack">@csrf
        <label for="name">Business name <span class="required">*</span></label>
        <input name="name" id="name" required maxlength="150" value="{{ old('name') }}" placeholder="Example: Sunrise Mart">
        <label for="owner_email">Verified owner's email <span class="required">*</span></label>
        <input name="owner_email" id="owner_email" required type="email" value="{{ old('owner_email') }}" placeholder="owner@business.com">
        <label for="contact_email">Business contact email</label>
        <input name="contact_email" id="contact_email" type="email" value="{{ old('contact_email') }}" placeholder="contact@business.com">
        <label for="phone">Phone number</label>
        <input name="phone" id="phone" value="{{ old('phone') }}" maxlength="30" placeholder="+94 77 123 4567">
        <label for="address">Main branch address</label>
        <textarea name="address" id="address" rows="3" maxlength="1000" placeholder="Street, city">{{ old('address') }}</textarea>
        <div class="hint-box">The new business automatically starts a {{ config('saas.default_trial_days') }}-day trial. You can extend it from its details page.</div>
        <button class="btn btn-primary" type="submit">Create business →</button>
    </form>
</section>
@endsection
