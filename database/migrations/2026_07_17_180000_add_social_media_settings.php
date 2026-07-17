<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'social_facebook', 'value' => '', 'group' => 'general'],
            ['key' => 'social_instagram', 'value' => '', 'group' => 'general'],
            ['key' => 'social_linkedin', 'value' => '', 'group' => 'general'],
            ['key' => 'social_twitter', 'value' => '', 'group' => 'general'],
            ['key' => 'social_youtube', 'value' => '', 'group' => 'general'],
            ['key' => 'social_whatsapp', 'value' => '', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }

    public function down(): void
    {
        Setting::query()
            ->whereIn('key', [
                'social_facebook',
                'social_instagram',
                'social_linkedin',
                'social_twitter',
                'social_youtube',
                'social_whatsapp',
            ])
            ->delete();
    }
};
