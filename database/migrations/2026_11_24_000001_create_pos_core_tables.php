<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_categories', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120); $table->string('slug', 150);
            $table->timestamps(); $table->unique(['tenant_id', 'slug']);
        });
        Schema::create('pos_products', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('pos_categories')->nullOnDelete();
            $table->string('name'); $table->string('sku', 100);
            $table->string('barcode', 100)->nullable(); $table->string('brand', 120)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->unsignedBigInteger('price_minor');
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps(); $table->softDeletes();
            $table->unique(['tenant_id', 'sku']); $table->index(['tenant_id', 'barcode']);
        });
        Schema::create('pos_stocks', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('pos_products')->cascadeOnDelete();
            $table->bigInteger('quantity')->default(0);
            $table->unsignedBigInteger('reorder_level')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'product_id']);
            $table->index(['tenant_id', 'branch_id']);
        });
        Schema::create('pos_stock_movements', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('pos_products')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('change'); $table->bigInteger('balance_after');
            $table->string('reason', 40); $table->string('reference', 120)->nullable();
            $table->text('note')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'product_id', 'created_at']);
        });
        Schema::create('pos_registers', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120); $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('pos_registers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('opening_cash_minor')->default(0);
            $table->unsignedBigInteger('closing_cash_minor')->nullable();
            $table->timestamp('opened_at'); $table->timestamp('closed_at')->nullable();
            $table->timestamps(); $table->index(['tenant_id', 'register_id', 'closed_at']);
        });
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key'); $table->string('status', 24)->default('completed');
            $table->unsignedBigInteger('subtotal_minor'); $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor'); $table->string('currency', 3);
            $table->timestamp('completed_at'); $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
        });
        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('pos_products')->restrictOnDelete();
            $table->string('name'); $table->string('sku', 100);
            $table->unsignedInteger('quantity'); $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor'); $table->timestamps();
        });
        Schema::create('pos_sale_payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->string('method', 30); $table->unsignedBigInteger('amount_minor');
            $table->string('reference', 120)->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pos_sale_payments','pos_sale_items','pos_sales','pos_shifts','pos_registers','pos_stock_movements','pos_stocks','pos_products','pos_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};