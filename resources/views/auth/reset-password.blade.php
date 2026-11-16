@extends('layouts.auth')
@section('title','Set new password')
@section('content')
    <span class="overline">ACCOUNT RECOVERY</span>
    <h2>Create a new password.</h2>
    <form method="POST" action="{{ route('password.update') }}" class="form-stack">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
        <label for="password">New password</label>
        <input id="password" type="password" name="password" required minlength="12" autocomplete="new-password">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        <button class="btn btn-primary" type="submit">Reset password</button>
    </form>
@endsection
