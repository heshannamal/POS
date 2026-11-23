<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description', 255)->nullable();
            $table->string('discount_type', 12);
            $table->unsignedBigInteger('discount_value');
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_tenant')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_request_id')->nullable()->unique()->constrained('subscription_payment_requests')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('plan_name', 120);
            $table->string('billing_cycle', 12);
            $table->string('status', 16)->default('pending')->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('list_price_minor');
            $table->unsignedBigInteger('plan_discount_minor')->default(0);
            $table->unsignedBigInteger('coupon_discount_minor')->default(0);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedInteger('tax_basis_points')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('coupon_code', 40)->nullable();
            $table->string('bill_to_name', 190);
            $table->text('bill_to_address')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('receipt_number', 30)->nullable()->unique();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'issued_at'], 'billing_invoice_tenant_status');
        });

        Schema::create('billing_coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('billing_coupons')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_request_id')->unique()->constrained('subscription_payment_requests')->restrictOnDelete();
            $table->unsignedBigInteger('discount_minor');
            $table->string('status', 12)->default('reserved');
            $table->timestamps();
            $table->index(['coupon_id', 'status']);
        });

        Schema::create('billing_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('billing_invoices')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('credit_note_number', 30)->nullable()->unique();
            $table->string('external_reference', 120)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('method', 20);
            $table->text('reason');
            $table->timestamp('verified_at');
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('auto_renew')->default(false);
            $table->string('billing_cycle', 12)->nullable();
            $table->timestamp('cancel_at_period_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn([
            'auto_renew', 'billing_cycle', 'cancel_at_period_end',
        ]));
        Schema::dropIfExists('billing_refunds');
        Schema::dropIfExists('billing_coupon_redemptions');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_coupons');
    }
};
