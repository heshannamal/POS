<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('yearly_price_minor')->nullable()->after('monthly_price_minor');
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->boolean('is_enabled')->default(false);
            $table->text('instructions')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subscription_payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('method', 20);
            $table->string('billing_cycle', 12)->default('monthly');
            $table->unsignedBigInteger('requested_amount_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending');
            $table->string('proof_path', 255)->nullable();
            $table->string('customer_reference', 120)->nullable();
            $table->string('provider_reference', 120)->nullable()->unique();
            $table->timestamp('provider_verified_at')->nullable();
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->string('review_reference', 120)->nullable()->unique();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at'], 'subscription_payment_tenant_status');
            $table->index(['status', 'created_at'], 'subscription_payment_review_queue');
        });

        $now = now();
        DB::table('payment_methods')->insert([
            [
                'code' => 'card', 'name' => 'Card payment',
                'is_enabled' => false, 'instructions' => null,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'asset', 'name' => 'Upload payment proof',
                'is_enabled' => false,
                'instructions' => 'After paying to the official business account, upload a receipt or bank transfer confirmation. Approval is subject to independent payment verification.',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_requests');
        Schema::dropIfExists('payment_methods');
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('yearly_price_minor'));
    }
};
