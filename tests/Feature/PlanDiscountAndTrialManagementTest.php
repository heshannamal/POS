<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use App\Services\FeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlanDiscountAndTrialManagementTest extends TestCase
{
    use RefreshDatabase;

    private function operations(): User
    {
        return User::factory()->create([
            'is_platform_admin' => true, 'platform_role' => 'operations',
        ]);
    }

    private function billing(): User
    {
        return User::factory()->create([
            'is_platform_admin' => true, 'platform_role' => 'billing',
        ]);
    }

    public function test_plan_discount_is_optional_validated_and_applies_to_both_billing_cycles(): void
    {
        $admin = $this->billing();
        $plan = Plan::where('slug', 'business')->firstOrFail();

        $this->actingAs($admin)->put(route('platform.plans.update', $plan), [
            'name' => $plan->name, 'slug' => $plan->slug,
            'currency' => 'LKR', 'monthly_price' => '2500.50',
            'yearly_price' => '30000.00', 'is_active' => '1', 'is_public' => '1',
            'is_discount_available' => '1', 'discount_percentage' => '20.00',
        ])->assertRedirect();

        $plan->refresh();
        $this->assertTrue($plan->is_discount_available);
        $this->assertSame('20.00', $plan->discount_percentage);
        $this->assertSame(250050, $plan->monthly_price_minor);
        $this->assertSame(200040, $plan->priceForCycleMinor('monthly'));
        $this->assertSame(2400000, $plan->priceForCycleMinor('yearly'));

        $this->actingAs($admin)->put(route('platform.plans.update', $plan), [
            'name' => $plan->name, 'slug' => $plan->slug,
            'currency' => 'LKR', 'monthly_price' => '2500.50',
            'yearly_price' => '30000.00', 'is_active' => '1', 'is_public' => '1',
            'is_discount_available' => '1', 'discount_percentage' => '100',
        ])->assertSessionHasErrors('discount_percentage');

        $this->actingAs($admin)->put(route('platform.plans.update', $plan), [
            'name' => $plan->name, 'slug' => $plan->slug,
            'currency' => 'LKR', 'monthly_price' => '2500.50',
            'yearly_price' => '30000.00', 'is_active' => '1', 'is_public' => '1',
            'is_discount_available' => '0', 'discount_percentage' => '20.00',
        ])->assertRedirect();

        $plan->refresh();
        $this->assertFalse($plan->is_discount_available);
        $this->assertNull($plan->discount_percentage);
        $this->assertSame(250050, $plan->priceForCycleMinor('monthly'));
    }

    public function test_discount_is_snapshotted_in_customer_payment_request(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Discounted Retailer']);
        $plan = Plan::where('slug', 'business')->firstOrFail();
        $plan->update([
            'monthly_price_minor' => 200000, 'yearly_price_minor' => 2400000,
            'is_discount_available' => true, 'discount_percentage' => '12.50',
            'is_public' => true, 'is_active' => true,
        ]);
        PaymentMethod::where('code', 'asset')->update(['is_enabled' => true]);

        $this->actingAs($owner)->post(route('business.subscription.payments.store', $tenant), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'customer_reference' => 'DEAL-001',
            'evidence' => UploadedFile::fake()->image('receipt.png'),
            'requested_amount_minor' => 1,
        ])->assertRedirect(route('business.subscription.index', $tenant));

        $payment = $tenant->paymentRequests()->firstOrFail();
        $this->assertSame(175000, $payment->requested_amount_minor);
        $this->assertSame('trialing', $tenant->fresh()->subscription->status);

        $plan->update([
            'discount_percentage' => '30.00', 'monthly_price_minor' => 400000,
        ]);
        $this->assertSame(175000, $payment->fresh()->requested_amount_minor);
    }

    public function test_trial_owner_must_be_verified_and_creating_trial_assigns_duration(): void
    {
        $admin = $this->operations();
        $owner = User::factory()->create(['email' => 'trial-owner@example.com']);

        $this->actingAs($admin)->get(route('platform.trials.create'))->assertOk();
        $this->actingAs($admin)->post(route('platform.trials.store'), [
            'name' => 'Trial Retail', 'owner_email' => $owner->email, 'trial_days' => 21,
        ])->assertRedirect(route('platform.trials.index', ['state' => 'running']));

        $tenant = Tenant::where('name', 'Trial Retail')->firstOrFail();
        $this->assertTrue($owner->isOwnerOf($tenant));
        $this->assertSame('trial', $tenant->status);
        $this->assertSame(21, $tenant->trial->duration_days);
        $this->assertTrue($tenant->trial->is_active);
        $this->assertSame(21, $tenant->trial->days_remaining);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id, 'action' => 'tenant.trial.admin_created',
        ]);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($admin)->post(route('platform.trials.store'), [
            'name' => 'Not Verified', 'owner_email' => $unverified->email,
            'trial_days' => 7,
        ])->assertSessionHasErrors('owner_email');
        $this->assertDatabaseMissing('tenants', ['name' => 'Not Verified']);
    }

    public function test_deactivate_reactivate_and_extend_trial_keep_data_and_history(): void
    {
        $admin = $this->operations();
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Trial With Data']);
        $originalEnd = $tenant->trial->ends_at->copy();

        $this->assertTrue(app(FeatureAccess::class)->allows($tenant, 'staff.core'));

        $this->actingAs($admin)->patch(route('platform.trials.active', $tenant), [
            'is_active' => '0', 'reason' => 'Customer requested a temporary hold.',
        ])->assertRedirect();

        $tenant = $tenant->fresh(['trial', 'subscription']);
        $this->assertFalse($tenant->trial->is_active);
        $this->assertSame('inactive', $tenant->effective_status);
        $this->assertFalse(app(FeatureAccess::class)->allows($tenant, 'staff.core'));
        $this->assertTrue($owner->isOwnerOf($tenant));
        $this->assertSame($originalEnd->toDateTimeString(), $tenant->trial->ends_at->toDateTimeString());
        $this->assertSame('trialing', $tenant->subscription->status);

        $this->actingAs($admin)->get(route('platform.trials.index', ['state' => 'inactive']))
            ->assertOk()->assertSee('Trial With Data');

        $this->actingAs($admin)->post(route('platform.tenants.trial.extend', $tenant), [
            'days' => 7, 'notes' => 'Granted additional evaluation time',
        ])->assertRedirect();

        $tenant = $tenant->fresh(['trial']);
        $this->assertSame(7, $tenant->trial->extension_days);
        $this->assertTrue($tenant->trial->ends_at->greaterThan($originalEnd));

        $this->actingAs($admin)->patch(route('platform.trials.active', $tenant), [
            'is_active' => '1', 'reason' => 'Reopened on customer request.',
        ])->assertRedirect();

        $tenant = $tenant->fresh(['trial']);
        $this->assertTrue($tenant->trial->is_active);
        $this->assertSame('trial', $tenant->effective_status);
        $this->assertTrue(app(FeatureAccess::class)->allows($tenant, 'staff.core'));
        $this->assertDatabaseHas('trial_events', ['trial_id' => $tenant->trial->id, 'action' => 'deactivated']);
        $this->assertDatabaseHas('trial_events', ['trial_id' => $tenant->trial->id, 'action' => 'reactivated']);
        $this->assertDatabaseHas('trial_events', ['trial_id' => $tenant->trial->id, 'action' => 'extended']);
    }

    public function test_converted_business_or_billing_admin_cannot_toggle_trial(): void
    {
        $operations = $this->operations();
        $billing = $this->billing();
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Converted Account']);

        $this->actingAs($billing)->patch(route('platform.trials.active', $tenant), [
            'is_active' => '0', 'reason' => 'Billing role should be denied.',
        ])->assertForbidden();

        $tenant->trial()->update(['converted_at' => now()]);
        $this->actingAs($operations)->patch(route('platform.trials.active', $tenant), [
            'is_active' => '0', 'reason' => 'Converted account cannot be toggled.',
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($tenant->fresh()->trial->is_active);
    }

    public function test_expired_trial_can_be_extended_from_the_admin(): void
    {
        $admin = $this->operations();
        $tenant = app(CreateTenant::class)->handle(
            User::factory()->create(), ['name' => 'Already Expired']
        );
        $tenant->trial()->update(['ends_at' => now()->subDays(4)]);
        $this->assertSame('expired', $tenant->fresh()->effective_status);
        $this->actingAs($admin)->post(route('platform.tenants.trial.extend', $tenant), [
            'days' => 7, 'notes' => 'Restore the expired evaluation',
        ])->assertRedirect();
        $this->assertSame('trial', $tenant->fresh()->effective_status);
        $this->assertTrue($tenant->fresh()->trial->ends_at->greaterThan(now()->addDays(6)));
    }
}
