<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
        });
        DB::table('users')->whereNull('password_changed_at')->update(['password_changed_at' => now()]);

        Schema::create('platform_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->text('description')->nullable();
            $table->json('scopes');
            $table->json('permissions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_security_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('session_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'session_hash']);
            $table->index(['user_id', 'revoked_at', 'last_seen_at'], 'user_security_sessions_status');
        });

        Schema::create('security_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('severity', 20)->default('warning');
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'created_at']);
            $table->index(['resolved_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
        Schema::dropIfExists('user_security_sessions');
        Schema::dropIfExists('platform_roles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'two_factor_secret', 'two_factor_recovery_codes',
            'two_factor_confirmed_at', 'password_changed_at',
        ]));
    }
};
