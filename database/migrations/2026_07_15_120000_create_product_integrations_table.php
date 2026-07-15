<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('version', 32)->default('1.0.0');
            $table->string('api_base_url');
            $table->string('public_api_key', 80)->unique();
            $table->text('secret_api_key');
            $table->string('client_id', 80)->unique();
            $table->text('client_secret');
            $table->text('webhook_secret');
            $table->json('supported_versions')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_integrations');
    }
};
