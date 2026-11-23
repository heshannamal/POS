<?php

namespace App\Services;

use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Http\Request;

class PlatformAccess
{
    public const SCOPES = [
        'operations' => 'Business & store administration',
        'billing' => 'Subscription and billing administration',
        'support' => 'Support and communication',
        'auditor' => 'Read-only audit and reports',
    ];

    public const PERMISSIONS = [
        'dashboard.view' => 'View platform dashboard',
        'tenants.view' => 'View businesses',
        'tenants.manage' => 'Manage businesses',
        'stores.view' => 'View stores',
        'stores.manage' => 'Manage stores',
        'users.view' => 'View users',
        'users.manage' => 'Manage tenant users',
        'trials.view' => 'View trials',
        'trials.manage' => 'Manage trials',
        'billing.view' => 'View subscriptions, plans, modules and payments',
        'billing.manage' => 'Manage plans, modules and subscriptions',
        'reports.view' => 'View business reports',
        'audit.view' => 'View audit logs',
        'audit.export' => 'Export audit CSV',
        'support.view' => 'View tickets and announcements',
        'support.manage' => 'Respond to tickets and manage announcements',
        'security.view' => 'View security dashboards',
    ];

    public function isCustom(User $user): bool
    {
        return str_starts_with((string) $user->platformRole(), 'custom_');
    }

    public function role(User $user): ?PlatformRole
    {
        if (! $this->isCustom($user)) {
            return null;
        }
        $id = substr((string) $user->platformRole(), strlen('custom_'));
        if (! ctype_digit($id)) {
            return null;
        }
        return PlatformRole::whereKey((int) $id)->where('is_active', true)->first();
    }

    public function allowsScope(User $user, array $scopes, Request $request): bool
    {
        $custom = $this->role($user);
        if (! $custom || ! array_intersect($custom->scopes ?? [], $scopes)) {
            return false;
        }
        $permission = $this->routePermission($request);
        return $permission !== null && in_array($permission, $custom->permissions ?? [], true);
    }

    public function allowsDashboard(User $user, Request $request): bool
    {
        $role = $this->role($user);
        return $role !== null && in_array('dashboard.view', $role->permissions ?? [], true);
    }

    public function routePermission(Request $request): ?string
    {
        $name = (string) $request->route()?->getName();
        $view = in_array($request->method(), ['GET', 'HEAD'], true)
            && ! str_ends_with($name, '.create')
            && ! str_ends_with($name, '.edit');

        if ($name === 'platform.dashboard') return 'dashboard.view';
        if (str_starts_with($name, 'platform.tenants.')) return $view ? 'tenants.view' : 'tenants.manage';
        if (str_starts_with($name, 'platform.stores.')) return $view ? 'stores.view' : 'stores.manage';
        if (str_starts_with($name, 'platform.users.') || str_starts_with($name, 'platform.tenant-users.')) return $view ? 'users.view' : 'users.manage';
        if (str_starts_with($name, 'platform.trials.')) return $view ? 'trials.view' : 'trials.manage';
        if (str_starts_with($name, 'platform.plans.') || str_starts_with($name, 'platform.modules.')
            || str_starts_with($name, 'platform.subscriptions.')
            || str_starts_with($name, 'platform.payments.')) {
            return $view ? 'billing.view' : 'billing.manage';
        }
        if (str_starts_with($name, 'platform.reports.')) return 'reports.view';
        if ($name === 'platform.audit.export') return 'audit.export';
        if (str_starts_with($name, 'platform.audit.')) return 'audit.view';
        if (str_starts_with($name, 'platform.support.') || str_starts_with($name, 'platform.announcements.')) {
            return $view ? 'support.view' : 'support.manage';
        }
        if (str_starts_with($name, 'platform.security.')) return 'security.view';

        // Admin roles, payment-settings mutations and permanent-only financial
        // approvals cannot be assigned to custom roles via this mechanism.
        return null;
    }

    public function canViewNavigation(User $user, string $routeName): bool
    {
        if (! $this->isCustom($user)) {
            return true;
        }
        $role = $this->role($user);
        if (! $role) return false;
        $prefix = explode('.', $routeName);
        $section = $prefix[1] ?? '';
        $permission = match ($section) {
            'dashboard' => 'dashboard.view',
            'tenants' => 'tenants.view',
            'stores' => 'stores.view',
            'users', 'tenant-users' => 'users.view',
            'trials' => 'trials.view',
            'plans', 'modules', 'payments', 'subscriptions' => 'billing.view',
            'reports' => 'reports.view',
            'audit' => 'audit.view',
            'support', 'announcements' => 'support.view',
            'security' => 'security.view',
            default => null,
        };
        return $permission !== null && in_array($permission, $role->permissions ?? [], true);
    }
}
