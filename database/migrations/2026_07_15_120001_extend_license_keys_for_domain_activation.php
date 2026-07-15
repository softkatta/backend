<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_keys', function (Blueprint $table): void {
            $table->string('registered_ip', 45)->nullable()->after('allowed_domains');
            $table->string('product_version', 32)->nullable()->after('registered_ip');
            $table->unsignedSmallInteger('max_domains')->default(1)->after('max_devices');
            $table->boolean('is_product_active')->default(true)->after('status');
            $table->timestamp('deactivated_at')->nullable()->after('activated_at');
            $table->timestamp('force_logout_at')->nullable()->after('last_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('license_keys', function (Blueprint $table): void {
            $table->dropColumn([
                'registered_ip',
                'product_version',
                'max_domains',
                'is_product_active',
                'deactivated_at',
                'force_logout_at',
            ]);
        });
    }
};
