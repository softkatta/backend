<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('review_type', 20);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('full_name');
            $table->string('company_name')->nullable();
            $table->string('email');
            $table->string('mobile', 30);
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title');
            $table->text('description');
            $table->string('profile_image')->nullable();
            $table->string('screenshot')->nullable();
            $table->boolean('would_recommend')->default(true);
            $table->timestamp('consent_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('report_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('rating');
            $table->index('status');
            $table->index('email');
            $table->index('mobile');
            $table->index(['review_type', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['service_id', 'status']);
            $table->index(['is_featured', 'status']);
        });

        foreach ([
            ['key' => 'recaptcha_site_key', 'value' => '', 'group' => 'security'],
            ['key' => 'recaptcha_secret_key', 'value' => '', 'group' => 'security'],
        ] as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');

        Setting::query()
            ->whereIn('key', ['recaptcha_site_key', 'recaptcha_secret_key'])
            ->delete();
    }
};
