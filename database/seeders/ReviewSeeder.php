<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::query()->where('is_active', true)->orderBy('sort_order')->first();
        $service = Service::query()->where('is_active', true)->orderBy('sort_order')->first();

        $samples = [
            [
                'full_name' => 'Rahul Patil',
                'company_name' => 'Nanded Digital Hub',
                'email' => 'rahul.reviews@example.com',
                'mobile' => '9876500001',
                'city' => 'Nanded',
                'country' => 'India',
                'rating' => 5,
                'title' => 'Reliable ERP for our coaching institute',
                'description' => 'SoftKatta helped us digitize admissions, fees, and attendance. Support is responsive and the product feels built for Indian SMEs.',
                'is_featured' => true,
                'is_verified' => true,
                'type' => 'product',
            ],
            [
                'full_name' => 'Sneha Deshmukh',
                'company_name' => 'Deshmukh Traders',
                'email' => 'sneha.reviews@example.com',
                'mobile' => '9876500002',
                'city' => 'Pune',
                'country' => 'India',
                'rating' => 5,
                'title' => 'Professional custom software delivery',
                'description' => 'Their team understood our workflow and delivered a clean web app on time. Highly recommend for service engagements.',
                'is_featured' => true,
                'is_verified' => true,
                'type' => 'service',
            ],
            [
                'full_name' => 'Amit Kulkarni',
                'company_name' => null,
                'email' => 'amit.reviews@example.com',
                'mobile' => '9876500003',
                'city' => 'Aurangabad',
                'country' => 'India',
                'rating' => 4,
                'title' => 'Solid product with clear pricing',
                'description' => 'Setup was smooth and the dashboard is easy for our staff. Looking forward to more integrations.',
                'is_featured' => false,
                'is_verified' => false,
                'type' => 'product',
            ],
        ];

        foreach ($samples as $sample) {
            $type = $sample['type'];
            unset($sample['type']);

            if ($type === 'product' && ! $product) {
                continue;
            }
            if ($type === 'service' && ! $service) {
                continue;
            }

            Review::firstOrCreate(
                ['email' => $sample['email']],
                [
                    'uuid' => (string) Str::uuid(),
                    'review_type' => $type,
                    'product_id' => $type === 'product' ? $product->id : null,
                    'service_id' => $type === 'service' ? $service->id : null,
                    ...$sample,
                    'would_recommend' => true,
                    'consent_at' => now(),
                    'status' => Review::STATUS_APPROVED,
                    'approved_at' => now(),
                ]
            );
        }
    }
}
