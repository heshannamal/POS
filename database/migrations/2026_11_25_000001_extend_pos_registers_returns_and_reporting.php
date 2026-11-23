<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('expected_cash_minor')->nullable();
            $table->bigInteger('cash_difference_minor')->nullable();
            $table->text('closing_note')->nullable();
        });
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_cost_minor')->nullable();
        });
        Schema::create('pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('type', 25);
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['tenant_id','shift_id','type']);
        });
        Schema::create('pos_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('pos_sales')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->unsignedBigInteger('refund_minor');
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['tenant_id','idempotency_key']);
            $table->index(['tenant_id','sale_id']);
        });
        Schema::create('pos_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('pos_returns')->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained('pos_sale_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->boolean('restock');
            $table->unsignedBigInteger('refund_minor');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('pos_return_items');
        Schema::dropIfExists('pos_returns');
        Schema::dropIfExists('pos_cash_movements');
        Schema::table('pos_sale_items', fn (Blueprint $table) => $table->dropColumn('unit_cost_minor'));
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->dropColumn(['expected_cash_minor','cash_difference_minor','closing_note']);
        });
    }
};