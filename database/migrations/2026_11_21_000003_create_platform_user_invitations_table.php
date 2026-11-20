<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 191)->index();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['expires_at', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_user_invitations');
    }
};
