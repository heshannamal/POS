@extends('layouts.auth')
@section('title','Accept POS Cloud invitation')
@section('content')
<span class="overline">INVITED BY THE PLATFORM TEAM</span>
<h2>Activate your POS Cloud account.</h2>
<p class="muted">You've been invited as <strong>{{ $invitation->name }}</strong> using <strong>{{ $invitation->email }}</strong>. Set a password to finish creating your verified account.</p>
<form method="POST" action="{{ route('platform-invite.accept', $token) }}" class="form-stack">
    @csrf
    <div class="hint-box">This single-use invitation expires {{ $invitation->expires_at->format('d M Y H:i') }}.</div>
    <label for="password">Create a strong password</label>
    <input id="password" name="password" type="password" minlength="12" required autocomplete="new-password" placeholder="At least 12 characters, letters and numbers">
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password" placeholder="Repeat password">
    <button class="btn btn-primary" type="submit">Activate account →</button>
</form>
@endsection
