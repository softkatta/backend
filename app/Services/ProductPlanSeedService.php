<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Product;

class ProductPlanSeedService
{
    public function seedCanonicalPlans(): void
    {
        $map = [
            'study-point-management-software' => fn (Product $product) => $this->seedStudyPointPlans($product),
            'medical-store-management-software' => fn (Product $product) => $this->seedMedicalStorePlans($product),
            'nursery-school-management-software' => fn (Product $product) => $this->seedNurserySchoolPlans($product),
        ];

        foreach ($map as $slug => $seedPlans) {
            $product = Product::query()->where('slug', $slug)->first();
            if ($product) {
                $seedPlans($product);
            }
        }
    }

    private function seedStudyPointPlans(Product $product): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'study-point-basic',
                'description' => 'For small schools and institutes',
                'price' => 2999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => false,
                'sort_order' => 1,
                'limits' => [
                    'max_branches' => 1,
                    'max_users' => 20,
                    'max_staff' => 20,
                    'max_students' => 500,
                    'max_storage' => 10,
                    'enabled_modules' => ['students', 'attendance', 'fees'],
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'study-point-pro',
                'description' => 'For medium-sized schools',
                'price' => 5999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => true,
                'sort_order' => 2,
                'limits' => [
                    'max_branches' => 3,
                    'max_users' => 100,
                    'max_staff' => 100,
                    'max_students' => 2000,
                    'max_storage' => 50,
                    'enabled_modules' => ['students', 'attendance', 'fees', 'batches', 'examinations'],
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'study-point-enterprise',
                'description' => 'For large institutions',
                'price' => 12999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => false,
                'sort_order' => 3,
                'limits' => [
                    'max_branches' => 10,
                    'max_users' => 500,
                    'max_staff' => 500,
                    'max_students' => 10000,
                    'max_storage' => 500,
                    'enabled_modules' => ['students', 'attendance', 'fees', 'batches', 'examinations', 'library', 'reports'],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['product_id' => $product->id, 'slug' => $plan['slug']],
                array_merge($plan, ['is_active' => true]),
            );
        }

        $yearly = [
            'study-point-basic-yearly' => ['name' => 'Basic (Yearly)', 'price' => 2999 * 12, 'discount' => 0.20, 'sort_order' => 4],
            'study-point-pro-yearly' => ['name' => 'Professional (Yearly)', 'price' => 5999 * 12, 'discount' => 0.20, 'sort_order' => 5],
            'study-point-enterprise-yearly' => ['name' => 'Enterprise (Yearly)', 'price' => 12999 * 12, 'discount' => 0.20, 'sort_order' => 6],
        ];

        foreach ($yearly as $slug => $yearlyPlan) {
            Plan::updateOrCreate(
                ['product_id' => $product->id, 'slug' => $slug],
                [
                    'name' => $yearlyPlan['name'],
                    'description' => 'Get 2 months free',
                    'price' => $yearlyPlan['price'],
                    'discount' => $yearlyPlan['discount'] * 100,
                    'gst_rate' => 18,
                    'currency' => 'INR',
                    'trial_days' => 14,
                    'billing_cycle' => 'yearly',
                    'is_popular' => false,
                    'is_active' => true,
                    'sort_order' => $yearlyPlan['sort_order'],
                    'features' => [],
                    'limits' => [],
                ],
            );
        }
    }

    private function seedMedicalStorePlans(Product $product): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'medical-store-starter',
                'description' => 'Single-store pharmacy billing and inventory',
                'price' => 1999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => true,
                'sort_order' => 1,
                'limits' => [
                    'max_branches' => 1,
                    'max_users' => 5,
                    'max_staff' => 5,
                    'max_storage' => 10,
                    'enabled_modules' => ['billing', 'inventory', 'gst'],
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'medical-store-pro',
                'description' => 'Multi-counter pharmacy operations',
                'price' => 4999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => false,
                'sort_order' => 2,
                'limits' => [
                    'max_branches' => 3,
                    'max_users' => 20,
                    'max_staff' => 20,
                    'max_storage' => 50,
                    'enabled_modules' => ['billing', 'inventory', 'gst', 'purchase', 'reports'],
                ],
            ],
            [
                'name' => 'Starter (Yearly)',
                'slug' => 'medical-store-starter-yearly',
                'description' => 'Annual billing with savings',
                'price' => 1999 * 12,
                'discount' => 20,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'yearly',
                'is_popular' => false,
                'sort_order' => 3,
                'limits' => [],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['product_id' => $product->id, 'slug' => $plan['slug']],
                array_merge($plan, ['is_active' => true]),
            );
        }
    }

    private function seedNurserySchoolPlans(Product $product): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'nursery-school-basic',
                'description' => 'For small nursery schools',
                'price' => 1499,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => false,
                'sort_order' => 1,
                'limits' => [
                    'max_branches' => 1,
                    'max_users' => 10,
                    'max_staff' => 10,
                    'max_students' => 200,
                    'max_storage' => 5,
                    'enabled_modules' => ['admissions', 'attendance', 'fees'],
                ],
            ],
            [
                'name' => 'Standard',
                'slug' => 'nursery-school-standard',
                'description' => 'For growing nursery schools',
                'price' => 2999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => true,
                'sort_order' => 2,
                'limits' => [
                    'max_branches' => 2,
                    'max_users' => 25,
                    'max_staff' => 25,
                    'max_students' => 500,
                    'max_storage' => 20,
                    'enabled_modules' => ['admissions', 'attendance', 'fees', 'parent_portal', 'notifications'],
                ],
            ],
            [
                'name' => 'Standard (Yearly)',
                'slug' => 'nursery-school-standard-yearly',
                'description' => 'Annual billing with savings',
                'price' => 2999 * 12,
                'discount' => 20,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'yearly',
                'is_popular' => false,
                'sort_order' => 3,
                'limits' => [],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['product_id' => $product->id, 'slug' => $plan['slug']],
                array_merge($plan, ['is_active' => true]),
            );
        }
    }
}
