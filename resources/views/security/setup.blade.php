@extends('layouts.auth')
@section('title','Set up authenticator')
@section('content')
<span class="overline">PLATFORM ACCOUNT SECURITY</span>
<h2>Protect your administrator account</h2>
<p class="muted">A six-digit authenticator code is required to protect privileged access. Use Google Authenticator, Microsoft Authenticator, 1Password or another TOTP app.</p>
<div class="hint-box">This secret is private. Never share it with support or paste it into a public QR-code website. For security, the manual key is shown only during enrollment.</div>
<div class="security-secret">
    <small>Manual setup key</small>
    <code>{{ $secret }}</code>
    <button class="btn btn-outline" type="button" data-copy-secret="{{ $secret }}">Copy setup key</button>
</div>
<details class="security-uri"><summary>Advanced: authenticator URI</summary>
    <code class="security-break">{{ $uri }}</code>
</details>
<form method="POST" action="{{ route('security.enable') }}" class="form-stack">
    @csrf
    <label for="confirm_password">Confirm your account password</label>
    <input type="password" id="confirm_password" name="password" autocomplete="current-password" required>
    <label for="totp_code">Current 6-digit authenticator code</label>
    <input type="text" id="totp_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="000000" required>
    <button class="btn btn-primary" type="submit">Enable two-factor authentication →</button>
</form>
<p class="muted" style="margin-top:16px">After confirming you will receive 8 single-use recovery codes. Store them securely offline.</p>
<form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline" type="submit">Sign out</button></form>
@endsection
