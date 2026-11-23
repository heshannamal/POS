<?php

namespace Tests\Feature;

use App\Models\BillingCoupon;
use App\Models\BillingCouponRedemption;
use App\Models\BillingInvoice;
use App\Models\BillingRefund;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPaymentRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingInvoicing;
use App\Services\BillingMoney;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvancedBillingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name'=>'Phase Six Store']);
        $plan = Plan::where('slug','business')->firstOrFail();
        $plan->update([
            'is_public'=>true,'is_active'=>true,
            'currency'=>'LKR','monthly_price_minor'=>10000,
            'yearly_price_minor'=>100000,'is_discount_available'=>false,
            'discount_percentage'=>null,
        ]);
        PaymentMethod::where('code','asset')->update(['is_enabled'=>true]);
        $admin = User::factory()->create([
            'is_platform_admin'=>true,'platform_role'=>'super_admin',
        ]);
        return compact('owner','tenant','plan','admin');
    }

    private function evidence(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'bank-proof.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO/XV8kAAAAASUVORK5CYII=')
        );
    }

    private function submit(array $f, ?string $coupon = null): SubscriptionPaymentRequest
    {
        $payload = [
            'plan_id'=>$f['plan']->id,'billing_cycle'=>'monthly',
            'evidence'=>$this->evidence(),'customer_reference'=>'BANKPENDING',
        ];
        if ($coupon) $payload['coupon_code']=$coupon;
        $this->actingAs($f['owner'])->post(route('business.subscription.payments.store',$f['tenant']),$payload)
            ->assertRedirect(route('business.subscription.index',$f['tenant']));
        return SubscriptionPaymentRequest::where('tenant_id',$f['tenant']->id)
            ->latest('id')->firstOrFail();
    }

    private function approve(array $f, SubscriptionPaymentRequest $payment, string $reference): void
    {
        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve',$payment),[
            'approved_amount'=>number_format($payment->requested_amount_minor/100,2,'.',''),
            'review_reference'=>$reference,'confirm_funds_checked'=>'1',
            'review_notes'=>'Independently checked bank settlement statement',
        ])->assertRedirect();
    }

    public function test_paid_invoice_is_issued_only_after_manually_verified_payment_and_pdf_is_downloadable(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $invoice=BillingInvoice::where('payment_request_id',$payment->id)->firstOrFail();

        $this->assertSame('pending',$invoice->status);
        $this->assertSame('INV-'.str_pad($invoice->id,8,'0',STR_PAD_LEFT),$invoice->number);
        $this->assertSame(10000,$invoice->total_minor);
        $this->assertNull($invoice->receipt_number);
        $this->assertSame('trialing',$f['tenant']->fresh()->subscription->status);
        $this->actingAs($f['owner'])->get(route('business.billing.pdf',[$f['tenant'],$invoice]))
            ->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->actingAs($f['owner'])->get(route('business.billing.pdf',[$f['tenant'],$invoice,'type'=>'receipt']))
            ->assertNotFound();

        $f['plan']->update(['monthly_price_minor'=>25000]);
        $this->approve($f,$payment,'BANK-INV-APPROVED-1');
        $invoice->refresh();
        $this->assertSame('paid',$invoice->status);
        $this->assertSame(10000,$invoice->total_minor);
        $this->assertSame('RCT-'.str_pad($invoice->id,8,'0',STR_PAD_LEFT),$invoice->receipt_number);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame('active',$f['tenant']->fresh()->subscription->status);
        $receipt=$this->actingAs($f['owner'])->get(route('business.billing.pdf',
            [$f['tenant'],$invoice,'type'=>'receipt']));
        $receipt->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-1.4',$receipt->getContent());
        $this->assertStringContainsString('xref',$receipt->getContent());

        $this->assertDatabaseHas('audit_logs',[
            'tenant_id'=>$f['tenant']->id,'action'=>'billing.invoice.created','subject_id'=>$invoice->id,
        ]);
    }

    public function test_discount_and_coupon_tax_calculation_uses_exact_integer_minor_units(): void
    {
        $f=$this->fixture();
        $f['plan']->update(['is_discount_available'=>true,'discount_percentage'=>'10.00']);
        PlatformSetting::updateOrCreate(['key'=>'billing.tax_basis_points'],['value'=>'500']);
        BillingCoupon::create([
            'code'=>'WELCOME10','discount_type'=>'percent','discount_value'=>1000,
            'max_uses'=>1,'max_uses_per_tenant'=>1,'is_active'=>true,
        ]);
        $payment=$this->submit($f,'welcome10');
        $invoice=$payment->billingInvoice;

        $this->assertSame(10000,$invoice->list_price_minor);
        $this->assertSame(1000,$invoice->plan_discount_minor);
        $this->assertSame(900,$invoice->coupon_discount_minor);
        $this->assertSame(8100,$invoice->subtotal_minor);
        $this->assertSame(405,$invoice->tax_minor);
        $this->assertSame(8505,$payment->requested_amount_minor);
        $this->assertSame('WELCOME10',$invoice->coupon_code);
        $this->assertDatabaseHas('billing_coupon_redemptions',[
            'payment_request_id'=>$payment->id,'status'=>'reserved','discount_minor'=>900,
        ]);

        $this->approve($f,$payment,'BANK-TAX-COUPON');
        $this->assertDatabaseHas('billing_coupon_redemptions',[
            'payment_request_id'=>$payment->id,'status'=>'redeemed',
        ]);
        $this->assertSame(8505,$invoice->fresh()->total_minor);
    }

    public function test_rejection_voids_invoice_and_releases_coupon_reservation_for_other_business(): void
    {
        $f=$this->fixture();
        $coupon=BillingCoupon::create([
            'code'=>'ONEUSE','discount_type'=>'fixed','discount_value'=>2500,
            'currency'=>'LKR','max_uses'=>1,'max_uses_per_tenant'=>1,
            'is_active'=>true,
        ]);
        $first=$this->submit($f,'ONEUSE');
        $invoice=$first->billingInvoice;
        $otherOwner=User::factory()->create();
        $other=app(CreateTenant::class)->handle($otherOwner,['name'=>'Other Tenant']);
        $this->actingAs($otherOwner)->post(route('business.subscription.payments.store',$other),[
            'plan_id'=>$f['plan']->id,'billing_cycle'=>'monthly','coupon_code'=>'ONEUSE',
            'evidence'=>$this->evidence(),
        ])->assertSessionHasErrors('coupon_code');
        $this->assertDatabaseCount('billing_coupon_redemptions',1);

        $this->actingAs($f['admin'])->post(route('platform.payments.review.reject',$first),[
            'reason'=>'Bank transfer cannot be independently verified.',
        ])->assertRedirect();
        $this->assertSame('void',$invoice->fresh()->status);
        $this->assertSame('released',
            BillingCouponRedemption::where('payment_request_id',$first->id)->value('status'));
        $this->assertNull($invoice->fresh()->receipt_number);
        $this->assertSame('trialing',$f['tenant']->fresh()->subscription->status);

        $this->actingAs($otherOwner)->post(route('business.subscription.payments.store',$other),[
            'plan_id'=>$f['plan']->id,'billing_cycle'=>'monthly','coupon_code'=>'ONEUSE',
            'evidence'=>$this->evidence(),
        ])->assertRedirect(route('business.subscription.index',$other));
        $this->assertDatabaseCount('billing_coupon_redemptions',2);
    }

    public function test_refunds_require_external_confirmation_and_never_exceed_verified_invoice_total(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $this->approve($f,$payment,'BANK-REFUND-ORIGINAL');
        $invoice=$payment->billingInvoice->fresh();

        $temporary=User::factory()->create([
            'is_platform_admin'=>true,'platform_role'=>'super_admin',
            'temporary_admin_expires_at'=>now()->addHours(1),
        ]);
        $this->actingAs($temporary)->post(route('platform.billing.invoices.refund',$invoice),[
            'amount'=>'50.00','external_reference'=>'REFUND-DENIED',
            'method'=>'bank','reason'=>'Verified settlement reversed.',
            'confirm_external_refund'=>'1',
        ])->assertForbidden();

        $this->actingAs($f['admin'])->post(route('platform.billing.invoices.refund',$invoice),[
            'amount'=>'50.00','external_reference'=>'REFUND-NOT-CHECKED',
            'method'=>'bank','reason'=>'Settlement not yet verified.',
        ])->assertSessionHasErrors('confirm_external_refund');

        $this->actingAs($f['admin'])->post(route('platform.billing.invoices.refund',$invoice),[
            'amount'=>'40.00','external_reference'=>'EXT-REFUND-1',
            'method'=>'bank','reason'=>'Confirmed externally on bank statement.',
            'confirm_external_refund'=>'1',
        ])->assertRedirect();

        $refund=BillingRefund::firstOrFail();
        $this->assertSame(4000,$refund->amount_minor);
        $this->assertStringStartsWith('CN-',$refund->credit_note_number);
        $this->assertSame('paid',$invoice->fresh()->status);

        $this->actingAs($f['admin'])->post(route('platform.billing.invoices.refund',$invoice),[
            'amount'=>'61.00','external_reference'=>'EXT-REFUND-2',
            'method'=>'bank','reason'=>'Confirmed externally on bank statement.',
            'confirm_external_refund'=>'1',
        ])->assertSessionHasErrors('amount');
        $this->assertSame(1,BillingRefund::count());
        $pdf=$this->actingAs($f['owner'])->get(route('business.billing.credit-note',[$f['tenant'],$refund]));
        $pdf->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-1.4',$pdf->getContent());
        $this->assertDatabaseHas('audit_logs',['action'=>'billing.refund.recorded','subject_id'=>$refund->id]);
    }

    public function test_tenant_isolation_and_role_controls_for_billing_documents(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $invoice=$payment->billingInvoice;
        $otherOwner=User::factory()->create();
        $other=app(CreateTenant::class)->handle($otherOwner,['name'=>'Isolated Shop']);

        $this->actingAs($otherOwner)->get(route('business.billing.show',[$other,$invoice]))
            ->assertNotFound();
        $this->actingAs($otherOwner)->get(route('business.billing.pdf',[$other,$invoice]))
            ->assertNotFound();

        $support=User::factory()->create(['is_platform_admin'=>true,'platform_role'=>'support']);
        $this->actingAs($support)->get(route('platform.billing.invoices.index'))->assertForbidden();

        $billing=User::factory()->create(['is_platform_admin'=>true,'platform_role'=>'billing']);
        $this->actingAs($billing)->get(route('platform.billing.invoices.show',$invoice))->assertOk();
        $this->actingAs($f['owner'])->get(route('platform.billing.invoices.show',$invoice))
            ->assertForbidden();
    }

    public function test_tax_settings_apply_to_new_invoice_without_modifying_older_invoice_snapshots(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $old=$payment->billingInvoice;
        $this->actingAs($f['admin'])->put(route('platform.billing.settings.update'),[
            'tax_rate'=>'7.50','invoice_due_days'=>14,'grace_days'=>5,
        ])->assertRedirect();
        $this->assertSame('750',PlatformSetting::valueFor('billing.tax_basis_points'));
        $this->assertSame(0,$old->fresh()->tax_minor);
        $this->assertDatabaseHas('audit_logs',['action'=>'billing.settings.updated']);
    }

    public function test_business_renewal_preference_does_not_activate_without_verified_payment(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $this->approve($f,$payment,'BANK-RENEW-PREFERENCE');

        $this->actingAs($f['owner'])->put(route('business.billing.renewal',$f['tenant']),[
            'auto_renew'=>'1','cancel_at_period_end'=>'0',
        ])->assertRedirect();
        $subscription=$f['tenant']->fresh()->subscription;
        $this->assertTrue($subscription->auto_renew);
        $this->assertSame('active',$subscription->status);
        $this->assertSame(1,BillingInvoice::count());

        $this->actingAs($f['owner'])->put(route('business.billing.renewal',$f['tenant']),[
            'auto_renew'=>'1','cancel_at_period_end'=>'1',
        ])->assertSessionHasErrors('auto_renew');
    }

    public function test_expiry_maintenance_is_safe_and_manual_extension_requires_permanent_billing_review(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $this->approve($f,$payment,'BANK-EXPIRY-MAINTENANCE');
        $subscription=$f['tenant']->fresh()->subscription;
        $subscription->forceFill(['ends_at'=>now()->subDay()])->save();

        $this->assertSame(0,Artisan::call('billing:maintain',['--dry-run'=>true]));
        $this->assertSame('active',$subscription->fresh()->status);

        $this->assertSame(0,Artisan::call('billing:maintain'));
        $this->assertSame('past_due',$subscription->fresh()->status);
        $this->assertSame(0,Artisan::call('billing:maintain'));
        $this->assertSame('past_due',$subscription->fresh()->status);

        $this->actingAs($f['admin'])->post(route('platform.billing.subscriptions.extend',$f['tenant']),[
            'days'=>30,'reason'=>'Goodwill extension approved by billing team.',
            'confirm_no_payment'=>'1',
        ])->assertRedirect();
        $this->assertSame('active',$subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->ends_at->isFuture());
        $this->assertSame(1,BillingInvoice::count());
        $this->assertDatabaseHas('audit_logs',[
            'action'=>'billing.subscription.free_extension','tenant_id'=>$f['tenant']->id,
        ]);
    }

    public function test_report_has_separate_currency_rows_and_csv_is_protected(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $this->approve($f,$payment,'BANK-REPORT-1');
        $this->actingAs($f['admin'])->get(route('platform.billing.reports.index'))
            ->assertOk()->assertSee('LKR')->assertSee('USD');
        $this->actingAs($f['admin'])->get(route('platform.billing.reports.export'))
            ->assertOk()->assertHeader('Content-Type','text/csv; charset=UTF-8');
        $this->assertDatabaseHas('audit_logs',['action'=>'billing.report.exported']);
    }

    public function test_pending_review_reminder_targets_admin_instead_of_requesting_customer_repayment(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $invoice=$payment->billingInvoice;
        $invoice->update(['issued_at'=>now()->subDays(5)]);
        $this->assertSame(0,Artisan::call('billing:remind-invoices',['--days'=>2]));
        $this->assertSame(0,Artisan::call('billing:remind-invoices',['--days'=>2]));
        $this->assertSame(1,\App\Models\UserNotification::where('recipient_user_id',$f['admin']->id)
            ->where('event_key','billing.review_overdue')->count());
        $this->assertSame(0,\App\Models\UserNotification::where('recipient_user_id',$f['owner']->id)
            ->where('event_key','billing.review_overdue')->count());
    }

    public function test_legacy_pending_payment_gains_invoice_on_review_without_changing_original_price(): void
    {
        $f=$this->fixture();
        $legacy=SubscriptionPaymentRequest::create([
            'tenant_id'=>$f['tenant']->id,'plan_id'=>$f['plan']->id,
            'previous_plan_id'=>$f['tenant']->subscription->plan_id,
            'submitted_by'=>$f['owner']->id,
            'method'=>'asset','billing_cycle'=>'yearly',
            'requested_amount_minor'=>12345,'currency'=>'LKR',
            'status'=>'pending','proof_path'=>'legacy/path.pdf',
        ]);
        $this->actingAs($f['admin'])->post(route('platform.payments.review.approve',$legacy),[
            'approved_amount'=>'123.45','review_reference'=>'LEGACY-APPROVED',
            'confirm_funds_checked'=>'1',
        ])->assertRedirect();
        $invoice=BillingInvoice::where('payment_request_id',$legacy->id)->firstOrFail();
        $this->assertSame(12345,$invoice->total_minor);
        $this->assertSame('paid',$invoice->status);
        $this->assertNotNull($invoice->receipt_number);
    }

    public function test_quote_preview_calculates_tax_coupon_and_does_not_reserve_coupon(): void
    {
        $f=$this->fixture();
        $f['plan']->update(['is_discount_available'=>true,'discount_percentage'=>'10.00']);
        PlatformSetting::updateOrCreate(['key'=>'billing.tax_basis_points'],['value'=>'500']);
        BillingCoupon::create([
            'code'=>'QUOTE10','discount_type'=>'percent','discount_value'=>1000,
            'max_uses'=>1,'max_uses_per_tenant'=>1,'is_active'=>true,
        ]);

        $this->actingAs($f['owner'])->getJson(route('business.subscription.payments.quote',$f['tenant']).'?'.http_build_query([
            'plan_id'=>$f['plan']->id,'billing_cycle'=>'monthly','coupon_code'=>'quote10',
        ]))->assertOk()->assertJson([
            'currency'=>'LKR',
            'list_price_minor'=>10000,
            'plan_discount_minor'=>1000,
            'coupon_discount_minor'=>900,
            'tax_minor'=>405,
            'total_minor'=>8505,
        ]);
        $this->assertDatabaseCount('billing_coupon_redemptions',0);

        $this->actingAs($f['owner'])->getJson(route('business.subscription.payments.quote',$f['tenant']).'?'.http_build_query([
            'plan_id'=>$f['plan']->id,'billing_cycle'=>'monthly','coupon_code'=>'unknown',
        ]))->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    }

    public function test_invoice_resend_requires_active_email_rule_and_global_switch(): void
    {
        $f=$this->fixture();
        $payment=$this->submit($f);
        $invoice=$payment->billingInvoice;
        $this->actingAs($f['admin'])->post(route('platform.billing.invoices.resend',$invoice))
            ->assertSessionHasErrors('invoice');
        PlatformSetting::updateOrCreate(['key'=>'communications.email_enabled'],['value'=>'1']);
        PlatformSetting::updateOrCreate(['key'=>'communications.test_mode'],['value'=>'1']);
        PlatformSetting::updateOrCreate(['key'=>'communications.test_recipients'],['value'=>'qa@example.test']);
        $this->actingAs($f['admin'])->post(route('platform.billing.invoices.resend',$invoice))
            ->assertSessionHasErrors('invoice');
        \App\Models\CommunicationRule::where('event_key','billing.invoice_issued')
            ->update(['send_email'=>true]);
        $this->actingAs($f['admin'])->post(route('platform.billing.invoices.resend',$invoice))
            ->assertRedirect();
        $this->assertDatabaseHas('communication_deliveries',[
            'event_key'=>'billing.invoice_issued',
            'recipient_email'=>'qa@example.test',
        ]);
    }
}
