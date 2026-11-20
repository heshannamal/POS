@extends('layouts.auth')
@section('title','Reset password')
@section('content')
    <span class="overline">ACCOUNT RECOVERY</span>
    <h2>Forgot your password?</h2>
    <p class="muted">Enter your email and we will send a reset link if the account exists.</p>
    <form method="POST" action="{{ route('password.email') }}" class="form-stack">@csrf
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" required value="{{ old('email') }}" autofocus placeholder="you@business.com">
        <button class="btn btn-primary" type="submit">Send reset link</button>
    </form>
    <p class="auth-switch"><a href="{{ route('login') }}">Back to login</a></p>
@endsection
