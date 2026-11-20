<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Module;
use App\Models\Plan;
use App\Models\PlatformAnnouncement;
use App\Models\PlatformSetting;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin', bool $temporary = false): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'platform_role' => $role,
            'temporary_admin_expires_at' => $temporary ? now()->addHours(24) : null,
        ]);
    }

    private function tenant(string $name = 'Test Business'): Tenant
    {
        return app(CreateTenant::class)->handle(User::factory()->create(), ['name' => $name]);
    }

    public function test_super_admin_can_open_all_admin_pages(): void
    {
        $tenant = $this->tenant();
        $admin = $this->admin();

        foreach ([
            route('platform.dashboard'),
            route('platform.tenants.index'),
            route('platform.tenants.show', $tenant),
            route('platform.trials.index'),
            route('platform.subscriptions.index'),
            route('platform.plans.index'),
            route('platform.modules.index'),
            route('platform.reports.index'),
            route('platform.audit.index'),
            route('platform.support.index'),
            route('platform.announcements.index'),
            route('platform.admins.index'),
            route('platform.settings.index'),
            route('platform.modules.create'),
            route('platform.plans.create'),
            route('platform.announcements.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_scoped_admin_roles_cannot_cross_feature_boundaries(): void
    {
        $tenant = $this->tenant();
        $operations = $this->admin('operations');
        $billing = $this->admin('billing');
        $support = $this->admin('support');
        $auditor = $this->admin('auditor');

        $this->actingAs($operations)->get(route('platform.tenants.index'))->assertOk();
        $this->actingAs($operations)->get(route('platform.trials.index'))->assertOk();
        $this->actingAs($operations)->get(route('platform.modules.index'))->assertForbidden();
        $this->actingAs($operations)->get(route('platform.audit.index'))->assertForbidden();
        $this->actingAs($operations)->get(route('platform.admins.index'))->assertForbidden();

        $this->actingAs($billing)->get(route('platform.plans.index'))->assertOk();
        $this->actingAs($billing)->get(route('platform.subscriptions.index'))->assertOk();
        $this->actingAs($billing)->get(route('platform.tenants.show', $tenant))->assertOk();
        $this->actingAs($billing)->get(route('platform.trials.index'))->assertForbidden();

        $this->actingAs($support)->get(route('platform.support.index'))->assertOk();
        $this->actingAs($support)->get(route('platform.announcements.index'))->assertOk();
        $this->actingAs($support)->get(route('platform.tenants.show', $tenant))->assertOk();
        $this->actingAs($support)->get(route('platform.plans.index'))->assertForbidden();

        $this->actingAs($auditor)->get(route('platform.audit.index'))->assertOk();
        $this->actingAs($auditor)->get(route('platform.reports.index'))->assertOk();
        $this->actingAs($auditor)->get(route('platform.tenants.index'))->assertForbidden();
        $this->actingAs($auditor)->get(route('platform.support.index'))->assertForbidden();
    }

    public function test_temporary_admin_cannot_promote_or_manage_permanent_admins(): void
    {
        $temp = $this->admin('super_admin', true);
        $this->actingAs($temp)->get(route('platform.dashboard'))->assertOk();
        $this->actingAs($temp)->get(route('platform.admins.index'))->assertForbidden();
        $candidate = User::factory()->create();
        $this->actingAs($temp)->post(route('platform.admins.store'), [
            'email' => $candidate->email, 'role' => 'super_admin', 'admin_password' => 'password',
        ])->assertForbidden();
        $this->assertFalse($candidate->fresh()->is_platform_admin);
    }

    public function test_permanent_admin_can_promote_scope_change_and_revoke_existing_verified_users(): void
    {
        $admin = $this->admin();
        $candidate = User::factory()->create(['email' => 'operator@example.test']);

        $this->actingAs($admin)->post(route('platform.admins.store'), [
            'email' => $candidate->email,
            'role' => 'operations', 'admin_password' => 'incorrect',
        ])->assertSessionHasErrors('admin_password');

        $this->actingAs($admin)->post(route('platform.admins.store'), [
            'email' => $candidate->email, 'role' => 'operations', 'admin_password' => 'password',
        ])->assertRedirect();

        $this->assertSame('operations', $candidate->fresh()->platformRole());
        $this->actingAs($candidate->fresh())->get(route('platform.plans.index'))->assertForbidden();

        $this->actingAs($admin)->patch(route('platform.admins.update', $candidate), [
            'role' => 'support', 'enabled' => '1', 'admin_password' => 'password',
        ])->assertRedirect();
        $this->assertSame('support', $candidate->fresh()->platformRole());

        $this->actingAs($admin)->patch(route('platform.admins.update', $admin), [
            'role' => 'operations', 'enabled' => '1', 'admin_password' => 'password',
        ])->assertForbidden();
        $this->assertTrue($admin->fresh()->is_platform_admin);

        $this->actingAs($admin)->patch(route('platform.admins.update', $candidate), [
            'role' => 'support', 'enabled' => '0', 'admin_password' => 'password',
        ])->assertRedirect();
        $this->assertFalse($candidate->fresh()->is_platform_admin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.admin.changed', 'subject_id' => $candidate->id]);
    }

    public function test_business_profile_branches_limits_and_default_branch_are_tenant_scoped(): void
    {
        $admin = $this->admin('operations');
        $tenant = $this->tenant('Sunrise Retail');
        $other = $this->tenant('Other Retail');
        $tenant->subscription->plan->update(['max_branches' => 2]);

        $this->actingAs($admin)->put(route('platform.tenants.profile', $tenant), [
            'name' => 'Sunrise Retail Plus', 'contact_email' => 'hello@sunrise.test',
            'phone' => '0771234567', 'address' => 'Colombo',
            'currency' => 'LKR', 'timezone' => 'Asia/Colombo',
        ])->assertRedirect();
        $this->assertSame('Sunrise Retail Plus', $tenant->fresh()->name);

        $this->actingAs($admin)->post(route('platform.tenants.branches.store', $tenant), [
            'name' => 'East Store', 'code' => 'EAST', 'address' => 'East Road',
        ])->assertRedirect();

        $newBranch = $tenant->branches()->where('code', 'EAST')->firstOrFail();
        $main = $tenant->branches()->where('is_default', true)->firstOrFail();

        $this->actingAs($admin)->post(route('platform.tenants.branches.store', $tenant), [
            'name' => 'Third Store', 'code' => 'THIRD',
        ])->assertStatus(422);
        $this->assertDatabaseMissing('branches', ['tenant_id' => $tenant->id, 'code' => 'THIRD']);

        $otherBranch = $other->branches()->firstOrFail();
        $this->actingAs($admin)->patch(route('platform.tenants.branches.default', [$tenant, $otherBranch]))
            ->assertNotFound();

        $this->actingAs($admin)->patch(route('platform.tenants.branches.update', [$tenant, $main]), [
            'name' => 'Main Branch', 'is_active' => '0',
        ])->assertStatus(422);

        $this->actingAs($admin)->patch(route('platform.tenants.branches.default', [$tenant, $newBranch]))
            ->assertRedirect();
        $this->assertTrue($newBranch->fresh()->is_default);

        $this->actingAs($admin)->patch(route('platform.tenants.branches.update', [$tenant, $main]), [
            'name' => 'Original branch', 'is_active' => '0',
        ])->assertRedirect();

        $this->assertFalse($main->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'tenant.branch.default_changed']);
    }

    public function test_module_catalog_edits_do_not_publish_unimplemented_features(): void
    {
        $admin = $this->admin('billing');

        $this->actingAs($admin)->post(route('platform.modules.store'), [
            'code' => 'integrations.accounting',
            'name' => 'Accounting API', 'category' => 'Integrations',
            'description' => 'Planned accounting connector', 'monthly_price' => '1200.50',
            'is_available' => '1',
        ])->assertRedirect();

        $module = Module::where('code', 'integrations.accounting')->firstOrFail();
        $this->assertFalse($module->is_available);
        $this->assertSame(120050, $module->monthly_price_minor);
        $this->actingAs($admin)->get(route('platform.modules.edit', $module))->assertOk();

        $this->actingAs($admin)->put(route('platform.modules.update', $module), [
            'code' => 'core.staff',
            'name' => 'Accounting V2', 'category' => 'Integrations',
            'description' => 'Updated', 'monthly_price' => '100.00',
            'is_available' => '1',
        ])->assertRedirect();

        $this->assertFalse($module->fresh()->is_available);
        $this->assertSame('integrations.accounting', $module->fresh()->code);
        $this->assertSame(10000, $module->fresh()->monthly_price_minor);
    }

    public function test_platform_settings_change_future_onboarding_and_can_close_registration(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('platform.settings.update'), [
            'trial_days' => 21, 'registration_enabled' => '0',
            'default_currency' => 'USD', 'default_timezone' => 'Asia/Colombo',
        ])->assertRedirect();

        $this->assertFalse(PlatformSetting::registrationEnabled());
        $this->assertSame(21, PlatformSetting::trialDays());
        $this->assertSame('USD', PlatformSetting::defaultCurrency());

        $this->post(route('logout'))->assertRedirect();
        $this->get(route('register'))->assertForbidden();
        $this->post(route('register'), [
            'name' => 'Blocked', 'email' => 'blocked@example.com',
            'password' => 'Password1234567', 'password_confirmation' => 'Password1234567',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);

        $tenant = $this->tenant('New Business');
        $this->assertSame('USD', $tenant->currency);
        $this->assertSame(21, $tenant->trial->duration_days);
    }
}
