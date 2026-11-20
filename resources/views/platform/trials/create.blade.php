@extends('layouts.app')
@section('title','Add trial user')
@section('header','Create trial user')
@section('content')
<div class="page-head">
    <div><span class="overline">NEW TRIAL BUSINESS</span><h1>Add a trial user</h1><p class="muted">Assign a trial workspace to an existing verified user. Their business, branches and roles are created with no payment required.</p></div>
    <a href="{{ route('platform.trials.index') }}" class="btn btn-outline">← All trials</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide form-panel">
        <div class="panel-heading"><div><h2>Trial user & business</h2><p class="muted">The owner must have a verified account before creating a trial.</p></div></div>
        <form method="POST" action="{{ route('platform.trials.store') }}" class="form-stack">
            @csrf
            <label for="owner_email">Trial user's registered email *</label>
            <input id="owner_email" name="owner_email" type="email" maxlength="191" required value="{{ old('owner_email') }}" placeholder="owner@example.com">
            <label for="name">Business / store name *</label>
            <input id="name" name="name" maxlength="150" required value="{{ old('name') }}" placeholder="Example Retail Store">
            <label for="contact_email">Business contact email (optional)</label>
            <input id="contact_email" type="email" name="contact_email" maxlength="191" value="{{ old('contact_email') }}">
            <label for="phone">Contact phone (optional)</label>
            <input id="phone" name="phone" maxlength="30" value="{{ old('phone') }}">
            <label for="address">Business address (optional)</label>
            <textarea id="address" name="address" rows="3" maxlength="1000">{{ old('address') }}</textarea>
            <label for="trial_days">Trial duration (1–90 days) *</label>
            <input id="trial_days" name="trial_days" type="number" min="1" max="90" required value="{{ old('trial_days', $defaultDays) }}">
            <div class="hint-box">This creates a real business workspace in trial status. Trial duration is configured for this business only. The trial can later be extended, deactivated, or reactivated. Existing business data is retained.</div>
            <button class="btn btn-primary" type="submit">Create trial account →</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Owner not registered?</h2><p class="muted">Start with a secure account invitation.</p></div></div>
        <p class="muted">First invite the customer from the user directory. When they accept the email invitation and verify their identity, return here and create the trial workspace using their email address.</p>
        <a href="{{ route('platform.users.index') }}" class="btn btn-outline">Open Customers & Users →</a>
        <div class="hint-box" style="margin-top:16px">No temporary password is generated or stored for the customer. Use the one-time email invitation instead.</div>
    </section>
</div>
@endsection
