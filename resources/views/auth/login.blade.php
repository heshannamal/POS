@extends('layouts.auth')
@section('title','Sign in')
@section('content')
    <span class="overline">WELCOME BACK</span>
    <h2>Sign in to your workspace.</h2>
    <p class="muted">Enter your credentials to access your business or platform console.</p>
    <form method="POST" action="{{ route('login') }}" class="form-stack">
        @csrf
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="you@business.com">
        <div class="label-row"><label for="password">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
        <input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Your password">
        <label class="check-line"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="btn btn-primary" type="submit">Sign in <span>→</span></button>
    </form>
    <p class="auth-switch">New to POS Cloud? <a href="{{ route('register') }}">Create an account</a></p>
@endsection
