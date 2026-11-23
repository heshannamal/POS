@extends('layouts.app')
@section('title','Email Settings')
@section('header','Email sending settings')
@section('content')
<div class="page-head">
    <div><span class="overline">MAIL CONFIGURATION</span><h1>SMTP & test sending</h1><p class="muted">Transport credentials are read from your server .env file. This dashboard intentionally never shows or saves passwords.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.communications.deliveries.index') }}">Delivery logs →</a>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">✉</span><span class="stat-label">Transport</span><strong class="stat-text">{{ strtoupper($settings['mail_transport']) }}</strong><small>From MAIL_MAILER</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">◉</span><span class="stat-label">Outbound email</span><strong class="stat-text">{{ $settings['email_enabled']?'Enabled':'Disabled' }}</strong><small>Global kill switch</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">♙</span><span class="stat-label">Test mode</span><strong class="stat-text">{{ $settings['test_mode']?'Enabled':'Disabled' }}</strong><small>Redirects to allowlist</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">⚿</span><span class="stat-label">SMTP credentials</span><strong class="stat-text">{{ $settings['smtp_username_configured']?'Username set':'Not configured' }}</strong><small>Password value never displayed</small></div>
</div>
<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Delivery controls</h2><p class="muted">Default is outbound email disabled and test mode on to prevent accidental customer emails.</p></div></div>
        <form method="POST" action="{{ route('platform.communications.settings.update') }}" class="form-stack">
            @csrf @method('PUT')
            <label class="check-line"><input type="hidden" name="email_enabled" value="0">
                <input type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled',$settings['email_enabled']))> Enable Phase 5 automated emails</label>
            <label class="check-line"><input type="hidden" name="test_mode" value="0">
                <input type="checkbox" name="test_mode" value="1" @checked(old('test_mode',$settings['test_mode']))> Test mode — redirect all recipients to allowlist</label>
            <label for="test_recipients">Approved test recipients (comma separated)</label>
            <textarea id="test_recipients" name="test_recipients" rows="3" maxlength="2000" placeholder="youraddress@gmail.com">{{ old('test_recipients',$settings['test_recipients']) }}</textarea>
            <p class="muted">Maximum five valid addresses. Real delivery requires an actual SMTP/transactional mailer (not the log/array transport).</p>
            <button class="btn btn-primary" data-confirm="Change outgoing email settings? Enabling live mode allows emails to real recipients.">Save email controls</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Configured sender</h2><p class="muted">Environment-only readout</p></div></div>
        <dl class="detail-list">
            <div><dt>Mailer</dt><dd>{{ $settings['mail_transport'] }}</dd></div>
            <div><dt>From</dt><dd>{{ $settings['sender_address'] }}</dd></div>
            <div><dt>Sender name</dt><dd>{{ $settings['sender_name'] }}</dd></div>
            <div><dt>SMTP host</dt><dd>{{ $settings['smtp_host_configured']?'Configured':'Missing' }}</dd></div>
            <div><dt>SMTP username</dt><dd>{{ $settings['smtp_username_configured']?'Configured':'Missing' }}</dd></div>
        </dl>
        <div class="hint-box">Google Gmail: enable two-step verification and create a Google App Password in your Google Account. Set MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_PORT and MAIL_SCHEME in your private .env file. Never paste credentials into this dashboard or GitHub.</div>
        <form method="POST" action="{{ route('platform.communications.test') }}" class="form-stack" style="margin-top:18px">
            @csrf
            <label for="test_recipient">Test mail to an allowlisted address</label>
            <input type="email" id="test_recipient" name="recipient" required maxlength="191" placeholder="youraddress@gmail.com">
            <button class="btn btn-secondary">Send SMTP diagnostic message</button>
        </form>
    </section>
</div>
<div class="hint-box" style="margin-top:16px">For queued emails start a worker: <code>php artisan queue:work --queue=communications,default</code>. For expiry reminders set up Laravel Scheduler. A transport accepting a message does not prove mailbox delivery.</div>
@endsection
