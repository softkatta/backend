<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'two_factor_login_enabled'],
            ['value' => 'false', 'group' => 'security'],
        );
    }

    public function down(): void
    {
        Setting::query()->where('key', 'two_factor_login_enabled')->delete();
    }
};
