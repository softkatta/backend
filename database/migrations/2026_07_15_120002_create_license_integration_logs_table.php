<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_api_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('license_key_id')->nullable()->constrained('license_keys')->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 10)->default('POST');
            $table->string('domain')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('product_slug')->nullable();
            $table->boolean('success')->default(false);
            $table->string('error_code')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['license_key_id', 'created_at']);
            $table->index('domain');
            $table->index(['success', 'created_at']);
        });

        Schema::create('license_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('license_key_id')->constrained('license_keys')->cascadeOnDelete();
            $table->string('event');
            $table->json('meta')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['license_key_id', 'created_at']);
        });

        Schema::create('license_domain_reset_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('license_key_id')->constrained('license_keys')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_domain_reset_requests');
        Schema::dropIfExists('license_histories');
        Schema::dropIfExists('license_api_logs');
    }
};
