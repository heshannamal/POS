<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('is_active')->index();
            $table->foreignId('manager_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('timezone', 80)->nullable()->after('manager_user_id');
            $table->json('settings_overrides')->nullable()->after('timezone');
        });

        DB::table('branches')->where('is_active', false)->update(['status' => 'closed']);

        Schema::create('branch_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('store_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 30);
            $table->string('manufacturer', 100)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('status', 20)->default('registered');
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'serial_number'], 'store_device_serial_unique');
            $table->index(['branch_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('force_password_reset_at')->nullable()->index();
        });

        Schema::create('login_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('email_hash', 64)->nullable();
            $table->string('outcome', 15);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
        Schema::table('users', fn (Blueprint $table) =>
            $table->dropColumn(['last_login_at', 'last_login_ip', 'force_password_reset_at'])
        );
        Schema::dropIfExists('store_devices');
        Schema::dropIfExists('branch_user');
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['status', 'manager_user_id', 'timezone', 'settings_overrides']);
        });
    }
};
