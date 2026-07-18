<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\LicenseKey;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\ProductIntegrationService;
use App\Services\ProductPlanSeedService;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlansForCanonicalProducts();
        $this->createSampleCustomersWithSubscriptions();

        // ============================================================
        // SERVICES
        // ============================================================
        Service::firstOrCreate(
            ['slug' => 'implementation'],
            [
                'name' => 'Implementation Support',
                'description' => 'Expert setup, data migration, and team training.',
                'icon' => 'wrench',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Service::firstOrCreate(
            ['slug' => 'custom-integration'],
            [
                'name' => 'Custom Integrations',
                'description' => 'Connect existing tools and workflows with our platform.',
                'icon' => 'plug',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        Service::firstOrCreate(
            ['slug' => 'training'],
            [
                'name' => 'Staff Training',
                'description' => 'Hands-on training for your team to maximize software usage.',
                'icon' => 'book-open',
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        // ============================================================
        // HERO SLIDES
        // ============================================================
        HeroSlide::updateOrCreate(
            ['sort_order' => 1],
            [
                'title' => 'Run Your Institution on One Platform',
                'image' => 'https://picsum.photos/seed/softkatta-hero-1/1600/1000',
                'alt_text' => 'SoftKatta dashboard',
                'is_active' => true,
            ],
        );

        HeroSlide::updateOrCreate(
            ['sort_order' => 2],
            [
                'title' => 'Student Management Made Simple',
                'image' => 'https://picsum.photos/seed/softkatta-hero-2/1600/1000',
                'alt_text' => 'Student information system',
                'is_active' => true,
            ],
        );

        HeroSlide::updateOrCreate(
            ['sort_order' => 3],
            [
                'title' => 'Automate Billing & Reports',
                'image' => 'https://picsum.photos/seed/softkatta-hero-3/1600/1000',
                'alt_text' => 'Invoices and analytics',
                'is_active' => true,
            ],
        );

        // ============================================================
        // TESTIMONIALS
        // ============================================================
        Testimonial::firstOrCreate(
            ['name' => 'Dr. Rajesh Sharma', 'company' => 'Delhi Public School'],
            [
                'designation' => 'Principal',
                'content' => 'Study Point ERP transformed how we manage student records, attendance, and examinations. Highly recommended!',
                'rating' => 5,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Testimonial::firstOrCreate(
            ['name' => 'Priya Kumari', 'company' => 'Smart Coaching Institute'],
            [
                'designation' => 'Owner',
                'content' => 'The Coaching ERP helped us organize batches and track student progress efficiently.',
                'rating' => 5,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        Testimonial::firstOrCreate(
            ['name' => 'Amit Singh', 'company' => 'City Library'],
            [
                'designation' => 'Librarian',
                'content' => 'Library Management System makes member and book tracking effortless.',
                'rating' => 4,
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        Testimonial::firstOrCreate(
            ['name' => 'Neha Patel', 'company' => 'FitZone Gym'],
            [
                'designation' => 'Manager',
                'content' => 'Member management and billing is now automated. Great product!',
                'rating' => 5,
                'is_active' => true,
                'sort_order' => 4,
            ],
        );

        // ============================================================
        // FAQs
        // ============================================================
        Faq::firstOrCreate(
            ['question' => 'Can I try before buying?'],
            [
                'category' => 'General',
                'answer' => 'Yes! Most products include a free trial period. No credit card required.',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        Faq::firstOrCreate(
            ['question' => 'What payment methods do you accept?'],
            [
                'category' => 'Billing',
                'answer' => 'We accept credit/debit cards via Razorpay. GST invoices are automatically generated.',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        Faq::firstOrCreate(
            ['question' => 'Can I upgrade my plan anytime?'],
            [
                'category' => 'Plans',
                'answer' => 'Yes, you can upgrade or downgrade your plan anytime. Changes take effect immediately.',
                'sort_order' => 3,
                'is_active' => true,
            ],
        );

        Faq::firstOrCreate(
            ['question' => 'Is data backup included?'],
            [
                'category' => 'Security',
                'answer' => 'Yes, automatic daily backups are included with all paid plans.',
                'sort_order' => 4,
                'is_active' => true,
            ],
        );

        Faq::firstOrCreate(
            ['question' => 'How do I migrate my existing data?'],
            [
                'category' => 'Technical',
                'answer' => 'We provide implementation support to help you migrate data from your old system.',
                'sort_order' => 5,
                'is_active' => true,
            ],
        );
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    private function seedPlansForCanonicalProducts(): void
    {
        app(ProductPlanSeedService::class)->seedCanonicalPlans();
    }

    private function createSampleCustomersWithSubscriptions(): void
    {
        $licenseService = app(LicenseService::class);

        // Sample customers
        $customers = [
            [
                'name' => 'Ramesh Sharma',
                'email' => 'ramesh@delhischool.com',
                'company_name' => 'Delhi Public School',
                'phone' => '9876543210',
                'product_slug' => 'study-point-management-software',
            ],
            [
                'name' => 'Priya Kumari',
                'email' => 'priya@smartpharmacy.com',
                'company_name' => 'Smart Pharmacy',
                'phone' => '9876543211',
                'product_slug' => 'medical-store-management-software',
            ],
            [
                'name' => 'Amit Singh',
                'email' => 'amit@happynursery.com',
                'company_name' => 'Happy Nursery School',
                'phone' => '9876543212',
                'product_slug' => 'nursery-school-management-software',
            ],
        ];

        foreach ($customers as $customerData) {
            $user = User::firstOrCreate(
                ['email' => $customerData['email']],
                [
                    'name' => $customerData['name'],
                    'password' => 'Password@123',
                    'phone' => $customerData['phone'],
                    'company_name' => $customerData['company_name'],
                    'role' => 'client',
                    'is_active' => true,
                ],
            );

            // Create tenant for this customer
            $tenantSlug = str_replace(' ', '-', strtolower($customerData['company_name'])) . '-' . $user->id;
            $tenant = Tenant::firstOrCreate(
                ['owner_id' => $user->id],
                [
                    'name' => $customerData['company_name'],
                    'slug' => $tenantSlug,
                    'database_name' => 'softkatta_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    'status' => 'active',
                ],
            );

            $user->update(['tenant_id' => $tenant->id]);

            // Create subscription
            $product = Product::where('slug', $customerData['product_slug'])->first();
            if ($product) {
                $plan = $product->plans()->first();
                if ($plan) {
                    $subscription = Subscription::firstOrCreate(
                        ['user_id' => $user->id, 'product_id' => $product->id],
                        [
                            'tenant_id' => $tenant->id,
                            'plan_id' => $plan->id,
                            'status' => 'active',
                            'starts_at' => now(),
                            'ends_at' => now()->addMonths(12),
                            'trial_ends_at' => now()->addDays(14),
                            'auto_renew' => true,
                        ],
                    );

                    // Auto-generate license key
                    if (!$subscription->licenseKey) {
                        $licenseService->generateForSubscription($subscription);
                    }
                }
            }
        }

        $integrationService = app(ProductIntegrationService::class);
        foreach (Product::all() as $product) {
            if (! $product->productIntegration) {
                $integrationService->createForProduct($product);
            }
        }
    }
}
