<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->char('country', 2)->nullable()->after('address')->index();
            $table->string('website', 255)->nullable()->after('country');
            $table->string('registration_number', 100)->nullable()->after('website');
            $table->string('logo_path', 255)->nullable()->after('registration_number');
            $table->string('onboarding_status', 24)->default('incomplete')->after('onboarding_completed_at')->index();
            $table->string('lifecycle_previous_status', 25)->nullable()->after('status');
            $table->softDeletes();
        });

        // Existing businesses that completed onboarding must remain completed.
        DB::table('tenants')->whereNotNull('onboarding_completed_at')
            ->update(['onboarding_status' => 'completed']);

        Schema::create('tenant_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notes');
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'country', 'website', 'registration_number', 'logo_path',
                'onboarding_status', 'lifecycle_previous_status',
            ]);
        });
    }
};
