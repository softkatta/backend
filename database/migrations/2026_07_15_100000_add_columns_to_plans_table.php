<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->decimal('discount', 10, 2)->default(0)->after('price');
            $table->decimal('gst_rate', 5, 2)->default(0)->after('discount');
            $table->string('currency', 3)->default('INR')->after('gst_rate');
            $table->unsignedInteger('trial_days')->default(0)->after('currency');
            $table->json('limits')->nullable()->after('features')
                ->comment('JSON: max_branches, max_staff, max_students, max_storage, enabled_modules, api_access, whatsapp_integration, sms_integration, email_integration, biometric_integration, custom_domain, white_label, backup, addon_support');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['discount', 'gst_rate', 'currency', 'trial_days', 'limits']);
        });
    }
};
