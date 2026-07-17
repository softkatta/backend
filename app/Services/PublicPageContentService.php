<?php

namespace App\Services;

use App\Models\Setting;

class PublicPageContentService
{
    /** @var array<string, string> */
    private const PAGE_KEYS = [
        'home' => 'page_content_home',
        'services' => 'page_content_services',
        'products' => 'page_content_products',
        'contact' => 'page_content_contact',
        'careers' => 'page_content_careers',
        'blog' => 'page_content_blog',
        'faq' => 'page_content_faq',
        'pricing' => 'page_content_pricing',
    ];

    private const SEO_KEY = 'public_page_seo';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $pages = [];

        foreach (self::PAGE_KEYS as $slug => $key) {
            $pages[$slug] = $this->page($slug);
        }

        return [
            'pages' => $pages,
            'seo' => $this->seo(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $slug): array
    {
        $key = self::PAGE_KEYS[$slug] ?? null;

        if ($key === null) {
            return [];
        }

        return $this->jsonSetting($key, $this->defaultPage($slug));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function seo(): array
    {
        return $this->jsonSetting(self::SEO_KEY, $this->defaultSeo());
    }

    /**
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    private function jsonSetting(string $key, array $default): array
    {
        $raw = Setting::query()->where('key', $key)->value('value');

        if ($raw === null || trim((string) $raw) === '') {
            return $default;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? array_replace_recursive($default, $decoded) : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPage(string $slug): array
    {
        return match ($slug) {
            'home' => [
                'label' => 'Software Development Company · Nanded',
                'title' => 'Custom ERP & Business Software for',
                'highlight' => 'Indian Businesses',
                'description' => 'SoftKatta Solutions builds ERP, business management software, web apps, and mobile apps for schools, medical stores, nurseries, and enterprises — from Talni, Nanded.',
                'hero_badges' => ['GST invoices', 'Instant activation', 'Secure checkout', '24/7 support'],
                'trust_items' => ['GST Ready', 'Udyam MSME', 'Shop Act', 'Secure Cloud'],
                'typewriter_phrases' => ['Schools & Coaching', 'Medical Stores', 'Nursery Schools', 'Enterprises'],
                'sections' => [
                    'products' => [
                        'label' => 'Our Software Products',
                        'title' => 'Business Management',
                        'highlight' => 'Software Solutions',
                        'description' => 'Study Point, Medical Store, and Nursery School management software — built in Nanded for Indian businesses.',
                    ],
                    'services' => [
                        'label' => 'Professional Services',
                        'title' => 'Custom Software',
                        'highlight' => 'Development',
                        'description' => 'From coaching institutes and medical stores to nursery schools and hospitals — secure, scalable ERP and custom software from Talni, Nanded.',
                    ],
                    'why' => [
                        'label' => 'Why Choose SoftKatta Solutions',
                        'title' => 'Modern Tech,',
                        'highlight' => 'Trusted Support',
                        'description' => '',
                    ],
                    'faq' => [
                        'label' => 'Frequently Asked Questions',
                        'title' => 'Common',
                        'highlight' => 'Questions',
                        'description' => 'Quick answers about our software products, custom development, and support in Nanded.',
                    ],
                ],
                'why_highlight' => [
                    'stat' => 'India',
                    'title' => 'Built for Indian businesses',
                    'description' => 'From coaching institutes and medical stores to nursery schools and hospitals — secure, scalable ERP and custom software from Talni, Nanded.',
                ],
                'why_cards' => [
                    ['icon' => 'zap', 'title' => 'Lightning Fast', 'description' => 'Cloud-native architecture with 99.9% uptime SLA', 'color' => '#2563eb'],
                    ['icon' => 'shield', 'title' => 'Enterprise Security', 'description' => 'RBAC, encryption, audit logs & compliance', 'color' => '#14b8a6'],
                    ['icon' => 'barchart', 'title' => 'Smart Analytics', 'description' => 'Real-time dashboards and revenue insights', 'color' => '#6366f1'],
                    ['icon' => 'users', 'title' => '24/7 Support', 'description' => 'Dedicated onboarding and Marathi/English support', 'color' => '#0891b2'],
                ],
            ],
            'services' => [
                'label' => 'Software Services',
                'title' => 'Software Development Services That Help Your',
                'highlight' => 'Business Grow',
                'description' => 'At SoftKatta Solutions, we provide innovative, scalable, and reliable software development services tailored to businesses of all sizes. From custom software and ERP solutions to websites and mobile applications, we help organizations embrace digital transformation with modern technology.',
                'why_choose_title' => 'Why Choose SoftKatta Solutions?',
                'why_choose_items' => [
                    'Experienced Development Team',
                    'Customized Business Solutions',
                    'Secure & Scalable Applications',
                    'Affordable Pricing',
                    'Modern Technologies',
                    'Dedicated Support',
                    'On-Time Delivery',
                    'Long-Term Partnership',
                ],
                'cta_text' => 'Ready to start your project? Talk to our team about custom software, ERP, websites, or mobile apps.',
            ],
            'products' => [
                'label' => 'Software Products',
                'title' => 'Business Software',
                'highlight' => 'Products',
                'description' => 'Our software products are designed to automate business operations, improve productivity, and simplify day-to-day management.',
            ],
            'contact' => [
                'label' => 'Get In Touch',
                'title' => "Let's Build Something",
                'highlight' => 'Great Together',
                'description' => "Whether you're looking for custom software, ERP solutions, website development, or mobile applications, our team is ready to help you bring your ideas to life.",
                'cta_title' => 'Have a project in mind?',
                'cta_description' => "Contact SoftKatta Solutions today and let's discuss how we can help your business grow through innovative technology solutions.",
                'trust_items' => ['24h response', 'Free consultation', 'GST billing'],
            ],
            'careers' => [
                'label' => 'Join Our Team',
                'title' => 'Build Innovative',
                'highlight' => 'Software With Us',
                'description' => 'Join SoftKatta Solutions and build innovative software that makes a difference. We are always looking for passionate developers, designers, testers, and technology enthusiasts who want to grow with us.',
                'perks' => [
                    ['title' => 'Meaningful work', 'text' => 'Ship products used by real businesses every day.'],
                    ['title' => 'Small, focused team', 'text' => 'Collaborate closely — no endless meetings.'],
                    ['title' => 'Customer-first culture', 'text' => 'We build with empathy and long-term impact.'],
                    ['title' => 'Room to grow', 'text' => 'Learn new skills and take ownership early.'],
                ],
            ],
            'blog' => [
                'label' => 'Insights & Guides',
                'title' => 'Technology Insights, Software Tips &',
                'highlight' => 'Business Automation',
                'description' => 'Stay updated with the latest software trends, technology news, business automation ideas, and digital transformation guides from SoftKatta Solutions.',
                'categories' => [
                    'Software Development',
                    'ERP Solutions',
                    'Business Automation',
                    'Artificial Intelligence',
                    'Cloud Computing',
                    'Web Development',
                    'Mobile App Development',
                    'Healthcare Technology',
                    'Education Technology',
                    'Retail & Pharmacy Software',
                    'Cyber Security',
                    'Digital Marketing',
                    'UI/UX Design',
                    'Technology Trends',
                ],
                'cta_title' => 'Ready to Transform Your Business?',
                'cta_description' => 'Whether you need a custom software solution, ERP system, website, or mobile application, SoftKatta Solutions is here to help. Contact us today to discuss your project and take the next step toward digital transformation.',
            ],
            'faq' => [
                'label' => 'Help Center',
                'title' => 'Frequently Asked',
                'highlight' => 'Questions',
                'description' => 'Answers about our software products, custom development services, support, and industries we serve.',
            ],
            'pricing' => [
                'label' => 'Pricing',
                'title' => 'Simple, transparent',
                'highlight' => 'pricing',
                'description' => 'Choose a plan that fits your business. All products include GST invoicing and secure checkout.',
                'trust_items' => ['GST invoices', 'Instant activation', 'Secure checkout', 'Cancel anytime'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaultSeo(): array
    {
        return [
            '/' => [
                'title' => 'SoftKatta Solutions | Custom Software Development Company in Nanded',
                'description' => 'SoftKatta Solutions develops ERP software, websites, mobile applications, and custom business software for education, healthcare, retail, and enterprises.',
                'keywords' => '',
            ],
            '/services' => [
                'title' => 'Software Development Services That Help Your Business Grow | SoftKatta Solutions',
                'description' => 'Innovative custom software, ERP, website, mobile app, SaaS, cloud, API integration, UI/UX design, and maintenance services for businesses across India.',
                'keywords' => '',
            ],
            '/products' => [
                'title' => 'Business Software Products — ERP & Management Software | SoftKatta Solutions',
                'description' => 'Study Point, Medical Store, and Nursery School management software to automate operations and simplify business management.',
                'keywords' => '',
            ],
            '/about' => [
                'title' => 'About SoftKatta Solutions — Building Smart Software Solutions in Nanded',
                'description' => 'Software development company in Talni, Nanded building ERP software, custom applications, websites, and mobile apps.',
                'keywords' => '',
            ],
            '/contact' => [
                'title' => "Contact SoftKatta Solutions — Let's Build Something Great Together",
                'description' => 'Contact SoftKatta Solutions for custom software, ERP solutions, website development, and mobile applications.',
                'keywords' => '',
            ],
            '/careers' => [
                'title' => 'Careers — Join SoftKatta Solutions | Software Jobs in Nanded',
                'description' => 'Join SoftKatta Solutions and build innovative software. Open roles for developers, designers, and technology enthusiasts.',
                'keywords' => '',
            ],
            '/blog' => [
                'title' => 'Technology Insights, Software Tips & Business Automation | SoftKatta Solutions',
                'description' => 'Software trends, ERP guides, cloud computing, AI, and digital transformation articles from SoftKatta Solutions.',
                'keywords' => '',
            ],
            '/faq' => [
                'title' => 'FAQ — Software Development & ERP Questions | SoftKatta Solutions',
                'description' => 'Frequently asked questions about SoftKatta Solutions services, products, and support.',
                'keywords' => '',
            ],
            '/pricing' => [
                'title' => 'Software Pricing — SoftKatta Solutions',
                'description' => 'Affordable pricing for ERP and business management software with GST invoicing and secure checkout.',
                'keywords' => '',
            ],
        ];
    }
}
