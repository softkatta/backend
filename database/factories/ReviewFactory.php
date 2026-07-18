<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'review_type' => Review::TYPE_PRODUCT,
            'product_id' => null,
            'service_id' => null,
            'full_name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => fake()->numerify('9#########'),
            'city' => fake()->city(),
            'country' => 'India',
            'rating' => fake()->numberBetween(4, 5),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(3),
            'would_recommend' => true,
            'consent_at' => now(),
            'status' => Review::STATUS_PENDING,
            'is_featured' => false,
            'is_verified' => false,
            'helpful_count' => 0,
            'report_count' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Review::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => [
            'status' => Review::STATUS_APPROVED,
            'is_featured' => true,
            'approved_at' => now(),
        ]);
    }

    public function forProduct(int $productId): static
    {
        return $this->state(fn () => [
            'review_type' => Review::TYPE_PRODUCT,
            'product_id' => $productId,
            'service_id' => null,
        ]);
    }

    public function forService(int $serviceId): static
    {
        return $this->state(fn () => [
            'review_type' => Review::TYPE_SERVICE,
            'product_id' => null,
            'service_id' => $serviceId,
        ]);
    }
}
