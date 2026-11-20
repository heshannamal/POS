<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\SubscriptionPaymentRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubscriptionPaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Subscription Review Store']);
        $plan = Plan::where('slug', 'business')->firstOrFail();
        $plan->update([
            'is_public' => true,
            'is_active' => true,
            'currency' => 'LKR',
            'monthly_price_minor' => 350000,
            'yearly_price_minor' => 3500000,
        ]);
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'platform_role' => 'super_admin',
        ]);
        return compact('owner', 'tenant', 'plan', 'admin');
    }

    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'receipt.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO/XV8kAAAAASUVORK5CYII=')
        );
    }

    private function enableEvidence(User $admin): void
    {
        $method = PaymentMethod::where('code', 'asset')->firstOrFail();
        $this->actingAs($admin)->put(route('platform.payments.methods.update', $method), [
            'is_enabled' => '1', 'instructions' => 'Pay to verified bank account, then upload your proof.',
        ])->assertRedirect();
        $this->assertTrue($method->fresh()->is_operational);
    }

    private function submit(User $owner, Tenant $tenant, Plan $plan, string $cycle = 'monthly'): void
    {
        $this->actingAs($owner)->post(route('business.subscription.payments.store', $tenant), [
            'plan_id' => $plan->id, 'billing_cycle' => $cycle,
            'customer_reference' => 'CUSTOMER001', 'evidence' => $this->proof(),
            'method' => 'card', 'requested_amount_minor' => '1', // ignored by server
        ])->assertRedirect(route('business.subscription.index', $tenant));
    }

    public function test_payment_methods_start_disabled_and_only_permanent_super_can_change_them(): void
    {
        $f = $this->fixture();
        $method = PaymentMethod::where('code', 'asset')->firstOrFail();
        $card = PaymentMethod::where('code', 'card')->firstOrFail();
        $this->assertFalse($method->is_enabled);
        $this->assertFalse($card->is_operational);

        $this->actingAs($f['owner'])->put(route('platform.payments.methods.update', $method), [
            'is_enabled' => '1',
        ])->assertForbidden();

        $billing = User::factory()->create(['is_platform_admin' => true, 'platform_role' => 'billing']);
        $this->actingAs($billing)->put(route('platform.payments.methods.update', $method), [
            'is_enabled' => '1',
        ])->assertForbidden();

        $this->actingAs($f['admin'])->put(route('platform.payments.methods.update', $card), [
            'is_enabled' => '1', 'instructions' => 'A processor is not connected.',
        ])->assertRedirect();

        $this->assertTrue($card->fresh()->is_enabled);
        $this->assertFalse($card->fresh()->is_operational);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.method.updated']);
    }

    public function test_proof_submission_requires_method_activation_and_protects_subscription_until_verified(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['owner'])->post(route('business.subscription.payments.store', $f['tenant']), [
            'plan_id' => $f['plan']->id, 'billing_cycle' => 'monthly',
            'evidence' => $this->proof(),
        ])->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('subscription_payment_requests', 0);

        $this->enableEvidence($f['admin']);
        $this->submit($f['owner'], $f['tenant'], $f['plan']);
        $payment = SubscriptionPaymentRequest::firstOrFail();

        $this->assertSame('pending', $payment->status);
        $this->assertSame('asset', $payment->method);
        $this->assertSame(350000, $payment->requested_amount_minor);
        $this->assertSame($f['tenant']->subscription->plan_id, $payment->previous_plan_id);
        $this->assertSame('trialing', $f['tenant']->fresh()->subscription->status);
        Storage::disk('local')->assertExists($payment->proof_path);

        $this->actingAs($f['owner'])->get(route('business.subscription.payments.receipt', [
            $f['tenant'], $payment,
        ]))->assertOk();

        $this->actingAs($f['admin'])->get(route('platform.payments.review.show', $payment))
            ->assertOk()->assertSee('3500.00');
    }

    public function test_admin_entered_verified_amount_controls_approval_and_prevents_duplicate_processing(): void
    {
        $f = $this->fixture();
        $this->enableEvidence($f['admin']);
        $this->submit($f['owner'], $f['tenant'], $f['plan']);
        $payment = SubscriptionPaymentRequest::firstOrFail();

        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve', $payment), [
            'approved_amount' => '3499.99', 'review_reference' => 'BANK-0001',
            'confirm_funds_checked' => '1',
        ])->assertSessionHasErrors('approved_amount');
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('trialing', $f['tenant']->fresh()->subscription->status);

        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve', $payment), [
            'approved_amount' => '3500.00', 'review_reference' => 'BANK-0001',
            'review_notes' => 'Confirmed independently in settlement report.',
            'confirm_funds_checked' => '1',
        ])->assertRedirect();

        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame(350000, $payment->fresh()->approved_amount_minor);
        $this->assertSame('BANK-0001', $payment->fresh()->review_reference);
        $this->assertSame($f['plan']->id, $f['tenant']->fresh()->subscription->plan_id);
        $this->assertSame('asset_review', $f['tenant']->fresh()->subscription->source);
        $this->assertSame('active', $f['tenant']->fresh()->status);
        $this->assertNotNull($f['tenant']->fresh()->trial->converted_at);
        $this->assertTrue($f['tenant']->fresh()->subscription->ends_at->between(
            now()->addDays(27), now()->addDays(32)
        ));
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $f['tenant']->id, 'action' => 'subscription.payment.approved',
        ]);

        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve', $payment), [
            'approved_amount' => '3500.00', 'review_reference' => 'BANK-0002',
            'confirm_funds_checked' => '1',
        ])->assertSessionHasErrors('payment');
        $this->assertSame('BANK-0001', $payment->fresh()->review_reference);
    }

    public function test_rejection_keeps_trial_and_allows_resubmission(): void
    {
        $f = $this->fixture();
        $this->enableEvidence($f['admin']);
        $this->submit($f['owner'], $f['tenant'], $f['plan']);
        $payment = SubscriptionPaymentRequest::firstOrFail();

        $this->actingAs($f['owner'])->post(route('business.subscription.payments.store', $f['tenant']), [
            'plan_id' => $f['plan']->id, 'billing_cycle' => 'monthly',
            'evidence' => $this->proof(),
        ])->assertSessionHasErrors('evidence');

        $this->actingAs($f['admin'])->post(route('platform.payments.review.reject', $payment), [
            'reason' => 'Bank statement did not show this transfer.',
        ])->assertRedirect();
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame('trial', $f['tenant']->fresh()->status);

        $this->submit($f['owner'], $f['tenant'], $f['plan']);
        $this->assertSame(2, SubscriptionPaymentRequest::count());
    }

    public function test_yearly_amount_is_snapshot_and_admin_verified_against_that_amount(): void
    {
        $f = $this->fixture();
        $this->enableEvidence($f['admin']);
        $this->submit($f['owner'], $f['tenant'], $f['plan'], 'yearly');
        $payment = SubscriptionPaymentRequest::firstOrFail();

        $this->assertSame(3500000, $payment->requested_amount_minor);
        $f['plan']->update(['yearly_price_minor' => 7500000]);

        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve', $payment), [
            'approved_amount' => '35000.00', 'review_reference' => 'BANK-YEAR-01',
            'confirm_funds_checked' => '1',
        ])->assertRedirect();

        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertTrue($f['tenant']->fresh()->subscription->ends_at->between(
            now()->addDays(362), now()->addDays(368)
        ));
    }

    public function test_receipt_download_is_tenant_isolated_and_billing_admin_only(): void
    {
        $f = $this->fixture();
        $this->enableEvidence($f['admin']);
        $this->submit($f['owner'], $f['tenant'], $f['plan']);
        $payment = SubscriptionPaymentRequest::firstOrFail();

        $otherOwner = User::factory()->create();
        $otherTenant = app(CreateTenant::class)->handle($otherOwner, ['name' => 'Another Shop']);

        $this->actingAs($otherOwner)->get(route('business.subscription.payments.receipt', [
            $otherTenant, $payment,
        ]))->assertNotFound();

        $supportAdmin = User::factory()->create(['is_platform_admin' => true, 'platform_role' => 'support']);
        $this->actingAs($supportAdmin)->get(route('platform.payments.review.receipt', $payment))
            ->assertForbidden();

        $billing = User::factory()->create(['is_platform_admin' => true, 'platform_role' => 'billing']);
        $this->actingAs($billing)->get(route('platform.payments.review.receipt', $payment))
            ->assertOk();
    }

    public function test_card_requests_cannot_be_admin_approved_without_verified_capture(): void
    {
        $f = $this->fixture();
        $card = PaymentMethod::where('code', 'card')->firstOrFail();
        $card->update(['is_enabled' => true]);
        $this->assertFalse($card->fresh()->is_operational);

        $payment = SubscriptionPaymentRequest::create([
            'tenant_id' => $f['tenant']->id,
            'plan_id' => $f['plan']->id,
            'previous_plan_id' => $f['tenant']->subscription->plan_id,
            'submitted_by' => $f['owner']->id,
            'method' => 'card', 'billing_cycle' => 'monthly',
            'requested_amount_minor' => 350000, 'currency' => 'LKR',
            'status' => 'pending',
        ]);

        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve', $payment), [
            'approved_amount' => '3500.00', 'review_reference' => 'CARDTEST001',
            'confirm_funds_checked' => '1',
        ])->assertSessionHasErrors('payment');

        $this->assertSame('trialing', $f['tenant']->fresh()->subscription->status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_temporary_super_admin_cannot_authorize_real_payment_reviews(): void
    {
        $f = $this->fixture();
        $this->enableEvidence($f['admin']);
        $this->submit($f['owner'], $f['tenant'], $f['plan']);
        $payment = SubscriptionPaymentRequest::firstOrFail();
        $temporary = User::factory()->create([
            'is_platform_admin' => true, 'platform_role' => 'super_admin',
            'temporary_admin_expires_at' => now()->addHours(2),
        ]);

        $this->actingAs($temporary)->get(route('platform.payments.review.show', $payment))
            ->assertOk();
        $this->actingAs($temporary)->post(route('platform.payments.review.approve', $payment), [
            'approved_amount' => '3500.00', 'review_reference' => 'TEMP-REVIEW-01',
            'confirm_funds_checked' => '1',
        ])->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('trial', $f['tenant']->fresh()->status);
    }

    public function test_bank_reference_cannot_be_reused_for_another_business(): void
    {
        $f = $this->fixture();
        $this->enableEvidence($f['admin']);
        $this->submit($f['owner'], $f['tenant'], $f['plan']);
        $first = SubscriptionPaymentRequest::firstOrFail();

        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve', $first), [
            'approved_amount' => '3500.00', 'review_reference' => 'BANK-REUSED-01',
            'confirm_funds_checked' => '1',
        ])->assertRedirect();

        $otherOwner = User::factory()->create();
        $other = app(CreateTenant::class)->handle($otherOwner, ['name' => 'Another Paid Shop']);
        $this->submit($otherOwner, $other, $f['plan']);
        $second = SubscriptionPaymentRequest::where('tenant_id', $other->id)->firstOrFail();

        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve', $second), [
            'approved_amount' => '3500.00', 'review_reference' => 'BANK-REUSED-01',
            'confirm_funds_checked' => '1',
        ])->assertSessionHasErrors('review_reference');

        $this->assertSame('trial', $other->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
    }
}
