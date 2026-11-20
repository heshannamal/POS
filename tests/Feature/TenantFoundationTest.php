<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use App\Services\TenantAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_but_must_verify_email_before_onboarding(): void
    {
        $this->post(route('register'), [
            'name' => 'First Owner',
            'email' => 'owner@example.com',
            'password' => 'ExamplePassword1234',
            'password_confirmation' => 'ExamplePassword1234',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com']);
        $this->get(route('onboarding.create'))->assertRedirect(route('verification.notice'));
    }

    public function test_onboarding_creates_isolated_business_with_real_trial_and_owner(): void
    {
        $owner = User::factory()->create();
        $response = $this->actingAs($owner)->post(route('onboarding.store'), [
            'name' => 'Acme Retail',
            'contact_email' => 'hello@acme.test',
            'phone' => '0770000000',
            'address' => 'Main Street',
        ]);

        $tenant = Tenant::firstOrFail();
        $response->assertRedirect(route('business.dashboard', $tenant));
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'is_owner' => true,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('branches', [
            'tenant_id' => $tenant->id,
            'code' => 'MAIN',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('trials', [
            'tenant_id' => $tenant->id,
            'duration_days' => config('saas.default_trial_days'),
        ]);
        $this->assertTrue(app(TenantAuthorization::class)->allows($owner, $tenant, 'inventory.manage'));
        $this->actingAs($owner)->get(route('business.dashboard', $tenant))->assertOk();
    }

    public function test_owners_cannot_access_other_tenants_or_platform_admin(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $creator = app(CreateTenant::class);
        $first = $creator->handle($alice, ['name' => 'Alice Retail']);
        $second = $creator->handle($bob, ['name' => 'Bob Retail']);

        $this->actingAs($alice)->get(route('business.dashboard', $first))->assertOk();
        $this->actingAs($alice)->get(route('business.dashboard', $second))->assertForbidden();
        $this->actingAs($alice)->get(route('platform.dashboard'))->assertForbidden();
        $this->assertFalse(app(TenantAuthorization::class)->allows($alice, $second, 'inventory.view'));
    }

    public function test_super_admin_can_manage_tenants_and_extend_trial(): void
    {
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Sunrise Shop']);
        $originalEnd = $tenant->trial->ends_at->copy();

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->get(route('platform.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('platform.tenants.show', $tenant))->assertOk();

        $this->actingAs($admin)->post(route('platform.tenants.trial.extend', $tenant), [
            'days' => 7,
            'notes' => 'Customer needs more time',
        ])->assertRedirect();

        $this->assertEquals(
            $originalEnd->addDays(7)->getTimestamp(),
            $tenant->trial()->firstOrFail()->ends_at->getTimestamp()
        );
        $this->assertDatabaseHas('trial_events', [
            'trial_id' => $tenant->trial->id, 'action' => 'extended',
        ]);
    }

    public function test_admin_can_create_tenant_for_existing_verified_owner(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create(['email' => 'ready@example.com']);

        $this->actingAs($admin)->post(route('platform.tenants.store'), [
            'name' => 'Ready Store',
            'owner_email' => $owner->email,
        ])->assertRedirect();

        $tenant = Tenant::where('name', 'Ready Store')->firstOrFail();
        $this->assertTrue($owner->isOwnerOf($tenant));
    }

    public function test_expired_trial_does_not_remove_business_data(): void
    {
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Test Store']);
        $tenant->trial()->update(['ends_at' => now()->subDay()]);

        $this->assertSame('expired', $tenant->fresh('trial')->effective_status);
        $this->actingAs($owner)->get(route('business.dashboard', $tenant))->assertOk();
        $this->assertDatabaseHas('branches', ['tenant_id' => $tenant->id]);
    }
}
