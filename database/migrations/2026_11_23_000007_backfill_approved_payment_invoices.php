<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve historical approved/rejected/pending payment requests. Old
        // records have no original tax/coupon snapshot; do not invent it.
        DB::table('subscription_payment_requests')->orderBy('id')->chunkById(100,
            function ($payments): void {
                foreach ($payments as $payment) {
                    if (DB::table('billing_invoices')->where('payment_request_id',$payment->id)->exists()) continue;
                    $tenant=DB::table('tenants')->where('id',$payment->tenant_id)->first();
                    $plan=DB::table('plans')->where('id',$payment->plan_id)->first();
                    if (!$tenant || !$plan) continue;

                    $status=match($payment->status) {
                        'approved'=>'paid','rejected'=>'void',default=>'pending',
                    };
                    $invoiceId=DB::table('billing_invoices')->insertGetId([
                        'tenant_id'=>$payment->tenant_id,
                        'payment_request_id'=>$payment->id,
                        'plan_id'=>$payment->plan_id,
                        'plan_name'=>$plan->name,
                        'billing_cycle'=>$payment->billing_cycle,
                        'status'=>$status,
                        'currency'=>$payment->currency,
                        'list_price_minor'=>$payment->requested_amount_minor,
                        'plan_discount_minor'=>0,
                        'coupon_discount_minor'=>0,
                        'subtotal_minor'=>$payment->requested_amount_minor,
                        'tax_basis_points'=>0,
                        'tax_minor'=>0,
                        'total_minor'=>$payment->requested_amount_minor,
                        'bill_to_name'=>$tenant->name,
                        'bill_to_address'=>$tenant->address,
                        'issued_at'=>$payment->created_at,
                        'due_at'=>\Illuminate\Support\Carbon::parse($payment->created_at)->addDays(7),
                        'paid_at'=>$status==='paid'?$payment->reviewed_at:null,
                        'voided_at'=>$status==='void'?$payment->reviewed_at:null,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    DB::table('billing_invoices')->where('id',$invoiceId)->update([
                        'number'=>sprintf('INV-%08d',$invoiceId),
                        'receipt_number'=>$status==='paid'?sprintf('RCT-%08d',$invoiceId):null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Preserve historical financial documents on rollback.
    }
};
