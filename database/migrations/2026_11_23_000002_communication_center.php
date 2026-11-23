<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80)->unique();
            $table->string('name', 120);
            $table->string('subject', 255);
            $table->longText('body_markdown');
            $table->json('allowed_variables');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('communication_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('send_email')->default(false);
            $table->boolean('send_in_app')->default(true);
            $table->boolean('to_owner')->default(false);
            $table->boolean('to_actor')->default(false);
            $table->boolean('to_platform')->default(false);
            $table->json('extra_emails')->nullable();
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->string('event_key', 80);
            $table->string('title', 255);
            $table->text('message');
            $table->string('action_url', 500)->nullable();
            $table->char('dedupe_key', 64);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['recipient_user_id', 'dedupe_key'], 'user_notification_dedupe');
            $table->index(['recipient_user_id', 'read_at', 'created_at'], 'user_notification_inbox');
        });

        Schema::create('communication_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email', 191);
            $table->string('original_email', 191)->nullable();
            $table->string('subject', 255);
            $table->longText('body_html'); // Eloquent encrypted cast
            $table->string('status', 24)->default('queued');
            $table->string('transport', 30)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->char('dedupe_key', 64);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['recipient_email', 'dedupe_key'], 'communications_delivery_dedupe');
            $table->index(['status', 'created_at'], 'communications_delivery_queue');
        });

        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key', 80);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'event_key'], 'user_notification_preference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('communication_deliveries');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('communication_rules');
        Schema::dropIfExists('communication_templates');
    }
};
