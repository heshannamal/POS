<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('platform_role', 30)->nullable()->after('is_platform_admin');
        });
        Schema::table('branches', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('is_default');
        });
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_employees')->nullable();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 160);
            $table->string('category', 30)->default('general');
            $table->string('priority', 15)->default('normal');
            $table->string('status', 30)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_internal')->default(false);
            $table->text('message');
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('platform_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('message');
            $table->string('tone', 20)->default('info');
            $table->boolean('is_published')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_published', 'starts_at', 'ends_at'], 'announcement_visibility_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcements');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['max_branches', 'max_employees']);
        });
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('platform_role');
        });
    }
};
