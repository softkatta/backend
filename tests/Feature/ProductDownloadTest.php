<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitled_customer_can_download_android_and_windows_releases(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('releases/android/app.apk', 'apk-content');
        Storage::disk('public')->put('releases/windows/setup.exe', 'exe-content');
        $product = Product::create([
            'name' => 'Gold Store', 'slug' => 'gold-store', 'is_active' => true,
            'meta' => ['releases' => [
                'android' => ['version' => '1.2.0', 'file_path' => 'releases/android/app.apk', 'file_name' => 'Gold-Store.apk'],
                'windows' => ['version' => '1.2.0', 'file_path' => 'releases/windows/setup.exe', 'file_name' => 'Gold-Store-Setup.exe'],
            ]],
        ]);
        $user = User::factory()->create(['role' => UserRole::Client, 'is_active' => true]);
        $tenant = Tenant::create(['name' => 'Download Store', 'slug' => 'download-store', 'status' => 'active', 'owner_id' => $user->id]);
        $user->update(['tenant_id' => $tenant->id]);
        Subscription::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'product_id' => $product->id, 'status' => SubscriptionStatus::Active, 'starts_at' => now(), 'ends_at' => now()->addMonth()]);

        $this->actingAs($user)->get('/api/v1/client/products/gold-store/downloads/android')->assertOk()->assertDownload('Gold-Store.apk');
        $this->actingAs($user)->get('/api/v1/client/products/gold-store/downloads/windows')->assertOk()->assertDownload('Gold-Store-Setup.exe');
    }

    public function test_customer_without_entitlement_cannot_download_release(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('releases/windows/setup.exe', 'exe-content');
        Product::create(['name' => 'Gold Store', 'slug' => 'gold-store', 'is_active' => true, 'meta' => ['releases' => ['windows' => ['file_path' => 'releases/windows/setup.exe']]]]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Client, 'is_active' => true]))->getJson('/api/v1/client/products/gold-store/downloads/windows')
            ->assertForbidden()->assertJsonPath('message', 'An active product entitlement is required to download this app.');
    }
}