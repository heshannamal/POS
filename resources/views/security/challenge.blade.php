@extends('layouts.auth')
@section('title','Authenticator challenge')
@section('content')
<span class="overline">SECOND FACTOR REQUIRED</span>
<h2>Verify your sign-in</h2>
<p class="muted">Enter the six-digit code from your authenticator app, or use a previously saved one-time recovery code.</p>
<form method="POST" action="{{ route('security.verify') }}" class="form-stack">
    @csrf
    <label for="auth_code">Authenticator code</label>
    <input id="auth_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" autofocus>
    <div class="security-or"><span>OR</span></div>
    <label for="recovery_code">One-time recovery code</label>
    <input id="recovery_code" name="recovery_code" type="text" maxlength="30" autocomplete="off" placeholder="Recovery code (10 characters)">
    <p class="muted">Use only one method. Each recovery code can be used once.</p>
    <button class="btn btn-primary" type="submit">Verify and continue →</button>
</form>
<form method="POST" action="{{ route('logout') }}" style="margin-top:18px">@csrf
    <button type="submit" class="btn btn-outline">Sign out</button>
</form>
@endsection
