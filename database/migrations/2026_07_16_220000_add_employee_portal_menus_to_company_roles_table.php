<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_roles', function (Blueprint $table): void {
            $table->json('employee_portal_menus')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('company_roles', function (Blueprint $table): void {
            $table->dropColumn('employee_portal_menus');
        });
    }
};
