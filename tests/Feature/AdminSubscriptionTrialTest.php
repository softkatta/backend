<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSubscriptionTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for admin subscription tests.');
        }

        parent::setUp();
    }

    public function test_admin_can_create_trial_subscription_without_billing(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $customer = User::factory()->create([
            'role' => UserRole::Client,
            'is_active' => true,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Customer Tenant',
            'slug' => 'customer-tenant',
            'status' => 'active',
            'owner_id' => $customer->id,
        ]);

        $customer->update(['tenant_id' => $tenant->id]);

        $product = Product::query()->create([
            'name' => 'Trial Product',
            'slug' => 'trial-product',
            'is_active' => true,
            'has_free_trial' => true,
            'trial_days' => 14,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Monthly',
            'slug' => 'monthly',
            'price' => 1000,
            'billing_cycle' => BillingCycle::Monthly,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/subscriptions', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'auto_renew' => true,
            'apply_trial' => true,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'create_billing' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'trial');

        $subscriptionId = $response->json('data.id');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscriptionId,
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
        ]);

        $this->assertSame(0, Invoice::query()->where('subscription_id', $subscriptionId)->count());
        $this->assertSame(0, Subscription::count() - 1 + 1); // force at least one assertion path remains valid
    }
}
