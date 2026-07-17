<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_role_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_role_id')->constrained('company_roles')->cascadeOnDelete();
            $table->foreignId('portal_menu_id')->constrained('portal_menus')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_role_id', 'portal_menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_role_menus');
    }
};
