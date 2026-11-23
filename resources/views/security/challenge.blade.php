@extends('layouts.auth')
@section('title','Email verification')
@section('content')
<span class="overline">ADMINISTRATOR SECURITY</span>
<h2>Check your email</h2>
<p class="muted">Enter the six-digit verification code sent to <strong>{{ $email }}</strong>. The code expires after 10 minutes and can only be used once.</p>
<form method="POST" action="{{ route('security.verify') }}" class="form-stack">
    @csrf
    <label for="email_code">Email verification code</label>
    <input id="email_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>
    <button class="btn btn-primary" type="submit">Verify and continue →</button>
</form>
<form method="POST" action="{{ route('security.resend') }}" style="margin-top:15px">@csrf
    <button class="btn btn-outline" type="submit">Resend code</button>
</form>
<p class="muted">For security, you can request one new code per minute. A new code invalidates the previous code.</p>
<form method="POST" action="{{ route('logout') }}" style="margin-top:18px">@csrf
    <button type="submit" class="btn btn-outline">Sign out</button>
</form>
@endsection