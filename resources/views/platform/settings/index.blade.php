@extends('layouts.app')
@section('title', 'Platform settings')
@section('header', 'Platform settings')
@section('content')
<div class="page-head"><div><span class="overline">ADMINISTRATION</span><h1>Platform configuration</h1><p class="muted">Defaults for newly created accounts, registration and trial enrollment.</p></div></div>
<div class="dashboard-grid">
    <section class="panel panel-wide form-panel">
        <div class="panel-heading"><div><h2>Business onboarding defaults</h2><p class="muted">Existing tenants and active trial dates remain unchanged.</p></div></div>
        <form method="POST" action="{{ route('platform.settings.update') }}" class="form-stack">
            @csrf @method('PUT')
            <label for="registration_enabled">Public account registration</label>
            <select name="registration_enabled" id="registration_enabled">
                <option value="1" @selected($settings['registration_enabled'])>Enabled — allow new accounts</option>
                <option value="0" @selected(!$settings['registration_enabled'])>Disabled — close new signups</option>
            </select>
            <div class="hint-box">Disabling public registration also prevents invited people without an account from self-registering. Existing users can still sign in and reset passwords.</div>
            <label for="trial_days">Default trial duration (days)</label>
            <input name="trial_days" id="trial_days" type="number" min="1" max="90" value="{{ old('trial_days', $settings['trial_days']) }}" required>
            <label for="default_currency">Default business currency</label>
            <select name="default_currency" id="default_currency">
                @foreach(['LKR','USD'] as $currency)<option value="{{ $currency }}" @selected(old('default_currency', $settings['default_currency']) === $currency)>{{ $currency }}</option>@endforeach
            </select>
            <label for="default_timezone">Default business timezone</label>
            <select name="default_timezone" id="default_timezone">
                @foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('default_timezone', $settings['default_timezone']) === $timezone)>{{ $timezone }}</option>@endforeach
            </select>
            <button class="btn btn-primary" type="submit" data-confirm="Apply these platform onboarding settings to future registrations?">Save platform settings</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Security & integration readiness</h2><p class="muted">Important operational safeguards</p></div></div>
        <div class="setup-checklist">
            <div><span class="check-icon">✓</span><div><strong>Verified admin accounts</strong><small>Platform admin promotions require a permanent super administrator's password</small></div></div>
            <div><span class="check-icon">✓</span><div><strong>Tenant access isolation</strong><small>All business operations validate the requested tenant</small></div></div>
            <div><span class="check-icon">✓</span><div><strong>Audit trail</strong><small>Administrative updates produce tracked records</small></div></div>
            <div><span class="circle-initial">◷</span><div><strong>Gateway / settlement</strong><small>Not integrated; merchant onboarding, settlement and finance require a separate approved payment arrangement</small></div></div>
        </div>
    </section>
</div>
@endsection
