<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use App\Services\FeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommercialAndEmployeesTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(?User $owner = null, string $name = 'Happy Store'): Tenant
    {
        return app(CreateTenant::class)->handle($owner ?? User::factory()->create(), ['name' => $name]);
    }

    public function test_new_trial_has_starter_plan_and_released_staff_entitlement(): void
    {
        $tenant = $this->tenant();
        $this->assertSame('starter', $tenant->subscription->plan->slug);
        $this->assertSame('trialing', $tenant->subscription->status);
        $this->assertTrue(app(FeatureAccess::class)->allows($tenant, 'staff.core'));
        $this->assertFalse(app(FeatureAccess::class)->allows($tenant, 'pos.core'));
        $tenant->trial()->update(['ends_at' => now()->subDay()]);
        $this->assertFalse(app(FeatureAccess::class)->allows($tenant->fresh(), 'staff.core'));
        $this->assertDatabaseHas('branches', ['tenant_id' => $tenant->id, 'code' => 'MAIN']);
    }

    public function test_plan_editor_is_platform_only_and_updates_included_modules(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $plan = Plan::where('slug', 'starter')->firstOrFail();
        $staff = Module::where('code', 'staff.core')->firstOrFail();

        $this->actingAs($owner)->get(route('platform.plans.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('platform.plans.index'))->assertOk();
        $this->actingAs($admin)->put(route('platform.plans.update', $plan), [
            'name' => 'Starter Plus', 'slug' => 'starter', 'description' => 'Starter module pack',
            'monthly_price' => '2500.50', 'currency' => 'LKR',
            'is_active' => '1', 'is_public' => '0', 'modules' => [$staff->id],
        ])->assertRedirect();

        $this->assertSame(250050, $plan->fresh()->monthly_price_minor);
        $this->assertSame([$staff->id], $plan->fresh()->modules->pluck('id')->all());
    }

    public function test_admin_can_activate_plan_manually_without_creating_payments(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenant($owner);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $plan = Plan::where('slug', 'business')->firstOrFail();

        $this->actingAs($admin)->post(route('platform.tenants.plan.activate', $tenant), [
            'plan_id' => $plan->id,
        ])->assertRedirect();

        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertSame('manual', $tenant->fresh()->subscription->source);
        $this->assertSame($plan->id, $tenant->fresh()->subscription->plan_id);
        $this->assertNotNull($tenant->fresh()->trial->converted_at);
        $this->assertTrue($owner->isOwnerOf($tenant));
        $this->assertDatabaseHas('branches', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'subscription.manually_activated']);
    }

    public function test_manual_module_grants_are_tenant_scoped_and_require_released_module(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $first = $this->tenant(User::factory()->create(), 'First Tenant');
        $second = $this->tenant(User::factory()->create(), 'Second Tenant');
        $staff = Module::where('code', 'staff.core')->firstOrFail();
        $unreleased = Module::where('code', 'ecommerce.store')->firstOrFail();

        $this->actingAs($admin)->put(route('platform.tenants.modules.grant', $first), [
            'module_id' => $staff->id, 'enabled' => '0',
        ])->assertRedirect();
        $this->assertFalse(app(FeatureAccess::class)->allows($first->fresh(), 'staff.core'));
        $this->assertTrue(app(FeatureAccess::class)->allows($second->fresh(), 'staff.core'));

        $this->actingAs($admin)->put(route('platform.tenants.modules.grant', $first), [
            'module_id' => $unreleased->id, 'enabled' => '1',
        ])->assertSessionHasErrors('module_id');

        $this->actingAs($admin)->delete(route('platform.tenants.modules.revoke', [$first, $staff]))
            ->assertRedirect();
        $this->assertTrue(app(FeatureAccess::class)->allows($first->fresh(), 'staff.core'));
    }

    public function test_admin_trial_setting_only_changes_future_businesses(): void
    {
        $older = $this->tenant(User::factory()->create(), 'Older Retail');
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->put(route('platform.trials.settings'), [
            'default_days' => 21,
        ])->assertRedirect();
        $new = $this->tenant(User::factory()->create(), 'New Retail');

        $this->assertSame(14, $older->trial->duration_days);
        $this->assertSame(21, $new->trial->duration_days);
        $this->actingAs($admin)->get(route('platform.trials.index'))->assertOk();
    }

    public function test_owner_can_create_roles_but_other_tenant_and_cashier_cannot_edit(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenant($owner);
        $otherOwner = User::factory()->create();
        $otherTenant = $this->tenant($otherOwner, 'Other Store');
        $staffPermission = Permission::where('key', 'employees.view')->firstOrFail();

        $this->actingAs($owner)->post(route('business.roles.store', $tenant), [
            'name' => 'Supervisor', 'permissions' => [$staffPermission->id],
        ])->assertRedirect();
        $role = Role::where('tenant_id', $tenant->id)->where('slug', 'supervisor')->firstOrFail();
        $this->assertTrue($role->permissions->contains('id', $staffPermission->id));

        $this->actingAs($otherOwner)->put(route('business.roles.update', [$tenant, $role]), [
            'name' => 'Hijacked',
        ])->assertForbidden();

        $this->actingAs($owner)->put(route('business.roles.update', [$otherTenant, $role]), [
            'name' => 'Wrong Tenant',
        ])->assertForbidden();
    }

    public function test_staff_invites_require_matching_verified_identity_and_cannot_be_reused(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenant($owner);
        $employee = User::factory()->create(['email' => 'worker@example.com']);
        $role = Role::where('tenant_id', $tenant->id)->where('slug', 'cashier')->firstOrFail();
        $token = str_repeat('a', 64);

        StaffInvitation::create([
            'tenant_id' => $tenant->id, 'role_id' => $role->id,
            'invited_by' => $owner->id, 'email' => $employee->email,
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7),
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post(route('staff.invites.accept', $token))->assertForbidden();
        $this->actingAs($employee)->post(route('staff.invites.accept', $token))->assertRedirect();
        $this->assertTrue($tenant->users()->whereKey($employee->id)->exists());
        $this->assertDatabaseHas('role_user', [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $employee->id,
        ]);
        $this->actingAs($employee)->post(route('staff.invites.accept', $token))->assertStatus(410);
    }

    public function test_non_owner_staff_cannot_escalate_permissions_or_manage_other_tenants(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenant($owner);
        $cashier = User::factory()->create();
        $tenant->users()->attach($cashier->id, ['status' => 'active', 'is_owner' => false]);
        $role = Role::where('tenant_id', $tenant->id)->where('slug', 'cashier')->firstOrFail();
        DB::table('role_user')->insert([
            'role_id' => $role->id, 'tenant_id' => $tenant->id, 'user_id' => $cashier->id,
        ]);
        $this->actingAs($cashier)->get(route('business.employees.index', $tenant))->assertForbidden();
        $this->actingAs($cashier)->post(route('business.roles.store', $tenant), [
            'name' => 'Administrator', 'permissions' => Permission::pluck('id')->all(),
        ])->assertForbidden();
        $this->actingAs($cashier)->get(route('business.subscription.index', $tenant))->assertOk();
    }

    public function test_owner_can_view_staff_pages_invite_verified_users_and_change_access(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenant($owner);
        $cashier = Role::where('tenant_id', $tenant->id)->where('slug', 'cashier')->firstOrFail();
        $employee = User::factory()->create(['email' => 'invited@example.com']);
        $anotherTenant = $this->tenant(User::factory()->create(), 'Unrelated Tenant');
        $otherRole = Role::where('tenant_id', $anotherTenant->id)->where('slug', 'manager')->firstOrFail();

        $this->actingAs($owner)->get(route('business.employees.index', $tenant))->assertOk();
        $this->actingAs($owner)->get(route('business.roles.index', $tenant))->assertOk();

        $this->actingAs($owner)->post(route('business.employees.invite', $tenant), [
            'email' => 'invited@example.com', 'role_id' => $otherRole->id,
        ])->assertSessionHasErrors('role_id');

        $this->actingAs($owner)->post(route('business.employees.invite', $tenant), [
            'email' => 'invited@example.com', 'role_id' => $cashier->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('staff_invitations', [
            'tenant_id' => $tenant->id, 'email' => 'invited@example.com', 'role_id' => $cashier->id,
        ]);
        $this->assertFalse($tenant->users()->whereKey($employee->id)->exists());
    }
}
