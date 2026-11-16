@extends('layouts.auth')
@section('title','Set up your business')
@section('content')
    <span class="overline">STEP 1 OF 1 · BUSINESS ONBOARDING</span>
    <h2>Tell us about your business.</h2>
    <p class="muted">We will create an isolated business workspace, a main branch and your {{ config('saas.default_trial_days') }}-day trial. Your data remains after upgrading.</p>
    <form method="POST" action="{{ route('onboarding.store') }}" class="form-stack">@csrf
        <label for="name">Business name <span class="required">*</span></label>
        <input id="name" name="name" type="text" maxlength="150" value="{{ old('name') }}" required autofocus placeholder="Example: Green Leaf Retail">
        <label for="contact_email">Business contact email</label>
        <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', auth()->user()->email) }}" placeholder="contact@business.com">
        <label for="phone">Phone number</label>
        <input id="phone" name="phone" type="tel" maxlength="30" value="{{ old('phone') }}" placeholder="+94 77 123 4567">
        <label for="address">Main branch address</label>
        <textarea id="address" name="address" rows="3" placeholder="Street, city">{{ old('address') }}</textarea>
        <div class="hint-box">✓ Your account is the business owner. You can add staff, permissions and branches in later modules.</div>
        <button class="btn btn-primary" type="submit">Create my workspace <span>→</span></button>
    </form>
@endsection
