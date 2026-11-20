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
        ['label' => 'Businesses', 'icon' => '▦', 'route' => 'platform.tenants.index', 'active' => 'platform.tenants.*', 'access' => ['operations']],
        ['label' => 'Customers & Users', 'icon' => '♧', 'route' => 'platform.users.index', 'active' => 'platform.users.*', 'access' => ['operations','support']],
        ['label' => 'Trials', 'icon' => '◷', 'route' => 'platform.trials.index', 'active' => 'platform.trials.*', 'access' => ['operations']],
        ['label' => 'Subscriptions', 'icon' => '▤', 'route' => 'platform.subscriptions.index', 'active' => 'platform.subscriptions.*', 'access' => ['billing']],
        ['label' => 'Payment Setup', 'icon' => '▣', 'route' => 'platform.payments.index', 'active' => 'platform.payments.*', 'access' => ['billing']],
        ['label' => 'Plans', 'icon' => '◈', 'route' => 'platform.plans.index', 'active' => 'platform.plans.*', 'access' => ['billing']],
        ['label' => 'Modules', 'icon' => '◇', 'route' => 'platform.modules.index', 'active' => 'platform.modules.*', 'access' => ['billing']],
        ['label' => 'Reports', 'icon' => '▥', 'route' => 'platform.reports.index', 'active' => 'platform.reports.*', 'access' => ['auditor','operations','billing']],
        ['label' => 'Support Inbox', 'icon' => '✉', 'route' => 'platform.support.index', 'active' => 'platform.support.*', 'access' => ['support','operations']],
        ['label' => 'Announcements', 'icon' => '◉', 'route' => 'platform.announcements.index', 'active' => 'platform.announcements.*', 'access' => ['support','operations']],
        ['label' => 'Audit Log', 'icon' => '▨', 'route' => 'platform.audit.index', 'active' => 'platform.audit.*', 'access' => ['auditor']],
        ['label' => 'Administrators', 'icon' => '♙', 'route' => 'platform.admins.index', 'active' => 'platform.admins.*', 'access' => ['super_admin'], 'permanent' => true],
        ['label' => 'Settings', 'icon' => '⚙', 'route' => 'platform.settings.index', 'active' => 'platform.settings.*', 'access' => ['super_admin'], 'permanent' => true],
    ] : [
        ['label' => 'Dashboard', 'icon' => '◫', 'route' => 'business.dashboard', 'active' => 'business.dashboard'],
        ['label' => 'POS', 'icon' => '▣', 'route' => null],
        ['label' => 'Sales', 'icon' => '▤', 'route' => null],
        ['label' => 'Products & Stock', 'icon' => '◇', 'route' => null],
        ['label' => 'Employees', 'icon' => '♙', 'route' => 'business.employees.index', 'active' => 'business.employees.*', 'can' => 'employees.view', 'feature' => 'staff.core'],
        ['label' => 'Roles & Permissions', 'icon' => '⚿', 'route' => 'business.roles.index', 'active' => 'business.roles.*', 'can' => 'employees.view', 'feature' => 'staff.core'],
        ['label' => 'Subscription', 'icon' => '▥', 'route' => 'business.subscription.index', 'active' => 'business.subscription.*'],
        ['label' => 'Support', 'icon' => '✉', 'route' => 'business.support.index', 'active' => 'business.support.*', 'can' => 'settings.manage'],
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
                    if ($platform && isset($item['access'])) {
                        $role = auth()->user()->platformRole();
                        $visible = ($role === 'super_admin' || in_array($role, $item['access'], true))
                            && (!isset($item['permanent']) || auth()->user()->isPermanentPlatformAdmin());
                    }
                    if (!$platform && isset($item['can'])) {
                        $authorization = app(\App\Services\TenantAuthorization::class);
                        $featureAccess = app(\App\Services\FeatureAccess::class);
                        $visible = $authorization->allows(auth()->user(), $tenant, $item['can'])
                            && (!isset($item['feature']) || $featureAccess->allows($tenant, $item['feature']));
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
            @if(!$platform)
                <a class="nav-pill" href="{{ route('onboarding.create') }}"><span class="nav-icon">＋</span> Add business</a>
            @elseif(in_array(auth()->user()->platformRole(), ['super_admin','operations'], true))
                <a class="nav-pill" href="{{ route('platform.tenants.create') }}"><span class="nav-icon">＋</span> Add business</a>
            @endif
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
