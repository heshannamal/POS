@extends('layouts.auth')
@section('title','Account recovery codes')
@section('content')
<span class="overline">AUTHENTICATOR RECOVERY</span>
<h2>Your account recovery codes</h2>
@if(count($codes))
    <p class="muted">These codes are displayed <strong>once</strong>. Save all eight codes in an offline password manager or another safe location. They cannot be retrieved after you leave this page.</p>
    <div class="security-code-grid">
        @foreach($codes as $code)<code>{{ $code }}</code>@endforeach
    </div>
    <button type="button" class="btn btn-outline" data-copy-recovery="{{ implode("\n",$codes) }}">Copy recovery codes</button>
@else
    <p class="muted">Recovery codes are hidden after their initial display. You may generate new codes using your password and authenticator app.</p>
@endif
<hr style="margin:22px 0;border:0;border-top:1px solid var(--border)">
<h2>Regenerate recovery codes</h2>
<form method="POST" action="{{ route('security.regenerate') }}" class="form-stack">@csrf
    <label for="reg_password">Current password</label>
    <input id="reg_password" name="password" type="password" autocomplete="current-password" required>
    <label for="reg_code">Authenticator code</label>
    <input id="reg_code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
    <button class="btn btn-secondary" data-confirm="Regenerate codes? All previous unused recovery codes will stop working.">Generate new recovery codes</button>
</form>
@if(!config('platform_security.require_2fa',false) || !auth()->user()->isPermanentPlatformAdmin() || auth()->user()->platformRole()!=='super_admin')
    <details style="margin-top:22px">
        <summary>Disable two-factor authentication</summary>
        <form method="POST" action="{{ route('security.disable') }}" class="form-stack" style="margin-top:13px">@csrf
            <label>Current password</label><input name="password" type="password" autocomplete="current-password" required>
            <label>Authenticator code</label><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
            <button class="btn btn-outline" data-confirm="Disable two-factor protection on this account?">Disable authenticator</button>
        </form>
    </details>
@endif
<p class="auth-switch"><a href="{{ route('platform.dashboard') }}">Continue to Platform Dashboard →</a> · <a href="{{ route('security.sessions') }}">My sessions</a></p>
@endsection
