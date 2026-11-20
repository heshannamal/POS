<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f3f6">
    <title>@yield('title', 'Welcome') — {{ config('app.name', 'POS SaaS') }}</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('pos-theme') === 'dark' ? 'dark' : 'light'; } catch (_) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
<div class="auth-page">
    <section class="auth-intro">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark">✳</span><span><strong>POS Cloud</strong><small>One workspace. Every sale.</small></span></a>
        <div class="auth-art">
            <span class="overline">YOUR BUSINESS, CONNECTED</span>
            <h1>Modern commerce.<br><em>Simpler management.</em></h1>
            <p>Operate your stores, employees, products and business subscriptions in one beautifully connected workspace.</p>
            <div class="auth-preview">
                <div class="preview-tile lavender">◫ <strong>One business dashboard</strong><span>Track every part of your business</span></div>
                <div class="preview-tile mint">◇ <strong>Inventory that stays yours</strong><span>Your data remains when your trial ends</span></div>
                <div class="preview-tile peach">◷ <strong>Get started with a trial</strong><span>Try the platform before purchasing</span></div>
            </div>
        </div>
        <small class="auth-copyright">© {{ date('Y') }} POS Cloud. Built for growing businesses.</small>
    </section>
    <section class="auth-form-section">
        <div class="auth-form-top"><button type="button" class="icon-button js-theme-toggle" aria-label="Toggle dark mode"><span class="theme-symbol">☾</span></button></div>
        <div class="auth-form-container">
            @if(session('status'))<div class="flash-success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="flash-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </section>
</div>
</body>
</html>
