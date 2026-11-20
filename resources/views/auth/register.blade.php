@extends('layouts.auth')
@section('title','Create account')
@section('content')
    <span class="overline">START YOUR FREE TRIAL</span>
    <h2>Create your account.</h2>
    <p class="muted">First create your account, then set up your business. No demo data migrations required.</p>
    <form method="POST" action="{{ route('register') }}" class="form-stack">
        @csrf
        <label for="name">Full name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" maxlength="120" placeholder="Your name">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@business.com">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required minlength="12" autocomplete="new-password" placeholder="Minimum 12 characters, letters and numbers">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat password">
        <button class="btn btn-primary" type="submit">Create account <span>→</span></button>
    </form>
    <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
