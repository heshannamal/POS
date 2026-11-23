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
        ['label' => 'Overview', 'icon' => '◫', 'route' => 'platform.dashboard', 'active' => 'platform.dashboard', 'access' => ['operations','billing','support','auditor']],
        ['label' => 'Businesses', 'icon' => '▦', 'route' => 'platform.tenants.index', 'active' => 'platform.tenants.*', 'access' => ['operations']],
        ['label' => 'All Stores', 'icon' => '▣', 'route' => 'platform.stores.index', 'active' => 'platform.stores.*', 'access' => ['operations','support']],
        ['label' => 'Tenant Staff', 'icon' => '♙', 'route' => 'platform.tenant-users.index', 'active' => 'platform.tenant-users.*', 'access' => ['operations','support']],
        ['label' => 'Customers & Users', 'icon' => '♧', 'route' => 'platform.users.index', 'active' => 'platform.users.*', 'access' => ['operations','support']],
        ['label' => 'Trials', 'icon' => '◷', 'route' => 'platform.trials.index', 'active' => 'platform.trials.*', 'access' => ['operations']],
        ['label' => 'Subscriptions', 'icon' => '▤', 'route' => 'platform.subscriptions.index', 'active' => 'platform.subscriptions.*', 'access' => ['billing']],
        ['label' => 'Payment Setup', 'icon' => '▣', 'route' => 'platform.payments.index', 'active' => 'platform.payments.index', 'access' => ['billing']],
        ['label' => 'Payment Reviews', 'icon' => '◷', 'route' => 'platform.payments.review.index', 'active' => 'platform.payments.review.*', 'access' => ['billing']],
        ['label' => 'Billing Invoices', 'icon' => '▤', 'route' => 'platform.billing.invoices.index', 'active' => 'platform.billing.invoices.*', 'access' => ['billing']],
        ['label' => 'Coupons', 'icon' => '◈', 'route' => 'platform.billing.coupons.index', 'active' => 'platform.billing.coupons.*', 'access' => ['billing']],
        ['label' => 'Billing Reports', 'icon' => '▥', 'route' => 'platform.billing.reports.index', 'active' => 'platform.billing.reports.*', 'access' => ['billing']],
        ['label' => 'Billing Settings', 'icon' => '⚙', 'route' => 'platform.billing.settings.index', 'active' => 'platform.billing.settings.*', 'access' => ['billing']],
        ['label' => 'Plans', 'icon' => '◈', 'route' => 'platform.plans.index', 'active' => 'platform.plans.*', 'access' => ['billing']],
        ['label' => 'Modules', 'icon' => '◇', 'route' => 'platform.modules.index', 'active' => 'platform.modules.*', 'access' => ['billing']],
        ['label' => 'Reports', 'icon' => '▥', 'route' => 'platform.reports.index', 'active' => 'platform.reports.*', 'access' => ['auditor','operations','billing']],
        ['label' => 'Support Inbox', 'icon' => '✉', 'route' => 'platform.support.index', 'active' => 'platform.support.*', 'access' => ['support','operations']],
        ['label' => 'Announcements', 'icon' => '◉', 'route' => 'platform.announcements.index', 'active' => 'platform.announcements.*', 'access' => ['support','operations']],
        ['label' => 'Audit Log', 'icon' => '▨', 'route' => 'platform.audit.index', 'active' => 'platform.audit.*', 'access' => ['auditor']],
        ['label' => 'Security Center', 'icon' => '⚿', 'route' => 'platform.security.index', 'active' => 'platform.security.*', 'access' => ['auditor']],
        ['label' => 'Granular Roles', 'icon' => '♧', 'route' => 'platform.security.roles.index', 'active' => 'platform.security.roles.*', 'access' => ['super_admin'], 'permanent' => true],
        ['label' => 'Administrators', 'icon' => '♙', 'route' => 'platform.admins.index', 'active' => 'platform.admins.*', 'access' => ['super_admin'], 'permanent' => true],
        ['label' => 'Email Templates', 'icon' => '✉', 'route' => 'platform.communications.templates.index', 'active' => 'platform.communications.templates.*', 'access' => ['super_admin'], 'permanent' => true],
        ['label' => 'Recipients', 'icon' => '♙', 'route' => 'platform.communications.rules.index', 'active' => 'platform.communications.rules.*', 'access' => ['super_admin'], 'permanent' => true],
        ['label' => 'Email Settings', 'icon' => '⚙', 'route' => 'platform.communications.settings.index', 'active' => 'platform.communications.settings.*', 'access' => ['super_admin'], 'permanent' => true],
        ['label' => 'Delivery Logs', 'icon' => '▨', 'route' => 'platform.communications.deliveries.index', 'active' => 'platform.communications.deliveries.*', 'access' => ['super_admin'], 'permanent' => true],
        ['label' => 'Notification Inbox', 'icon' => '◉', 'route' => 'platform.notifications.index', 'active' => 'platform.notifications.*', 'access' => ['operations','support','billing','auditor']],
        ['label' => 'Settings', 'icon' => '⚙', 'route' => 'platform.settings.index', 'active' => 'platform.settings.*', 'access' => ['super_admin'], 'permanent' => true],
    ] : [
        ['label' => 'Dashboard', 'icon' => '◫', 'route' => 'business.dashboard', 'active' => 'business.dashboard'],
        ['label' => 'POS', 'icon' => '▣', 'route' => 'business.pos.terminal', 'active' => 'business.pos.terminal', 'can' => 'pos.sales'],
        ['label' => 'Sales', 'icon' => '▤', 'route' => 'business.pos.sales', 'active' => 'business.pos.sales', 'can' => 'pos.sales'],
        ['label' => 'Products & Stock', 'icon' => '◇', 'route' => 'business.pos.products', 'active' => 'business.pos.products*', 'can' => 'pos.inventory.manage'],
        ['label' => 'Inventory', 'icon' => '▦', 'route' => 'business.pos.inventory', 'active' => 'business.pos.inventory*', 'can' => 'pos.inventory.manage'],
        ['label' => 'Registers & Shifts', 'icon' => '▣', 'route' => 'business.pos.registers', 'active' => 'business.pos.registers*', 'can' => 'pos.shifts.operate'],
        ['label' => 'Connected Devices', 'icon' => '▣', 'route' => 'business.pos.devices.index', 'active' => 'business.pos.devices.*', 'can' => 'pos.devices.manage'],
        ['label' => 'Returns', 'icon' => '↶', 'route' => 'business.pos.returns', 'active' => 'business.pos.returns*', 'can' => 'pos.returns.manage'],
        ['label' => 'Suppliers', 'icon' => '♧', 'route' => 'business.purchasing.suppliers', 'active' => 'business.purchasing.suppliers*', 'can' => 'pos.suppliers.manage'],
        ['label' => 'Purchase Orders', 'icon' => '▤', 'route' => 'business.purchasing.orders', 'active' => 'business.purchasing.orders*', 'canAny' => ['pos.purchases.manage','pos.receiving.manage','pos.payables.manage','pos.purchasing.reports']],
        ['label' => 'Stock Controls', 'icon' => '◷', 'route' => 'business.purchasing.stock', 'active' => 'business.purchasing.stock*', 'can' => 'pos.inventory.manage'],
        ['label' => 'Purchasing Reports', 'icon' => '▥', 'route' => 'business.purchasing.reports', 'active' => 'business.purchasing.reports*', 'can' => 'pos.purchasing.reports'],
        ['label' => 'Employees', 'icon' => '♙', 'route' => 'business.employees.index', 'active' => 'business.employees.*', 'can' => 'employees.view', 'feature' => 'staff.core'],
        ['label' => 'Roles & Permissions', 'icon' => '⚿', 'route' => 'business.roles.index', 'active' => 'business.roles.*', 'can' => 'employees.view', 'feature' => 'staff.core'],
        ['label' => 'Subscription', 'icon' => '▥', 'route' => 'business.subscription.index', 'active' => 'business.subscription.*'],
        ['label' => 'Support', 'icon' => '✉', 'route' => 'business.support.index', 'active' => 'business.support.*', 'can' => 'settings.manage'],
        ['label' => 'Notifications', 'icon' => '◉', 'route' => 'business.notifications.index', 'active' => 'business.notifications.*'],
        ['label' => 'Invoices & Billing', 'icon' => '▤', 'route' => 'business.billing.index', 'active' => 'business.billing.*', 'can' => 'settings.manage'],
        ['label' => 'Reports', 'icon' => '▨', 'route' => 'business.pos.reports', 'active' => 'business.pos.reports*', 'can' => 'pos.reports.view'],
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
                        $access = app(\App\Services\PlatformAccess::class);
                        $visible = ($role === 'super_admin' || in_array($role, $item['access'], true)
                            || ($access->isCustom(auth()->user()) && $access->canViewNavigation(auth()->user(), $item['route'])))
                            && (!isset($item['permanent']) || ($role === 'super_admin' && auth()->user()->isPermanentPlatformAdmin()));
                    }
                    if (!$platform && isset($item['canAny'])) {
                        $authorization = app(\App\Services\TenantAuthorization::class);
                        $visible = collect($item['canAny'])->contains(fn ($permission) => $authorization->allows(auth()->user(), $tenant, $permission));
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
                @if($platform && in_array(auth()->user()->platformRole(), ['super_admin','billing'], true))
                    @php $pendingPaymentReviews = \App\Models\SubscriptionPaymentRequest::where('status','pending')->count(); @endphp
                    <a href="{{ route('platform.payments.review.index') }}" class="topbar-review-link" aria-label="{{ $pendingPaymentReviews }} pending subscription payment reviews" title="Payment reviews">
                        <span aria-hidden="true">◷</span>
                        @if($pendingPaymentReviews > 0)<span class="pending-review-count">{{ $pendingPaymentReviews > 99 ? '99+' : $pendingPaymentReviews }}</span>@endif
                    </a>
                @endif
                @php
                    $unreadNotifications = \App\Models\UserNotification::where('recipient_user_id', auth()->id())
                        ->whereNull('read_at')->when(!$platform && isset($tenant), fn ($q) => $q->where('tenant_id',$tenant->id))->count();
                    $notificationHref = $platform ? route('platform.notifications.index') : route('business.notifications.index',$tenant);
                @endphp
                <a class="topbar-review-link" href="{{ $notificationHref }}" title="Notification inbox" aria-label="{{ $unreadNotifications }} unread notifications">♧
                    @if($unreadNotifications>0)<span class="pending-review-count">{{ $unreadNotifications>99?'99+':$unreadNotifications }}</span>@endif
                </a>
                <a class="topbar-review-link" href="{{ route('security.sessions') }}" title="My sessions & security" aria-label="My security sessions">⚿</a>
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
