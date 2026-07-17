<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFeature;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProductPlanSeedService;
use App\Services\PublicPageContentService;
use Illuminate\Database\Seeder;

class SeoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCompanySettings();
        $this->seedAboutContent();
        $this->seedPageContent();
        $this->seedServices();
        $this->seedProducts();
        $this->seedFaqs();
        $this->seedBlogs();
        $this->seedHeroAltText();
    }

    private function seedCompanySettings(): void
    {
        $settings = [
            ['key' => 'company_name', 'value' => 'SoftKatta Solutions', 'group' => 'general'],
            ['key' => 'company_tagline', 'value' => 'Custom Software Development Company in Nanded', 'group' => 'general'],
            ['key' => 'company_address', 'value' => 'Talni, Nanded, Maharashtra, India', 'group' => 'general'],
            ['key' => 'company_website', 'value' => 'https://softkatta.in', 'group' => 'general'],
            ['key' => 'company_phone', 'value' => '+91 7038452357', 'group' => 'general'],
            ['key' => 'company_description', 'value' => 'SoftKatta Solutions is a software development company based in Talni, Nanded, Maharashtra. We develop custom software, ERP solutions, web applications, mobile apps, and business management systems for education, healthcare, retail, and enterprises.', 'group' => 'general'],
            ['key' => 'brand_short_name', 'value' => 'SoftKatta', 'group' => 'general'],
            ['key' => 'support_email', 'value' => 'support@softkatta.in', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']],
            );
        }
    }

    private function seedAboutContent(): void
    {
        $values = [
            ['title' => 'Innovation', 'description' => 'Pioneering practical technology solutions for real business challenges.'],
            ['title' => 'Quality', 'description' => 'Delivering reliable, well-tested software that meets professional standards.'],
            ['title' => 'Customer Satisfaction', 'description' => 'Building long-term relationships through responsive support and results.'],
            ['title' => 'Transparency', 'description' => 'Clear communication, honest timelines, and upfront pricing.'],
            ['title' => 'Security', 'description' => 'Protecting your data with secure architecture and best practices.'],
            ['title' => 'Continuous Improvement', 'description' => 'Evolving products and processes to stay ahead.'],
        ];

        $story = <<<'TEXT'
Founded with a vision to make technology accessible for every business, SoftKatta Solutions specializes in developing ERP software, custom business applications, SaaS products, websites, and mobile applications.

We understand that every business has unique requirements. Instead of providing one-size-fits-all software, we build customized solutions tailored to your business processes. Our team focuses on delivering reliable, secure, and future-ready software that improves efficiency and drives growth.

Today, we proudly serve educational institutes, medical stores, businesses, and organizations across India with innovative digital solutions.
TEXT;

        $content = [
            ['key' => 'about_highlight_title', 'value' => 'Our Story', 'group' => 'content'],
            ['key' => 'about_highlight_text', 'value' => 'SoftKatta Solutions is a software development company based in Talni, Nanded, Maharashtra. We help businesses, educational institutions, healthcare organizations, and startups transform their operations through innovative software solutions. Our mission is to simplify business processes with secure, scalable, and user-friendly technology.', 'group' => 'content'],
            ['key' => 'about_hero_label', 'value' => 'About SoftKatta Solutions', 'group' => 'content'],
            ['key' => 'about_hero_title', 'value' => 'Building Smart Software Solutions for', 'group' => 'content'],
            ['key' => 'about_hero_highlight', 'value' => 'Modern Businesses', 'group' => 'content'],
            ['key' => 'about_hero_description', 'value' => 'SoftKatta Solutions is a software development company based in Talni, Nanded, Maharashtra. We help businesses, educational institutions, healthcare organizations, and startups transform their operations through innovative software solutions.', 'group' => 'content'],
            ['key' => 'about_story_text', 'value' => trim($story), 'group' => 'content'],
            ['key' => 'about_mission_text', 'value' => 'To empower businesses through reliable, innovative, and affordable software solutions that simplify operations and accelerate growth.', 'group' => 'content'],
            ['key' => 'about_vision_text', 'value' => "To become one of India's most trusted software development companies by delivering world-class digital solutions that create long-term value for businesses.", 'group' => 'content'],
            ['key' => 'about_values', 'value' => json_encode($values, JSON_UNESCAPED_UNICODE), 'group' => 'content'],
            ['key' => 'about_milestones', 'value' => '[]', 'group' => 'content'],
        ];

        foreach ($content as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']],
            );
        }
    }

    private function seedPageContent(): void
    {
        /** @var PublicPageContentService $pages */
        $pages = app(PublicPageContentService::class);
        $all = $pages->all();

        foreach ($all['pages'] as $slug => $content) {
            Setting::updateOrCreate(
                ['key' => 'page_content_'.$slug],
                ['value' => json_encode($content, JSON_UNESCAPED_UNICODE), 'group' => 'content'],
            );
        }

        Setting::updateOrCreate(
            ['key' => 'public_page_seo'],
            ['value' => json_encode($all['seo'], JSON_UNESCAPED_UNICODE), 'group' => 'content'],
        );
    }

    private function seedServices(): void
    {
        $details = [
            'custom-software-development' => [
                'body' => 'Every business has unique processes. We develop tailor-made software solutions that match your exact business requirements. Our custom software improves productivity, reduces manual work, and helps automate daily operations.',
                'bullets_heading' => 'What We Offer',
                'bullets' => ['Business Management Software', 'Inventory Management', 'CRM Development', 'Billing Software', 'HR Management Systems', 'Automation Solutions'],
                'meta_title' => 'Custom Software Development | SoftKatta Solutions',
            ],
            'erp-software-development' => [
                'body' => 'We build powerful ERP solutions that connect every department into a single platform, improving collaboration, reporting, and decision-making.',
                'bullets_heading' => 'Suitable For',
                'bullets' => ['Schools & Colleges', 'Coaching Institutes', 'Hospitals', 'Medical Stores', 'Retail Businesses', 'Service Providers'],
                'meta_title' => 'ERP Software Development | SoftKatta Solutions',
            ],
            'website-development' => [
                'body' => 'Your website is your digital identity. We design modern, responsive, SEO-friendly websites that help businesses establish a strong online presence and generate more leads.',
                'bullets_heading' => 'Website Types',
                'bullets' => ['Business Websites', 'Corporate Websites', 'Educational Websites', 'Hospital Websites', 'Landing Pages', 'eCommerce Websites', 'Portfolio Websites'],
                'meta_title' => 'Website Development Company | SoftKatta Solutions',
            ],
            'mobile-app-development' => [
                'body' => 'We develop secure and high-performance Android and cross-platform mobile applications with user-friendly interfaces and modern technologies.',
                'bullets_heading' => 'App Categories',
                'bullets' => ['Business Apps', 'School Apps', 'Hospital Apps', 'Pharmacy Apps', 'Customer Apps', 'Employee Apps'],
                'meta_title' => 'Mobile App Development Services | SoftKatta Solutions',
            ],
            'saas-application-development' => [
                'body' => 'Launch your own cloud-based software with subscription management, user roles, billing, analytics, and multi-tenant architecture.',
                'meta_title' => 'SaaS Application Development | SoftKatta Solutions',
            ],
            'cloud-solutions' => [
                'body' => 'Deploy secure cloud applications that are accessible anytime, anywhere with automatic backups, scalability, and enhanced security.',
                'meta_title' => 'Cloud Solutions | SoftKatta Solutions',
            ],
            'api-integration' => [
                'body' => 'Connect your software with third-party services to automate workflows and improve business efficiency.',
                'bullets_heading' => 'Integrations',
                'bullets' => ['Payment Gateway', 'WhatsApp API', 'SMS Gateway', 'Email Services', 'Google APIs', 'Biometric Devices'],
                'meta_title' => 'API Integration Services | SoftKatta Solutions',
            ],
            'ui-ux-design' => [
                'body' => 'We create intuitive and attractive user interfaces that improve user engagement and deliver an excellent digital experience.',
                'meta_title' => 'UI/UX Design Services | SoftKatta Solutions',
            ],
            'software-maintenance' => [
                'body' => 'Our support team ensures your software remains secure, updated, and optimized with regular maintenance and technical assistance.',
                'meta_title' => 'Software Maintenance & Support | SoftKatta Solutions',
            ],
        ];

        $services = [
            [
                'slug' => 'custom-software-development',
                'name' => 'Custom Software Development',
                'description' => 'Every business has unique processes. We develop tailor-made software solutions that match your exact business requirements and automate daily operations.',
                'icon' => 'code',
                'sort_order' => 1,
            ],
            [
                'slug' => 'erp-software-development',
                'name' => 'ERP Software Development',
                'description' => 'We build powerful ERP solutions that connect every department into a single platform for better collaboration, reporting, and decision-making.',
                'icon' => 'barchart',
                'sort_order' => 2,
            ],
            [
                'slug' => 'website-development',
                'name' => 'Website Development',
                'description' => 'Modern, responsive, SEO-friendly websites that help businesses establish a strong online presence and generate more leads.',
                'icon' => 'palette',
                'sort_order' => 3,
            ],
            [
                'slug' => 'mobile-app-development',
                'name' => 'Mobile App Development',
                'description' => 'Secure, high-performance Android and cross-platform mobile applications with user-friendly interfaces and modern technologies.',
                'icon' => 'rocket',
                'sort_order' => 4,
            ],
            [
                'slug' => 'saas-application-development',
                'name' => 'SaaS Application Development',
                'description' => 'Launch cloud-based software with subscription management, user roles, billing, analytics, and multi-tenant architecture.',
                'icon' => 'cloud',
                'sort_order' => 5,
            ],
            [
                'slug' => 'cloud-solutions',
                'name' => 'Cloud Solutions',
                'description' => 'Deploy secure cloud applications accessible anytime, anywhere with automatic backups, scalability, and enhanced security.',
                'icon' => 'cloud',
                'sort_order' => 6,
            ],
            [
                'slug' => 'api-integration',
                'name' => 'API Integration Services',
                'description' => 'Connect your software with payment gateways, WhatsApp API, SMS, email, Google APIs, and third-party business applications.',
                'icon' => 'lightbulb',
                'sort_order' => 7,
            ],
            [
                'slug' => 'ui-ux-design',
                'name' => 'UI/UX Design',
                'description' => 'Intuitive and attractive user interfaces that improve engagement and deliver an excellent digital experience.',
                'icon' => 'palette',
                'sort_order' => 8,
            ],
            [
                'slug' => 'software-maintenance',
                'name' => 'Software Maintenance & Support',
                'description' => 'Ongoing maintenance, performance optimization, bug fixes, feature enhancements, and dedicated technical support.',
                'icon' => 'shield',
                'sort_order' => 9,
            ],
        ];

        foreach ($services as $service) {
            $extra = $details[$service['slug']] ?? [];
            Service::updateOrCreate(
                ['slug' => $service['slug']],
                array_merge($service, $extra, [
                    'is_active' => true,
                ]),
            );
        }

        Service::query()
            ->whereIn('slug', ['implementation', 'custom-integration', 'training'])
            ->update(['is_active' => false]);
    }

    private function seedProducts(): void
    {
        $education = ProductCategory::firstOrCreate(
            ['slug' => 'education-erp'],
            [
                'name' => 'Education ERP',
                'description' => 'Student management and institutional operations software.',
                'icon' => 'graduation-cap',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $healthcare = ProductCategory::firstOrCreate(
            ['slug' => 'healthcare-erp'],
            [
                'name' => 'Healthcare ERP',
                'description' => 'Healthcare and pharmacy management software.',
                'icon' => 'heart-pulse',
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        $products = [
            [
                'slug' => 'study-point-management-software',
                'name' => 'Study Point Management Software',
                'description' => 'Manage admissions, students, attendance, batches, fee collection, examinations, results, staff, and reports through one integrated platform.',
                'overview' => 'Complete coaching and study center management with admissions, fees, attendance, and analytics.',
                'category_id' => $education->id,
                'sort_order' => 1,
                'features' => [
                    'Student Management',
                    'Attendance',
                    'Fee Collection',
                    'Batch Management',
                    'Online Payments',
                    'WhatsApp Notifications',
                    'Reports & Analytics',
                ],
            ],
            [
                'slug' => 'medical-store-management-software',
                'name' => 'Medical Store Management Software',
                'description' => 'Complete pharmacy software with billing, inventory management, GST invoices, purchase management, supplier records, and expiry tracking.',
                'overview' => 'Pharmacy billing, inventory, GST compliance, and stock management in one system.',
                'category_id' => $healthcare->id,
                'sort_order' => 2,
                'features' => [
                    'GST Billing',
                    'Barcode Support',
                    'Inventory Management',
                    'Stock Alerts',
                    'Purchase & Sales',
                    'Reports',
                ],
            ],
            [
                'slug' => 'nursery-school-management-software',
                'name' => 'Nursery School Management Software',
                'description' => 'A complete ERP for nursery schools including admissions, attendance, fee management, communication, and parent engagement.',
                'overview' => 'Admissions, fees, parent portal, and school communication for nursery schools.',
                'category_id' => $education->id,
                'sort_order' => 3,
                'features' => [
                    'Student Admission',
                    'Attendance',
                    'Fees',
                    'Parent Portal',
                    'Notifications',
                    'Reports',
                ],
            ],
        ];

        $activeSlugs = collect($products)->pluck('slug')->all();

        foreach ($products as $item) {
            $features = $item['features'];
            unset($item['features']);

            $product = Product::updateOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, [
                    'is_active' => $item['is_active'] ?? true,
                    'has_free_trial' => true,
                    'trial_days' => 14,
                    'meta' => ['tagline' => $item['name']],
                ]),
            );

            if ($features === []) {
                continue;
            }

            foreach ($features as $index => $title) {
                ProductFeature::updateOrCreate(
                    ['product_id' => $product->id, 'title' => $title],
                    ['description' => $title, 'sort_order' => $index + 1],
                );
            }
        }

        Product::query()
            ->whereNotIn('slug', $activeSlugs)
            ->each(fn (Product $product) => $product->delete());

        app(ProductPlanSeedService::class)->seedCanonicalPlans();
    }

    private function seedFaqs(): void
    {
        $faqs = [
            [
                'question' => 'What services does SoftKatta Solutions provide?',
                'category' => 'Services',
                'answer' => 'We offer custom software development, ERP solutions, website development, mobile app development, cloud solutions, API integration, and software maintenance services.',
                'sort_order' => 1,
            ],
            [
                'question' => 'Do you develop customized software?',
                'category' => 'Services',
                'answer' => 'Yes. Every business has different requirements, and we specialize in building customized software solutions tailored to your specific needs.',
                'sort_order' => 2,
            ],
            [
                'question' => 'Do you provide support after project completion?',
                'category' => 'Support',
                'answer' => 'Yes. We provide software maintenance, technical support, updates, and feature enhancements after project delivery.',
                'sort_order' => 3,
            ],
            [
                'question' => 'Which industries do you serve?',
                'category' => 'Industries',
                'answer' => 'We serve educational institutions, hospitals, medical stores, retail businesses, startups, service providers, and enterprises across various industries.',
                'sort_order' => 4,
            ],
            [
                'question' => 'Where is SoftKatta Solutions located?',
                'category' => 'General',
                'answer' => 'SoftKatta Solutions is based in Talni, Nanded, Maharashtra, India. We serve clients across Maharashtra and throughout India.',
                'sort_order' => 5,
            ],
            [
                'question' => 'What software products does SoftKatta Solutions offer?',
                'category' => 'Products',
                'answer' => 'We offer Study Point Management Software, Medical Store Management Software, and Nursery School Management Software.',
                'sort_order' => 6,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'category' => $faq['category'],
                    'answer' => $faq['answer'],
                    'sort_order' => $faq['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedBlogs(): void
    {
        $authorId = User::query()->value('id');
        $articles = [
            [
                'slug' => 'why-every-business-needs-custom-software-in-2026',
                'title' => 'Why Every Business Needs Custom Software in 2026',
                'excerpt' => 'Learn how customized software helps businesses increase productivity, reduce costs, and improve customer satisfaction.',
                'category' => 'Software Development',
                'meta_title' => 'Why Every Business Needs Custom Software in 2026',
                'meta_description' => 'Discover why custom software improves productivity, reduces manual work, and helps businesses scale in 2026 with SoftKatta Solutions.',
            ],
            [
                'slug' => 'erp-software-vs-traditional-business-management',
                'title' => 'ERP Software vs Traditional Business Management',
                'excerpt' => 'Understand the differences between ERP systems and traditional software and discover which solution best fits your business.',
                'category' => 'ERP Solutions',
                'meta_title' => 'ERP Software vs Traditional Business Management',
                'meta_description' => 'Compare ERP software with traditional business management tools and choose the right approach for your organization.',
            ],
            [
                'slug' => 'benefits-of-cloud-based-business-software',
                'title' => 'Benefits of Cloud-Based Business Software',
                'excerpt' => 'Explore why cloud applications are becoming the preferred choice for modern businesses due to flexibility, scalability, and security.',
                'category' => 'Cloud Computing',
                'meta_title' => 'Benefits of Cloud-Based Business Software',
                'meta_description' => 'Learn why cloud business software offers flexibility, scalability, security, and remote access for modern companies.',
            ],
            [
                'slug' => 'how-software-automation-saves-time-and-money',
                'title' => 'How Software Automation Saves Time and Money',
                'excerpt' => 'Discover how automating repetitive business processes increases efficiency and reduces operational expenses.',
                'category' => 'Business Automation',
                'meta_title' => 'How Software Automation Saves Time and Money',
                'meta_description' => 'See how business process automation reduces manual work, saves time, and lowers operational costs.',
            ],
            [
                'slug' => 'top-features-every-school-management-software-should-have',
                'title' => 'Top Features Every School Management Software Should Have',
                'excerpt' => 'A comprehensive guide to essential features like attendance, fee management, student records, communication, and reports.',
                'category' => 'Education Technology',
                'meta_title' => 'Top School Management Software Features Guide',
                'meta_description' => 'Essential school ERP features including attendance, fees, admissions, parent communication, and reporting explained.',
            ],
            [
                'slug' => 'complete-guide-to-hospital-management-software',
                'title' => 'Complete Guide to Hospital Management Software',
                'excerpt' => 'Learn how digital hospital systems improve patient care, appointment management, billing, pharmacy, and medical records.',
                'category' => 'Healthcare Technology',
                'meta_title' => 'Complete Guide to Hospital Management Software',
                'meta_description' => 'How hospital ERP software improves OPD, IPD, billing, pharmacy, pathology, and patient record management.',
            ],
            [
                'slug' => 'medical-store-management-software-features-benefits',
                'title' => 'Medical Store Management Software: Features & Benefits',
                'excerpt' => 'Discover how pharmacy software simplifies billing, inventory tracking, expiry management, GST compliance, and sales reporting.',
                'category' => 'Retail & Pharmacy Software',
                'meta_title' => 'Medical Store Software Features & Benefits',
                'meta_description' => 'Pharmacy billing, inventory, GST invoices, stock alerts, and reporting features for medical stores explained.',
            ],
            [
                'slug' => 'website-vs-mobile-app-which-does-your-business-need',
                'title' => 'Website vs Mobile App: Which One Does Your Business Need?',
                'excerpt' => 'Compare websites and mobile apps to understand which solution suits your business goals and customer needs.',
                'category' => 'Web Development',
                'meta_title' => 'Website vs Mobile App: Which Does Your Business Need?',
                'meta_description' => 'Compare websites and mobile apps to decide the best digital channel for your business goals and customers.',
            ],
            [
                'slug' => 'laravel-vs-react-powerful-combination',
                'title' => 'Laravel vs React: Why They Are a Powerful Combination',
                'excerpt' => 'Explore why Laravel and React are widely used for developing secure, scalable, and modern web applications.',
                'category' => 'Software Development',
                'meta_title' => 'Laravel and React: A Powerful Web Development Stack',
                'meta_description' => 'Why Laravel and React work together for secure, scalable, and modern custom web application development.',
            ],
            [
                'slug' => 'how-ai-is-transforming-business-software',
                'title' => 'How AI Is Transforming Business Software',
                'excerpt' => 'Understand how Artificial Intelligence is improving customer support, automation, analytics, and decision-making across industries.',
                'category' => 'Artificial Intelligence',
                'meta_title' => 'How AI Is Transforming Business Software',
                'meta_description' => 'Explore how AI improves automation, analytics, customer support, and decision-making in business software.',
            ],
            [
                'slug' => 'saas-vs-traditional-software-which-is-better',
                'title' => 'SaaS vs Traditional Software: Which Is Better?',
                'excerpt' => 'Compare subscription-based SaaS applications with traditional desktop software and choose the right model for your business.',
                'category' => 'Cloud Computing',
                'meta_title' => 'SaaS vs Traditional Software: Which Is Better?',
                'meta_description' => 'Compare SaaS subscription software with traditional licensed desktop applications for your business needs.',
            ],
            [
                'slug' => 'how-to-choose-right-software-development-company',
                'title' => 'How to Choose the Right Software Development Company',
                'excerpt' => 'A practical guide to selecting a reliable software partner based on experience, technology, support, and project delivery.',
                'category' => 'Software Development',
                'meta_title' => 'How to Choose the Right Software Development Company',
                'meta_description' => 'Practical tips for selecting a reliable software development partner based on experience, support, and delivery.',
            ],
        ];

        foreach ($articles as $index => $article) {
            Blog::updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'title' => $article['title'],
                    'excerpt' => $article['excerpt'],
                    'content' => $this->buildBlogContent($article['title'], $article['excerpt']),
                    'featured_image' => null,
                    'author_id' => $authorId,
                    'is_published' => true,
                    'published_at' => now()->subDays(12 - $index),
                    'meta' => [
                        'category' => $article['category'],
                        'meta_title' => $article['meta_title'],
                        'meta_description' => $article['meta_description'],
                    ],
                ],
            );
        }
    }

    private function buildBlogContent(string $title, string $excerpt): string
    {
        return implode("\n\n", [
            $excerpt,
            "Why This Matters in 2026\n\nBusinesses across India are adopting digital tools faster than ever. Whether you operate a school, hospital, medical store, retail shop, or service company, software can simplify operations, improve reporting, and create better customer experiences.",
            "Key Benefits for Your Business\n\n• Reduce manual paperwork and repetitive tasks\n• Improve data accuracy and business visibility\n• Enable faster decision-making with real-time reports\n• Scale operations without increasing administrative overhead\n• Deliver better service to customers, students, or patients",
            "How SoftKatta Solutions Can Help\n\nAt SoftKatta Solutions, we build custom software, ERP systems, websites, and mobile applications tailored to real business workflows. Our team in Talni, Nanded helps organizations across Maharashtra and India embrace practical digital transformation with secure, scalable technology.",
            "Explore Related Resources\n\n• Services: /services\n• Products: /products\n• Contact: /contact",
            "Frequently Asked Questions\n\nQ: Is custom software suitable for small businesses?\nA: Yes. Custom and modular software can be designed to fit your budget and scale as your business grows.\n\nQ: Do you provide support after project delivery?\nA: Yes. We offer software maintenance, updates, and long-term technical support.\n\nQ: How do I get started?\nA: Contact SoftKatta Solutions for a free consultation and project discussion.",
            "Ready to Transform Your Business?\n\nWhether you need a custom software solution, ERP system, website, or mobile application, SoftKatta Solutions is here to help. Visit /contact to discuss your project today.",
        ]);
    }

    private function seedHeroAltText(): void
    {
        $alts = [
            1 => 'ERP Software Dashboard — SoftKatta Solutions',
            2 => 'Study Point Management Software — SoftKatta Solutions',
            3 => 'Custom Software Development — SoftKatta Solutions',
        ];

        foreach ($alts as $sortOrder => $altText) {
            HeroSlide::where('sort_order', $sortOrder)->update(['alt_text' => $altText]);
        }
    }
}
