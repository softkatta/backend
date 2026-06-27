<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('backend_domain')->nullable()->unique()->after('domain');
            $table->string('frontend_domain')->nullable()->unique()->after('backend_domain');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropUnique(['backend_domain']);
            $table->dropUnique(['frontend_domain']);
            $table->dropColumn(['backend_domain', 'frontend_domain']);
        });
    }
};
