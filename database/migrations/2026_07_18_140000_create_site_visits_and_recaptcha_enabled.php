<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 500)->index();
            $table->string('ip_hash', 64)->index();
            $table->string('session_key', 64)->nullable()->index();
            $table->date('visited_on')->index();
            $table->timestamps();

            $table->unique(['ip_hash', 'path', 'visited_on'], 'site_visits_unique_daily');
        });

        Setting::firstOrCreate(
            ['key' => 'recaptcha_enabled'],
            ['value' => 'false', 'group' => 'security'],
        );

        Setting::firstOrCreate(
            ['key' => 'recaptcha_site_key'],
            ['value' => '', 'group' => 'security'],
        );

        Setting::firstOrCreate(
            ['key' => 'recaptcha_secret_key'],
            ['value' => '', 'group' => 'security'],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
        Setting::query()->where('key', 'recaptcha_enabled')->delete();
    }
};
