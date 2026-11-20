@extends('layouts.app')
@section('title','Create business')
@section('header','Create a business')
@section('content')
<div class="page-head">
    <div><span class="overline">BUSINESS ONBOARDING</span><h1>Provision a workspace</h1><p class="muted">Register an isolated business for an existing verified owner. A default branch, owner permissions and a trial are provisioned automatically.</p></div>
    <a href="{{ route('platform.tenants.index') }}" class="btn btn-outline">← Business directory</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Business profile</h2><p class="muted">Business identity, contact information and location</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.store') }}" enctype="multipart/form-data" class="form-stack">
            @csrf
            <label for="name">Business name *</label>
            <input id="name" name="name" maxlength="150" required value="{{ old('name') }}" placeholder="Example: Sunrise Mart">
            <label for="owner_email">Verified owner email *</label>
            <input id="owner_email" name="owner_email" type="email" required maxlength="191" value="{{ old('owner_email') }}" placeholder="owner@business.com">
            <div class="tenant-fields-grid">
                <div><label for="contact_person">Contact person</label><input id="contact_person" name="contact_person" maxlength="150" value="{{ old('contact_person') }}" placeholder="Primary contact"></div>
                <div><label for="contact_email">Business contact email</label><input id="contact_email" type="email" name="contact_email" maxlength="191" value="{{ old('contact_email') }}" placeholder="contact@business.com"></div>
                <div><label for="phone">Business phone</label><input id="phone" name="phone" maxlength="30" value="{{ old('phone') }}" placeholder="+94 77 123 4567"></div>
                <div><label for="country">Country (ISO 2-letter code)</label><input id="country" name="country" pattern="[a-zA-Z]{2}" maxlength="2" value="{{ old('country') }}" placeholder="LK"></div>
                <div><label for="website">Website (HTTPS)</label><input id="website" type="url" name="website" maxlength="255" value="{{ old('website') }}" placeholder="https://example.com"></div>
                <div><label for="registration_number">Business registration number</label><input id="registration_number" name="registration_number" maxlength="100" value="{{ old('registration_number') }}" placeholder="Registration reference"></div>
            </div>
            <label for="address">Main branch address</label>
            <textarea id="address" name="address" rows="3" maxlength="1000" placeholder="Street, city, postal code">{{ old('address') }}</textarea>
            <label for="logo">Business logo (optional)</label>
            <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp,.jpg,.jpeg,.png,.webp">
            <p class="muted">JPEG, PNG or WebP, maximum 2 MB. The file is stored privately.</p>
            <div class="hint-box">This creates a trial with the platform's current default duration. You can change individual trial dates from Trial Management.</div>
            <button class="btn btn-primary" type="submit">Create business →</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Owner account required</h2><p class="muted">No shared passwords or unverified accounts</p></div></div>
        <p class="muted">The business owner must have verified their email address. Send an account invitation first if this person isn't already a POS Cloud user.</p>
        <a href="{{ route('platform.users.index') }}" class="btn btn-outline">Open Customers & Users →</a>
    </section>
</div>
@endsection
