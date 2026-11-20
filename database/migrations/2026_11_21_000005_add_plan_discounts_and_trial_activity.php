<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('is_discount_available')->default(false);
            $table->decimal('discount_percentage', 5, 2)->nullable();
        });
        Schema::table('trials', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('trials', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['is_discount_available', 'discount_percentage']));
    }
};
