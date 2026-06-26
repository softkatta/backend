<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_email_enabled');
            $table->timestamp('security_setup_completed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('security_setup_skipped_at')->nullable()->after('security_setup_completed_at');
        });

        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_name');
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_token', 64)->unique();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_recovery_codes',
                'security_setup_completed_at',
                'security_setup_skipped_at',
            ]);
        });
    }
};
