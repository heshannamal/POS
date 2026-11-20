<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\SubscriptionPaymentRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionPaymentController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'customer_reference' => ['nullable', 'string', 'max:120'],
            'evidence' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $method = PaymentMethod::where('code', 'asset')->firstOrFail();
        if (! $method->is_operational) {
            throw ValidationException::withMessages([
                'evidence' => 'Payment evidence uploads are currently disabled by the platform.',
            ]);
        }
        if ($tenant->status === 'suspended') {
            throw ValidationException::withMessages(['evidence' => 'This business is currently suspended.']);
        }

        $plan = Plan::whereKey($data['plan_id'])->where('is_active', true)
            ->where('is_public', true)->firstOrFail();

        $amount = $data['billing_cycle'] === 'yearly'
            ? $plan->yearly_price_minor
            : $plan->monthly_price_minor;
        if ($amount === null || $amount < 1) {
            throw ValidationException::withMessages(['plan_id' => 'The selected billing cycle is not available for this paid plan.']);
        }
        if ($tenant->currency !== $plan->currency) {
            throw ValidationException::withMessages([
                'plan_id' => 'The plan currency does not match your business currency. Contact support for currency options.',
            ]);
        }

        // Files are private: never published in storage/app/public or via storage:link.
        $path = $request->file('evidence')->store('subscription-payment-evidence/'.$tenant->id, 'local');
        if (! $path) {
            throw ValidationException::withMessages(['evidence' => 'Upload failed. Please try again.']);
        }

        try {
            $record = DB::transaction(function () use ($tenant, $plan, $data, $path, $amount, $request) {
                Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                if (SubscriptionPaymentRequest::where('tenant_id', $tenant->id)
                    ->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'evidence' => 'This business already has a payment awaiting review. Please wait for an admin decision.',
                    ]);
                }

                $payment = SubscriptionPaymentRequest::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'previous_plan_id' => $tenant->subscription?->plan_id,
                    'submitted_by' => $request->user()->id,
                    'method' => 'asset',
                    'billing_cycle' => $data['billing_cycle'],
                    'requested_amount_minor' => $amount,
                    'currency' => $plan->currency,
                    'status' => 'pending',
                    'proof_path' => $path,
                    'customer_reference' => $data['customer_reference'] ?? null,
                ]);

                AuditLog::create([
                    'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                    'action' => 'subscription.payment.submitted',
                    'subject_type' => SubscriptionPaymentRequest::class,
                    'subject_id' => $payment->id,
                    'metadata' => [
                        'method' => 'asset', 'plan_id' => $plan->id,
                        'billing_cycle' => $data['billing_cycle'],
                        'requested_amount_minor' => $amount, 'currency' => $plan->currency,
                    ],
                    'ip_address' => $request->ip(),
                ]);

                return $payment;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return redirect()->route('business.subscription.index', $tenant)
            ->with('status', 'Payment evidence #'.$record->id.' was submitted for manual verification. No subscription access has been granted yet.');
    }

    public function receipt(Tenant $tenant, SubscriptionPaymentRequest $payment): StreamedResponse
    {
        abort_unless($payment->tenant_id === $tenant->id && $payment->method === 'asset'
            && $payment->proof_path, 404);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download(
            $payment->proof_path,
            'your-payment-evidence-'.$payment->id.'.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION),
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']
        );
    }
}
