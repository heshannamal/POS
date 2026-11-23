<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_api_devices',function(Blueprint $table){
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->string('name',120);
            $table->string('platform',20); // desktop | mobile
            $table->string('app_version',60)->nullable();
            $table->unsignedBigInteger('auth_session_version')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('last_ip',45)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id','user_id','device_uuid'],'pos_api_device_owner_uuid');
            $table->index(['tenant_id','user_id','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_api_devices');
    }
};