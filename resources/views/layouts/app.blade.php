<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f3f6">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'POS SaaS') }}</title>
    <script>
        try { document.documentElement.dataset.theme = localStorage.getItem('pos-theme') === 'dark' ? 'dark' : 'light'; } catch (_) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    $platform = request()->routeIs('platform.*');
    $navItems = $platform ? [
        ['label' => 'Overview', 'icon' => '◫', 'route' => 'platform.dashboard', 'active' => 'platform.dashboard'],
        ['label' => 'Businesses', 'icon' => '▦', 'route' => 'platform.tenants.index', 'active' => 'platform.tenants.*'],
        ['label' => 'Trials', 'icon' => '◷', 'route' => 'platform.trials.index', 'active' => 'platform.trials.*'],
        ['label' => 'Plans & Modules', 'icon' => '◈', 'route' => 'platform.plans.index', 'active' => 'platform.plans.*'],
        ['label' => 'Platform Reports', 'icon' => '▥', 'route' => null],
    ] : [
        ['label' => 'Dashboard', 'icon' => '◫', 'route' => 'business.dashboard', 'active' => 'business.dashboard'],
        ['label' => 'POS', 'icon' => '▣', 'route' => null],
        ['label' => 'Sales', 'icon' => '▤', 'route' => null],
        ['label' => 'Products & Stock', 'icon' => '◇', 'route' => null],
        ['label' => 'Employees', 'icon' => '♙', 'route' => 'business.employees.index', 'active' => 'business.employees.*', 'can' => 'employees.view', 'feature' => 'staff.core'],
        ['label' => 'Roles & Permissions', 'icon' => '⚿', 'route' => 'business.roles.index', 'active' => 'business.roles.*', 'can' => 'employees.view', 'feature' => 'staff.core'],
        ['label' => 'Subscription', 'icon' => '▥', 'route' => 'business.subscription.index', 'active' => 'business.subscription.*'],
        ['label' => 'Reports', 'icon' => '▨', 'route' => null],
    ];
@endphp
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ $platform ? route('platform.dashboard') : route('business.dashboard', $tenant) }}">
            <span class="brand-mark">✳</span>
            <span><strong>POS Cloud</strong><small>{{ $platform ? 'Platform Console' : 'Business Workspace' }}</small></span>
        </a>
        <div class="sidebar-section-label">{{ $platform ? 'PLATFORM' : 'WORKSPACE' }}</div>
        <nav class="side-nav" aria-label="Main navigation">
            @foreach ($navItems as $item)
                @php
                    $visible = true;
                    if (!$platform && isset($item['can'])) {
                        $authorization = app(\App\Services\TenantAuthorization::class);
                        $featureAccess = app(\App\Services\FeatureAccess::class);
                        $visible = $authorization->allows(auth()->user(), $tenant, $item['can'])
                            && $featureAccess->allows($tenant, $item['feature']);
                    }
                @endphp
                @if (!$visible)
                    @continue
                @endif
                @if ($item['route'])
                    <a class="nav-pill {{ request()->routeIs($item['active'] ?? 'never') ? 'is-active' : '' }}"
                        href="{{ $platform ? route($item['route'], $item['query'] ?? []) : route($item['route'], $tenant) }}">
                        <span class="nav-icon" aria-hidden="true">{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <span class="nav-pill is-disabled" title="Available in a later module" aria-disabled="true">
                        <span class="nav-icon" aria-hidden="true">{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span><small>Soon</small>
                    </span>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-bottom">
            @if (!$platform && isset($tenant))
                <div class="workspace-chip"><span class="workspace-avatar">{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}</span>
                    <span><strong>{{ $tenant->name }}</strong><small>{{ ucfirst($tenant->effective_status) }} workspace</small></span>
                </div>
            @endif
            <a class="nav-pill" href="{{ route('onboarding.create') }}"><span class="nav-icon">＋</span> Add business</a>
            <form action="{{ route('logout') }}" method="post">@csrf
                <button type="submit" class="nav-pill logout-button"><span class="nav-icon">↪</span> Sign out</button>
            </form>
        </div>
    </aside>
    <div class="main-area">
        <header class="topbar">
            <button class="mobile-menu" type="button" data-menu-toggle aria-label="Toggle menu">☰</button>
            <div class="topbar-title"><span class="eyebrow">{{ $platform ? 'Platform administration' : 'Business overview' }}</span>
                <strong>@yield('header', 'Dashboard')</strong></div>
            <div class="topbar-actions">
                <span class="topbar-user">{{ auth()->user()->name }}</span>
                <button type="button" class="icon-button js-theme-toggle" aria-label="Toggle dark mode"><span class="theme-symbol">☾</span></button>
                <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            </div>
        </header>
        <main class="page-content">
            @if(session('status'))<div class="flash-success" role="status">✓ {{ session('status') }}</div>@endif
            @if($errors->any())
                <div class="flash-error" role="alert"><strong>Please correct the following:</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
