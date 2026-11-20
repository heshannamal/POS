<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPaymentRequest;
use App\Models\Tenant;
use App\Models\TrialEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionPaymentApproval
{
    /**
     * Review an incoming subscription request without trusting the uploaded
     * receipt as proof of funds. The operator must independently reconcile
     * with the bank/acquirer, then supply the confirmed amount and reference.
     */
    public function approve(
        SubscriptionPaymentRequest $request,
        User $operator,
        int $verifiedAmountMinor,
        string $externalReference,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($request, $operator, $verifiedAmountMinor, $externalReference, $notes): void {
            $payment = SubscriptionPaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'This request has already been reviewed.']);
            }
            if ($verifiedAmountMinor < $payment->requested_amount_minor) {
                throw ValidationException::withMessages([
                    'approved_amount' => 'The verified amount is below the payable amount. Reject the request or resolve the difference before activation.',
                ]);
            }
            if (SubscriptionPaymentRequest::where('review_reference', $externalReference)
                ->whereKeyNot($payment->id)->exists()) {
                throw ValidationException::withMessages(['review_reference' => 'This verified transaction reference has already been used.']);
            }
            if ($payment->method === 'card' && (! $payment->provider_verified_at || ! $payment->provider_reference)) {
                throw ValidationException::withMessages(['payment' => 'Card settlement has not been verified by a connected payment provider.']);
            }

            $tenant = Tenant::whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
            if ($tenant->status === 'suspended') {
                throw ValidationException::withMessages(['payment' => 'This business is suspended. Resolve the suspension before activating a subscription.']);
            }
            $subscription = Subscription::where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();

            // Do not let a stale payment switch a tenant away from a plan that
            // was manually activated while this receipt was under review.
            if ($subscription->status === 'active'
                && $subscription->plan_id !== $payment->plan_id) {
                throw ValidationException::withMessages([
                    'payment' => 'The business changed plans since this request was submitted. Reconcile it manually before approving.',
                ]);
            }

            $before = $tenant->status;
            $start = $subscription->status === 'active' && $subscription->ends_at?->isFuture()
                ? $subscription->ends_at->copy()
                : now();
            $end = $payment->billing_cycle === 'yearly'
                ? $start->copy()->addYearNoOverflow()
                : $start->copy()->addMonthNoOverflow();

            $payment->update([
                'status' => 'approved',
                'approved_amount_minor' => $verifiedAmountMinor,
                'review_reference' => $externalReference,
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $subscription->update([
                'plan_id' => $payment->plan_id,
                'status' => 'active',
                'source' => $payment->method === 'asset' ? 'asset_review' : 'card_review',
                'started_at' => $start,
                'ends_at' => $end,
            ]);

            $tenant->update(['status' => 'active']);
            if ($before === 'trial' && $tenant->trial && $tenant->trial->converted_at === null) {
                $tenant->trial()->update(['converted_at' => now()]);
                TrialEvent::create([
                    'trial_id' => $tenant->trial->id, 'actor_id' => $operator->id,
                    'action' => 'payment_approved',
                ]);
            }

            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $operator->id,
                'action' => 'subscription.payment.approved',
                'subject_type' => SubscriptionPaymentRequest::class,
                'subject_id' => $payment->id,
                'metadata' => [
                    'method' => $payment->method,
                    'currency' => $payment->currency,
                    'requested_minor' => $payment->requested_amount_minor,
                    'verified_minor' => $verifiedAmountMinor,
                    'plan_id' => $payment->plan_id,
                    'review_reference' => $externalReference,
                    'billing_cycle' => $payment->billing_cycle,
                ],
            ]);
        }, attempts: 3);
    }

    public function reject(
        SubscriptionPaymentRequest $request,
        User $operator,
        string $reason
    ): void {
        DB::transaction(function () use ($request, $operator, $reason): void {
            $payment = SubscriptionPaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'This request has already been reviewed.']);
            }
            $payment->update([
                'status' => 'rejected', 'reviewed_by' => $operator->id,
                'reviewed_at' => now(), 'review_notes' => $reason,
            ]);
            AuditLog::create([
                'tenant_id' => $payment->tenant_id, 'actor_id' => $operator->id,
                'action' => 'subscription.payment.rejected',
                'subject_type' => SubscriptionPaymentRequest::class,
                'subject_id' => $payment->id, 'metadata' => ['reason' => $reason],
            ]);
        }, attempts: 3);
    }
}
