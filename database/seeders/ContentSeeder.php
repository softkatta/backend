<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\LicenseKey;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\ProductIntegrationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // CATEGORIES
        // ============================================================
        $erpCategory = ProductCategory::firstOrCreate(
            ['slug' => 'education-erp'],
            [
                'name' => 'Education ERP',
                'description' => 'Student management and institutional operations software.',
                'icon' => 'graduation-cap',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $managementCategory = ProductCategory::firstOrCreate(
            ['slug' => 'management'],
            [
                'name' => 'Management Systems',
                'description' => 'Specialized management solutions for various industries.',
                'icon' => 'briefcase',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        // ============================================================
        // PRODUCTS
        // ============================================================
        
        // Study Point ERP
        $studyPointErp = Product::firstOrCreate(
            ['slug' => 'study-point-erp'],
            [
                'category_id' => $erpCategory->id,
                'name' => 'Study Point ERP',
                'description' => 'Complete student information system and academic management for educational institutions.',
                'overview' => 'Manage admissions, attendance, fees, exams, and reports in one platform.',
                'is_active' => true,
                'has_free_trial' => true,
                'trial_days' => 14,
                'sort_order' => 1,
                'meta' => [
                    'tagline' => 'All-in-one platform for educational institutions',
                    'current_version' => '3.5.2',
                    'installer_slug' => 'study-point',
                ],
            ],
        );

        // Coaching ERP
        $coachingErp = Product::firstOrCreate(
            ['slug' => 'coaching-erp'],
            [
                'category_id' => $erpCategory->id,
                'name' => 'Coaching ERP',
                'description' => 'Student batches, faculty assignments, doubt sessions, and performance tracking.',
                'overview' => 'Designed specifically for coaching centers and online tutoring platforms.',
                'is_active' => true,
                'has_free_trial' => true,
                'trial_days' => 14,
                'sort_order' => 2,
                'meta' => [
                    'tagline' => 'Coaching center management made easy',
                    'current_version' => '2.8.1',
                ],
            ],
        );

        // Library Management System
        $libraryMgmt = Product::firstOrCreate(
            ['slug' => 'library-management-system'],
            [
                'category_id' => $managementCategory->id,
                'name' => 'Library Management System',
                'description' => 'Book catalog, member management, issue/return tracking, and fine management.',
                'overview' => 'Digital solution for library and media center operations.',
                'is_active' => true,
                'has_free_trial' => true,
                'trial_days' => 30,
                'sort_order' => 3,
                'meta' => [
                    'tagline' => 'Modernize your library operations',
                    'current_version' => '1.9.5',
                ],
            ],
        );

        // Gym Management System
        $gymMgmt = Product::firstOrCreate(
            ['slug' => 'gym-management-system'],
            [
                'category_id' => $managementCategory->id,
                'name' => 'Gym Management System',
                'description' => 'Member profiles, memberships, attendance, trainer assignments, and billing.',
                'overview' => 'Complete solution for fitness centers and gyms.',
                'is_active' => true,
                'has_free_trial' => true,
                'trial_days' => 14,
                'sort_order' => 4,
                'meta' => [
                    'tagline' => 'Gym management simplified',
                    'current_version' => '2.1.0',
                ],
            ],
        );

        // ============================================================
        // PLANS FOR STUDY POINT ERP
        // ============================================================
        $this->createPlansForStudyPoint($studyPointErp);

        // ============================================================
        // PLANS FOR COACHING ERP
        // ============================================================
        $this->createPlansForCoachingErp($coachingErp);

        // ============================================================
        // PLANS FOR LIBRARY MANAGEMENT
        // ============================================================
        $this->createPlansForLibrary($libraryMgmt);

        // ============================================================
        // PLANS FOR GYM MANAGEMENT
        // ============================================================
        $this->createPlansForGym($gymMgmt);

        // ============================================================
        // SAMPLE CUSTOMERS WITH SUBSCRIPTIONS & LICENSE KEYS
        // ============================================================
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

    private function createPlansForStudyPoint(Product $product): void
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
                'limits' => [
                    'max_branches' => 1,
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
                'limits' => [
                    'max_branches' => 3,
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
                'limits' => [
                    'max_branches' => 10,
                    'max_staff' => 500,
                    'max_students' => 10000,
                    'max_storage' => 500,
                    'enabled_modules' => ['students', 'attendance', 'fees', 'batches', 'examinations', 'library', 'reports'],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['product_id' => $product->id, 'slug' => $plan['slug']],
                $plan,
            );
        }

        // Yearly plans at 20% discount
        $yearly = [
            'study-point-basic-yearly' => ['name' => 'Basic (Yearly)', 'price' => 2999 * 12, 'discount' => 0.20],
            'study-point-pro-yearly' => ['name' => 'Professional (Yearly)', 'price' => 5999 * 12, 'discount' => 0.20],
            'study-point-enterprise-yearly' => ['name' => 'Enterprise (Yearly)', 'price' => 12999 * 12, 'discount' => 0.20],
        ];

        foreach ($yearly as $slug => $yearly_plan) {
            Plan::firstOrCreate(
                ['product_id' => $product->id, 'slug' => $slug],
                [
                    'name' => $yearly_plan['name'],
                    'description' => 'Get 2 months free',
                    'price' => $yearly_plan['price'],
                    'discount' => $yearly_plan['discount'] * 100,
                    'gst_rate' => 18,
                    'currency' => 'INR',
                    'trial_days' => 14,
                    'billing_cycle' => 'yearly',
                    'is_popular' => false,
                    'features' => [],
                    'limits' => [],
                ],
            );
        }
    }

    private function createPlansForCoachingErp(Product $product): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'coaching-starter',
                'description' => 'Single center coaching institutes',
                'price' => 1999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => true,
                'limits' => [
                    'max_branches' => 1,
                    'max_staff' => 15,
                    'max_students' => 300,
                    'max_storage' => 5,
                    'enabled_modules' => ['students', 'batches', 'attendance', 'fees'],
                ],
            ],
            [
                'name' => 'Growth',
                'slug' => 'coaching-growth',
                'description' => 'Multi-center coaching chains',
                'price' => 4999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => false,
                'limits' => [
                    'max_branches' => 5,
                    'max_staff' => 100,
                    'max_students' => 2000,
                    'max_storage' => 50,
                    'enabled_modules' => ['students', 'batches', 'attendance', 'fees', 'tests', 'reports'],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['product_id' => $product->id, 'slug' => $plan['slug']],
                $plan,
            );
        }
    }

    private function createPlansForLibrary(Product $product): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'library-basic',
                'description' => 'Small libraries',
                'price' => 999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 30,
                'billing_cycle' => 'monthly',
                'is_popular' => false,
                'limits' => [
                    'max_branches' => 1,
                    'max_staff' => 2,
                    'max_students' => 500,
                    'max_storage' => 5,
                    'enabled_modules' => ['books', 'members', 'issue_return'],
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'library-pro',
                'description' => 'Institutional libraries',
                'price' => 2499,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 30,
                'billing_cycle' => 'monthly',
                'is_popular' => true,
                'limits' => [
                    'max_branches' => 3,
                    'max_staff' => 10,
                    'max_students' => 5000,
                    'max_storage' => 50,
                    'enabled_modules' => ['books', 'members', 'issue_return', 'fines', 'reports'],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['product_id' => $product->id, 'slug' => $plan['slug']],
                $plan,
            );
        }
    }

    private function createPlansForGym(Product $product): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'gym-starter',
                'description' => 'Single location gyms',
                'price' => 1499,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => true,
                'limits' => [
                    'max_branches' => 1,
                    'max_staff' => 10,
                    'max_students' => 200,
                    'max_storage' => 5,
                    'enabled_modules' => ['members', 'memberships', 'attendance', 'fees'],
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'gym-pro',
                'description' => 'Multi-gym chains',
                'price' => 3999,
                'discount' => 0,
                'gst_rate' => 18,
                'currency' => 'INR',
                'trial_days' => 14,
                'billing_cycle' => 'monthly',
                'is_popular' => false,
                'limits' => [
                    'max_branches' => 5,
                    'max_staff' => 50,
                    'max_students' => 1000,
                    'max_storage' => 50,
                    'enabled_modules' => ['members', 'memberships', 'attendance', 'fees', 'trainers', 'reports'],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['product_id' => $product->id, 'slug' => $plan['slug']],
                $plan,
            );
        }
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
                'product_slug' => 'study-point-erp',
            ],
            [
                'name' => 'Priya Kumari',
                'email' => 'priya@smartcoaching.com',
                'company_name' => 'Smart Coaching Institute',
                'phone' => '9876543211',
                'product_slug' => 'coaching-erp',
            ],
            [
                'name' => 'Amit Singh',
                'email' => 'amit@citylibrary.com',
                'company_name' => 'City Library',
                'phone' => '9876543212',
                'product_slug' => 'library-management-system',
            ],
            [
                'name' => 'Neha Patel',
                'email' => 'neha@fitzone.com',
                'company_name' => 'FitZone Gym',
                'phone' => '9876543213',
                'product_slug' => 'gym-management-system',
            ],
        ];

        foreach ($customers as $customerData) {
            $user = User::firstOrCreate(
                ['email' => $customerData['email']],
                [
                    'name' => $customerData['name'],
                    'password' => Hash::make('Password@123'),
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
