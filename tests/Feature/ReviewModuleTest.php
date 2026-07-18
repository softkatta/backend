<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for feature tests.');
        }

        parent::setUp();
    }

    public function test_public_can_submit_product_review(): void
    {
        $product = Product::query()->create([
            'name' => 'Demo ERP',
            'slug' => 'demo-erp',
            'description' => 'Demo product',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->postJson('/api/v1/reviews', [
            'review_type' => 'product',
            'product_id' => $product->id,
            'full_name' => 'Test User',
            'email' => 'reviewer@example.com',
            'mobile' => '9876543210',
            'city' => 'Nanded',
            'country' => 'India',
            'rating' => 5,
            'title' => 'Great software product',
            'description' => 'This is a detailed review describing our experience with the product over several months.',
            'would_recommend' => true,
            'consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reviews', [
            'email' => 'reviewer@example.com',
            'product_id' => $product->id,
            'status' => 'pending',
        ]);
    }

    public function test_pending_review_is_hidden_from_public_list(): void
    {
        $product = Product::query()->create([
            'name' => 'Demo ERP',
            'slug' => 'demo-erp',
            'description' => 'Demo product',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Review::factory()->forProduct($product->id)->create([
            'status' => Review::STATUS_PENDING,
            'email' => 'pending@example.com',
        ]);

        $this->getJson('/api/v1/reviews')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_admin_approve_makes_review_public(): void
    {
        $product = Product::query()->create([
            'name' => 'Demo ERP',
            'slug' => 'demo-erp',
            'description' => 'Demo product',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $review = Review::factory()->forProduct($product->id)->create([
            'status' => Review::STATUS_PENDING,
            'email' => 'approve@example.com',
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/reviews/{$review->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->getJson('/api/v1/reviews')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->getJson("/api/v1/products/{$product->slug}/reviews")
            ->assertOk()
            ->assertJsonPath('data.stats.approved', 1);
    }

    public function test_duplicate_email_for_same_product_is_rejected(): void
    {
        $product = Product::query()->create([
            'name' => 'Demo ERP',
            'slug' => 'demo-erp',
            'description' => 'Demo product',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Review::factory()->forProduct($product->id)->approved()->create([
            'email' => 'dup@example.com',
            'mobile' => '9000000001',
        ]);

        $this->postJson('/api/v1/reviews', [
            'review_type' => 'product',
            'product_id' => $product->id,
            'full_name' => 'Another User',
            'email' => 'dup@example.com',
            'mobile' => '9000000002',
            'rating' => 4,
            'title' => 'Second attempt review title',
            'description' => 'Trying to submit another review with the same email for the same product.',
            'would_recommend' => true,
            'consent' => true,
        ])->assertStatus(422);
    }
}
