<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('license_key', 64)->unique();
            $table->json('allowed_domains')->nullable()->comment('List of domains allowed to use this license');
            $table->unsignedSmallInteger('max_devices')->default(1);
            $table->string('status', 20)->default('active')->comment('active, suspended, expired, revoked');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('null = lifetime');
            $table->unsignedInteger('activation_count')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
            $table->index('user_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_keys');
    }
};
