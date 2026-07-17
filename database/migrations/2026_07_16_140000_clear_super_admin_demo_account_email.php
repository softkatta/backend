<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $adminEmail = strtolower(trim((string) env('SUPER_ADMIN_EMAIL', 'admin@softkatta.com')));

        if ($adminEmail === '') {
            return;
        }

        Setting::query()
            ->where('key', 'demo_account_email')
            ->whereRaw('LOWER(value) = ?', [$adminEmail])
            ->update(['value' => '']);
    }

    public function down(): void
    {
        // No rollback — misconfiguration should not be restored.
    }
};
