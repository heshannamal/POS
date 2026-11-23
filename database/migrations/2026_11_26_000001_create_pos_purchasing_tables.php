<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_suppliers',function(Blueprint $t){
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name'); $t->string('code',60);
            $t->string('contact_person')->nullable(); $t->string('email')->nullable();
            $t->string('phone',80)->nullable(); $t->text('address')->nullable();
            $t->unsignedInteger('payment_terms_days')->default(0);
            $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes();
            $t->unique(['tenant_id','code']);
        });
        Schema::create('pos_purchase_orders',function(Blueprint $t){
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('pos_suppliers')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status',25)->default('draft'); $t->string('currency',3);
            $t->unsignedBigInteger('subtotal_minor')->default(0);
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('total_minor')->default(0);
            $t->unsignedBigInteger('received_minor')->default(0);
            $t->date('expected_date')->nullable(); $t->text('note')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->timestamps();
            $t->index(['tenant_id','branch_id','status']);
        });
        Schema::create('pos_purchase_order_items',function(Blueprint $t){
            $t->id(); $t->foreignId('purchase_order_id')->constrained('pos_purchase_orders')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('pos_products')->restrictOnDelete();
            $t->string('name'); $t->string('sku',100);
            $t->unsignedInteger('quantity'); $t->unsignedInteger('received_quantity')->default(0);
            $t->unsignedBigInteger('unit_cost_minor'); $t->unsignedBigInteger('line_total_minor');
            $t->timestamps(); $t->unique(['purchase_order_id','product_id']);
        });
        Schema::create('pos_goods_receipts',function(Blueprint $t){
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_order_id')->constrained('pos_purchase_orders')->restrictOnDelete();
            $t->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $t->uuid('idempotency_key'); $t->string('reference',120)->nullable();
            $t->timestamp('received_at'); $t->timestamps();
            $t->unique(['tenant_id','idempotency_key']);
        });
        Schema::create('pos_goods_receipt_items',function(Blueprint $t){
            $t->id(); $t->foreignId('goods_receipt_id')->constrained('pos_goods_receipts')->restrictOnDelete();
            $t->foreignId('purchase_order_item_id')->constrained('pos_purchase_order_items')->restrictOnDelete();
            $t->unsignedInteger('quantity'); $t->unsignedBigInteger('unit_cost_minor');
            $t->timestamps();
        });
        Schema::create('pos_supplier_payments',function(Blueprint $t){
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_order_id')->constrained('pos_purchase_orders')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('idempotency_key'); $t->unsignedBigInteger('amount_minor');
            $t->string('method',30); $t->string('reference',120)->nullable();
            $t->timestamp('paid_at'); $t->timestamps();
            $t->unique(['tenant_id','idempotency_key']);
        });
        Schema::create('pos_inventory_counts',function(Blueprint $t){
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained('pos_products')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->bigInteger('before_quantity'); $t->bigInteger('counted_quantity');
            $t->string('reason',255); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach(['pos_inventory_counts','pos_supplier_payments','pos_goods_receipt_items','pos_goods_receipts','pos_purchase_order_items','pos_purchase_orders','pos_suppliers'] as $name)Schema::dropIfExists($name);
    }
};