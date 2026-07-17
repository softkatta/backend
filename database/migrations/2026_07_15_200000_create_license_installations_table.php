<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_key_id')->constrained('license_keys')->cascadeOnDelete();
            $table->uuid('installation_id')->unique();
            $table->string('domain');
            $table->string('server_fingerprint')->nullable();
            $table->string('install_token_hash');
            $table->string('refresh_token_hash');
            $table->timestamp('install_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('product_version')->nullable();
            $table->string('registered_ip', 45)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['license_key_id', 'domain']);
            $table->index('install_token_hash');
            $table->index('refresh_token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_installations');
    }
};
