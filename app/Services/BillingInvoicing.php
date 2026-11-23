<?php

namespace App\Services;

use App\Models\BillingCoupon;
use App\Models\BillingCouponRedemption;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPaymentRequest;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class BillingInvoicing
{
    /** Call inside a transaction after locking the owning tenant row. */
    public function quote(Tenant $tenant, Plan $plan, string $cycle, ?string $code = null): array
    {
        $list = $cycle === 'yearly' ? $plan->yearly_price_minor : $plan->monthly_price_minor;
        $discounted = $plan->priceForCycleMinor($cycle);
        if ($list === null || $discounted === null || $discounted < 1) {
            throw ValidationException::withMessages(['plan_id' => 'The selected paid billing cycle is unavailable.']);
        }
        if ($plan->currency !== $tenant->currency) {
            throw ValidationException::withMessages(['plan_id' => 'Your business and selected plan must use the same currency.']);
        }

        $coupon = null;
        $couponDiscount = 0;
        if ($code) {
            $coupon = BillingCoupon::where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            if (! $coupon || ! $coupon->is_active
                || $coupon->starts_at?->isFuture() || $coupon->expires_at?->isPast()) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon is unavailable or expired.']);
            }
            if ($coupon->discount_type === 'fixed' && $coupon->currency !== $plan->currency) {
                throw ValidationException::withMessages(['coupon_code' => 'Coupon currency does not match this plan.']);
            }
            $redemptions = $coupon->redemptions()->whereIn('status', ['reserved','redeemed']);
            if ($coupon->max_uses !== null && (clone $redemptions)->count() >= $coupon->max_uses) {
                throw ValidationException::withMessages(['coupon_code' => 'The coupon usage limit has been reached.']);
            }
            if ((clone $redemptions)->where('tenant_id', $tenant->id)->count()
                >= $coupon->max_uses_per_tenant) {
                throw ValidationException::withMessages(['coupon_code' => 'This business has reached its coupon usage limit.']);
            }
            $couponDiscount = $coupon->discount_type === 'percent'
                ? BillingMoney::percentage($discounted, (int) $coupon->discount_value)
                : (int) $coupon->discount_value;
            $couponDiscount = min($couponDiscount, $discounted - 1);
        }

        $subtotal = $discounted - $couponDiscount;
        $taxBp = max(0, min(10000, (int) PlatformSetting::valueFor('billing.tax_basis_points','0')));
        $tax = BillingMoney::percentage($subtotal, $taxBp);

        return [
            'coupon' => $coupon,
            'list_price_minor' => (int) $list,
            'plan_discount_minor' => (int) $list - (int) $discounted,
            'coupon_discount_minor' => $couponDiscount,
            'subtotal_minor' => $subtotal,
            'tax_basis_points' => $taxBp,
            'tax_minor' => $tax,
            'total_minor' => $subtotal + $tax,
        ];
    }

    public function createForSubmission(
        Tenant $tenant,
        SubscriptionPaymentRequest $payment,
        Plan $plan,
        array $quote
    ): BillingInvoice {
        $invoice = BillingInvoice::create([
            'tenant_id' => $tenant->id, 'payment_request_id' => $payment->id,
            'plan_id' => $plan->id, 'plan_name' => $plan->name,
            'billing_cycle' => $payment->billing_cycle,
            'status' => 'pending', 'currency' => $plan->currency,
            'list_price_minor' => $quote['list_price_minor'],
            'plan_discount_minor' => $quote['plan_discount_minor'],
            'coupon_discount_minor' => $quote['coupon_discount_minor'],
            'subtotal_minor' => $quote['subtotal_minor'],
            'tax_basis_points' => $quote['tax_basis_points'],
            'tax_minor' => $quote['tax_minor'],
            'total_minor' => $quote['total_minor'],
            'coupon_code' => $quote['coupon']?->code,
            'bill_to_name' => $tenant->name,
            'bill_to_address' => $tenant->address,
            'issued_at' => now(),
            'due_at' => now()->addDays(max(1, min(60,
                (int) PlatformSetting::valueFor('billing.invoice_due_days','7')))),
        ]);
        $invoice->update(['number' => sprintf('INV-%08d', $invoice->id)]);

        if ($quote['coupon']) {
            BillingCouponRedemption::create([
                'coupon_id' => $quote['coupon']->id,
                'tenant_id' => $tenant->id,
                'payment_request_id' => $payment->id,
                'discount_minor' => $quote['coupon_discount_minor'],
                'status' => 'reserved',
            ]);
        }
        return $invoice;
    }

    /** Existing pending payment requests predate Phase 6; do not invent a new charge. */
    public function ensureLegacyInvoice(SubscriptionPaymentRequest $payment, Tenant $tenant): BillingInvoice
    {
        $existing = BillingInvoice::where('payment_request_id', $payment->id)->lockForUpdate()->first();
        if ($existing) return $existing;

        $invoice = BillingInvoice::create([
            'tenant_id' => $tenant->id, 'payment_request_id' => $payment->id,
            'plan_id' => $payment->plan_id, 'plan_name' => $payment->plan?->name ?? 'Subscription plan',
            'billing_cycle' => $payment->billing_cycle, 'status' => 'pending',
            'currency' => $payment->currency,
            'list_price_minor' => $payment->requested_amount_minor,
            'plan_discount_minor' => 0, 'coupon_discount_minor' => 0,
            'subtotal_minor' => $payment->requested_amount_minor,
            'tax_basis_points' => 0, 'tax_minor' => 0,
            'total_minor' => $payment->requested_amount_minor,
            'bill_to_name' => $tenant->name, 'bill_to_address' => $tenant->address,
            'issued_at' => $payment->created_at, 'due_at' => $payment->created_at?->copy()->addDays(7) ?? now(),
        ]);
        $invoice->update(['number' => sprintf('INV-%08d', $invoice->id)]);
        return $invoice;
    }

    public function paid(SubscriptionPaymentRequest $payment, Tenant $tenant, $periodStart, $periodEnd): void
    {
        $invoice = $this->ensureLegacyInvoice($payment, $tenant);
        if ($invoice->status !== 'pending' || $invoice->total_minor !== $payment->approved_amount_minor) {
            throw ValidationException::withMessages(['payment'=>'Invoice total does not match the independently verified amount.']);
        }
        $invoice->update([
            'status'=>'paid', 'paid_at'=>now(),
            'receipt_number'=>sprintf('RCT-%08d',$invoice->id),
            'period_start'=>$periodStart, 'period_end'=>$periodEnd,
        ]);
        BillingCouponRedemption::where('payment_request_id',$payment->id)
            ->where('status','reserved')->update(['status'=>'redeemed']);
    }

    public function rejected(SubscriptionPaymentRequest $payment): void
    {
        BillingInvoice::where('payment_request_id',$payment->id)
            ->where('status','pending')->update(['status'=>'void','voided_at'=>now()]);
        BillingCouponRedemption::where('payment_request_id',$payment->id)
            ->where('status','reserved')->update(['status'=>'released']);
    }
}
