<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_menus', function (Blueprint $table) {
            $table->id();
            $table->string('portal', 50)->default('employee');
            $table->string('key', 80);
            $table->string('label');
            $table->string('route');
            $table->string('icon', 80)->nullable();
            $table->string('parent_key', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('permission')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('badge_enabled')->default(false);
            $table->timestamps();

            $table->unique(['portal', 'key']);
            $table->index(['portal', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_menus');
    }
};
