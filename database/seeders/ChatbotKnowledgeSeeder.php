<?php

namespace Database\Seeders;

use App\Models\ChatbotCategory;
use App\Models\ChatbotFaq;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Production chatbot knowledge base — import from JSON + extended FAQ catalog.
 * Run: php artisan db:seed --class=ChatbotKnowledgeSeeder
 */
class ChatbotKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedFromJson();
        $this->seedExtendedFaqs();
        $this->seedPortalFaqs();
        $this->seedMultilingualFaqs();
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Products', 'slug' => 'products', 'sort_order' => 1],
            ['name' => 'Services', 'slug' => 'services', 'sort_order' => 2],
            ['name' => 'Pricing', 'slug' => 'pricing', 'sort_order' => 3],
            ['name' => 'Support', 'slug' => 'support', 'sort_order' => 4],
            ['name' => 'Company Information', 'slug' => 'company', 'sort_order' => 5],
            ['name' => 'Billing', 'slug' => 'billing', 'sort_order' => 6],
            ['name' => 'Technical', 'slug' => 'technical', 'sort_order' => 7],
            ['name' => 'Careers', 'slug' => 'careers', 'sort_order' => 8],
            ['name' => 'General', 'slug' => 'general', 'sort_order' => 9],
            ['name' => 'Employee Portal', 'slug' => 'portal_employee', 'sort_order' => 10],
            ['name' => 'Client Portal', 'slug' => 'portal_client', 'sort_order' => 11],
            ['name' => 'Admin Portal', 'slug' => 'portal_admin', 'sort_order' => 12],
            ['name' => 'HR Portal', 'slug' => 'portal_hr', 'sort_order' => 13],
        ];

        foreach ($categories as $category) {
            ChatbotCategory::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['is_active' => true]),
            );
        }
    }

    private function seedFromJson(): void
    {
        $path = database_path('seeders/data/chatbot-knowledge-base.json');
        if (! File::exists($path)) {
            return;
        }

        $data = json_decode(File::get($path), true);
        foreach ($data['faqs'] ?? [] as $faq) {
            $this->upsertFaq($faq);
        }
    }

    private function seedExtendedFaqs(): void
    {
        foreach ($this->faqCatalog() as $faq) {
            $this->upsertFaq($faq);
        }
    }

    /** @param array<string, mixed> $faq */
    private function upsertFaq(array $faq): void
    {
        ChatbotFaq::updateOrCreate(
            [
                'question' => $faq['question'],
                'language' => $faq['language'] ?? 'en',
            ],
            [
                'answer' => $faq['answer'],
                'keywords' => $faq['keywords'] ?? '',
                'category' => $faq['category'] ?? 'general',
                'sort_order' => $faq['sort_order'] ?? 100,
                'is_active' => true,
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function faqCatalog(): array
    {
        $contact = '+91 7038452357 | support@softkatta.in | Talni, Nanded, Maharashtra';
        $hours = "Monday – Saturday: 9:00 AM – 7:00 PM IST\nSunday: Closed";

        return array_merge(
            $this->companyFaqs($contact, $hours),
            $this->homeFaqs(),
            $this->aboutFaqs(),
            $this->productFaqs(),
            $this->studyPointFaqs(),
            $this->medicalStoreFaqs(),
            $this->nurserySchoolFaqs(),
            $this->serviceFaqs(),
            $this->pricingFaqs(),
            $this->salesFaqs(),
            $this->supportFaqs(),
            $this->billingFaqs(),
            $this->technicalFaqs(),
            $this->careerFaqs(),
            $this->conversationalTrainingFaqs(),
            $this->fallbackFaqs($contact, $hours),
        );
    }

    /** @return list<array<string, mixed>> */
    private function companyFaqs(string $contact, string $hours): array
    {
        return [
            ['question' => 'What is SoftKatta Solutions?', 'answer' => 'SoftKatta Solutions is a custom software development company based in Talni, Nanded, Maharashtra. We build ERP systems, business management software, websites, and mobile applications for schools, medical stores, nurseries, and enterprises across India.', 'keywords' => 'softkatta, company, about, who are you', 'category' => 'company', 'sort_order' => 1],
            ['question' => 'Where is SoftKatta Solutions located?', 'answer' => "We are located in Talni, Nanded, Maharashtra, India. We serve clients across Maharashtra and all of India.\n\nContact: {$contact}", 'keywords' => 'location, address, nanded, talni, office', 'category' => 'company', 'sort_order' => 2],
            ['question' => 'What are your business hours?', 'answer' => $hours, 'keywords' => 'hours, timing, open, closed, sunday', 'category' => 'company', 'sort_order' => 3],
            ['question' => 'How can I contact SoftKatta?', 'answer' => "Phone: +91 7038452357\nEmail: support@softkatta.in\nWebsite: https://softkatta.in\nAddress: Talni, Nanded, Maharashtra\n\nOr use our contact form at /contact — we reply within 24 business hours.", 'keywords' => 'contact, phone, email, reach, how to contact, contact us, get in touch, call, whatsapp', 'category' => 'company', 'sort_order' => 4],
            ['question' => 'Do you provide support in Marathi or Hindi?', 'answer' => 'Yes. Our team supports English, Marathi, and Hindi for consultations and customer support.', 'keywords' => 'marathi, hindi, language, regional', 'category' => 'company', 'sort_order' => 5],
            ['question' => 'Which industries do you serve?', 'answer' => 'Education (schools, coaching, institutes), healthcare/pharmacy (medical stores), nursery schools, retail, startups, service providers, and enterprises needing custom ERP or software.', 'keywords' => 'industries, sectors, who do you serve', 'category' => 'company', 'sort_order' => 6],
            ['question' => 'Is SoftKatta GST registered?', 'answer' => 'Yes. We provide GST-ready invoices on product purchases. GST is applied as per applicable rates (typically 18% on software subscriptions).', 'keywords' => 'gst, registered, invoice, tax', 'category' => 'company', 'sort_order' => 7],
            ['question' => 'Where can I read the Privacy Policy?', 'answer' => 'Our Privacy Policy is available at /privacy. It explains how we collect, use, and protect your data.', 'keywords' => 'privacy policy, privacy, data', 'category' => 'company', 'sort_order' => 9],
            ['question' => 'Where are the Terms of Service?', 'answer' => 'Terms of Service for accounts, subscriptions, and trials are at /terms.', 'keywords' => 'terms, terms of service, legal', 'category' => 'company', 'sort_order' => 10],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function homeFaqs(): array
    {
        return [
            ['question' => 'What does SoftKatta build?', 'answer' => 'We build custom ERP & business software for Schools & Coaching, Medical Stores, Nursery Schools, and Enterprises — including web apps and mobile apps.', 'keywords' => 'home, build, erp, software', 'category' => 'general', 'sort_order' => 10],
            ['question' => 'Do you offer 24/7 support?', 'answer' => 'We provide dedicated support with response within 24 business hours. Business hours are Mon–Sat 9 AM – 7 PM IST. Critical issues for active subscribers are prioritized.', 'keywords' => '24/7, support, help', 'category' => 'support', 'sort_order' => 11],
            ['question' => 'Can I watch a product demo video?', 'answer' => 'Yes. Demo videos are available on the Home page and individual product pages when configured. You can also book a live demo via chatbot or /contact.', 'keywords' => 'demo video, watch, preview', 'category' => 'products', 'sort_order' => 12],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function aboutFaqs(): array
    {
        return [
            ['question' => 'What is SoftKatta mission?', 'answer' => 'Our mission is to empower businesses through reliable, innovative, and affordable software solutions that simplify operations and drive growth.', 'keywords' => 'mission, goal, purpose', 'category' => 'company', 'sort_order' => 20],
            ['question' => 'What are SoftKatta core values?', 'answer' => 'Innovation, Quality, Customer Satisfaction, Transparency, Security, and Continuous Improvement.', 'keywords' => 'values, culture, principles', 'category' => 'company', 'sort_order' => 21],
            ['question' => 'When was SoftKatta founded?', 'answer' => 'SoftKatta was founded to make technology accessible for Indian businesses — building custom ERP, SaaS products, websites, and mobile apps from Nanded, Maharashtra.', 'keywords' => 'founded, history, story', 'category' => 'company', 'sort_order' => 22],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function productFaqs(): array
    {
        return [
            ['question' => 'What products does SoftKatta offer?', 'answer' => "Our ready-to-use SaaS products:\n\n1. Study Point Management Software — for schools & coaching institutes\n2. Medical Store Management Software — pharmacy billing & inventory\n3. Nursery School Management Software — admissions, fees, parent portal\n\nWe also build custom ERP and software on request.", 'keywords' => 'products, erp, software, list, catalog', 'category' => 'products', 'sort_order' => 30],
            ['question' => 'Is there a free trial?', 'answer' => 'Yes! All three products include a 14-day free trial. No credit card required. Register at /register, then click "Try free for 14 days" on any product page.', 'keywords' => 'free trial, try, demo, test', 'category' => 'products', 'sort_order' => 31],
            ['question' => 'How do I purchase a product?', 'answer' => "1. Create account at /register\n2. Visit /products and choose a product\n3. Select monthly or yearly plan\n4. Click Buy Now or Add to Cart\n5. Complete checkout via Razorpay\n6. Access your subscription from the client dashboard", 'keywords' => 'buy, purchase, order, how to buy', 'category' => 'products', 'sort_order' => 32],
            ['question' => 'Do your products work on mobile?', 'answer' => 'Yes. Our web applications are responsive and work on mobile browsers. Dedicated mobile apps can be built as a custom service.', 'keywords' => 'mobile, phone, android, ios, app', 'category' => 'products', 'sort_order' => 33],
            ['question' => 'Do you support WhatsApp notifications?', 'answer' => 'Study Point Management Software includes WhatsApp Notifications as a feature. WhatsApp integration for other products or custom projects is available via our API Integration Services.', 'keywords' => 'whatsapp, notification, sms, alert', 'category' => 'products', 'sort_order' => 34],
            ['question' => 'Can I upgrade or downgrade my plan?', 'answer' => 'Yes. You can upgrade or downgrade your subscription plan anytime from your dashboard. Changes take effect immediately.', 'keywords' => 'upgrade, downgrade, change plan', 'category' => 'billing', 'sort_order' => 35],
            ['question' => 'Is data backup included?', 'answer' => 'Yes. Automatic daily backups are included on all paid plans.', 'keywords' => 'backup, data safety, restore', 'category' => 'technical', 'sort_order' => 36],
            ['question' => 'How do I migrate existing data?', 'answer' => 'We provide implementation support for data migration on paid plans. Contact support with your current system details and we schedule a migration call.', 'keywords' => 'migrate, migration, import, existing data', 'category' => 'technical', 'sort_order' => 37],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function studyPointFaqs(): array
    {
        $base = ['category' => 'products', 'sort_order' => 40];
        return [
            array_merge($base, ['question' => 'What is Study Point Management Software?', 'answer' => 'Study Point is an Education ERP for schools and coaching institutes. Manage admissions, students, attendance, batches, fee collection, examinations, results, staff, and reports in one platform.', 'keywords' => 'study point, school erp, coaching, education']),
            array_merge($base, ['question' => 'Who should use Study Point?', 'answer' => 'Schools, coaching classes, training institutes, and educational centers that need student management, attendance, fees, batches, and exam management.', 'keywords' => 'study point, who, suitable, target']),
            array_merge($base, ['question' => 'What features does Study Point provide?', 'answer' => 'Student Management, Attendance, Fee Collection, Batch Management, Online Payments, WhatsApp Notifications, Reports & Analytics.', 'keywords' => 'study point features, modules']),
            array_merge($base, ['question' => 'How much does Study Point cost?', 'answer' => "Plans start at ₹2,999/month (Basic).\nProfessional: ₹5,999/month\nEnterprise: ₹12,999/month\n\nYearly plans available with ~20% savings.\nSee /pricing or /products/study-point-management-software", 'keywords' => 'study point price, cost, pricing', 'category' => 'pricing']),
            array_merge($base, ['question' => 'How can I book a Study Point demo?', 'answer' => 'Click "Book Demo" in this chat, visit /contact, or call +91 7038452357. You can also start a 14-day free trial without a demo.', 'keywords' => 'study point demo, schedule']),
            array_merge($base, ['question' => 'Does Study Point support multiple branches?', 'answer' => 'Yes. Basic supports 1 branch, Professional up to 3, Enterprise up to 10 branches.', 'keywords' => 'study point branches, multi branch']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function medicalStoreFaqs(): array
    {
        $base = ['category' => 'products', 'sort_order' => 50];
        return [
            array_merge($base, ['question' => 'What is Medical Store Management Software?', 'answer' => 'Complete pharmacy software with GST billing, barcode support, inventory management, stock alerts, purchase & sales, supplier records, and expiry tracking.', 'keywords' => 'medical store, pharmacy, chemist']),
            array_merge($base, ['question' => 'Who should use Medical Store software?', 'answer' => 'Single-store pharmacies, medical stores, and multi-counter pharmacy operations needing billing, inventory, and GST compliance.', 'keywords' => 'medical store who, pharmacy suitable']),
            array_merge($base, ['question' => 'What features does Medical Store software provide?', 'answer' => 'GST Billing, Barcode Support, Inventory Management, Stock Alerts, Purchase & Sales, Reports.', 'keywords' => 'medical store features']),
            array_merge($base, ['question' => 'How much does Medical Store software cost?', 'answer' => "Starter: ₹1,999/month\nProfessional: ₹4,999/month\n\nYearly Starter plan also available with savings.\nSee /pricing", 'keywords' => 'medical store price, pharmacy cost', 'category' => 'pricing']),
            array_merge($base, ['question' => 'Does Medical Store software support GST invoices?', 'answer' => 'Yes. GST billing and GST-ready invoices are core features.', 'keywords' => 'medical gst, pharmacy invoice']),
            array_merge($base, ['question' => 'Does it track medicine expiry?', 'answer' => 'Yes. Expiry tracking and stock alerts help prevent expired stock sales.', 'keywords' => 'expiry, batch, stock alert']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function nurserySchoolFaqs(): array
    {
        $base = ['category' => 'products', 'sort_order' => 60];
        return [
            array_merge($base, ['question' => 'What is Nursery School Management Software?', 'answer' => 'ERP for nursery and pre-primary schools — admissions, attendance, fee management, parent portal, notifications, and reports.', 'keywords' => 'nursery, preschool, kindergarten']),
            array_merge($base, ['question' => 'Who should use Nursery School software?', 'answer' => 'Nursery schools, pre-primary schools, and early childhood centers needing admissions, fees, attendance, and parent communication.', 'keywords' => 'nursery who, preschool suitable']),
            array_merge($base, ['question' => 'How much does Nursery School software cost?', 'answer' => "Basic: ₹1,499/month\nStandard: ₹2,999/month\n\nYearly Standard plan available with savings.\nSee /pricing", 'keywords' => 'nursery price, preschool cost', 'category' => 'pricing']),
            array_merge($base, ['question' => 'Does Nursery School software have a parent portal?', 'answer' => 'Yes. The Standard plan includes Parent Portal and Notifications for parent engagement.', 'keywords' => 'parent portal, parents, nursery']),
            array_merge($base, ['question' => 'What features does Nursery School software provide?', 'answer' => 'Student Admission, Attendance, Fees, Parent Portal, Notifications, Reports.', 'keywords' => 'nursery features']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function serviceFaqs(): array
    {
        $services = [
            ['slug' => 'custom-software-development', 'name' => 'Custom Software Development', 'desc' => 'Tailor-made software matching your exact business workflow — billing, inventory, CRM, HR, automation.'],
            ['slug' => 'erp-software-development', 'name' => 'ERP Software Development', 'desc' => 'Custom ERP connecting departments — suitable for schools, coaching, hospitals, medical stores, retail, service providers.'],
            ['slug' => 'website-development', 'name' => 'Website Development', 'desc' => 'Modern, responsive, SEO-friendly websites — business, corporate, educational, hospital, landing, eCommerce.'],
            ['slug' => 'mobile-app-development', 'name' => 'Mobile App Development', 'desc' => 'Secure Android and cross-platform mobile apps for business, schools, hospitals, pharmacy, customers, employees.'],
            ['slug' => 'saas-application-development', 'name' => 'SaaS Application Development', 'desc' => 'Cloud software with subscriptions, roles, billing, multi-tenant architecture.'],
            ['slug' => 'cloud-solutions', 'name' => 'Cloud Solutions', 'desc' => 'Secure cloud deployment with backups, scalability, and security best practices.'],
            ['slug' => 'api-integration', 'name' => 'API Integration Services', 'desc' => 'Payment gateways, WhatsApp, SMS, email, Google APIs, biometrics integrations.'],
            ['slug' => 'ui-ux-design', 'name' => 'UI/UX Design', 'desc' => 'Intuitive interface design for better user engagement and conversion.'],
            ['slug' => 'software-maintenance', 'name' => 'Software Maintenance & Support (AMC)', 'desc' => 'Ongoing maintenance, bug fixes, enhancements, and dedicated support contracts.'],
        ];

        $faqs = [
            ['question' => 'What services does SoftKatta provide?', 'answer' => "Custom Software, ERP Development, Website Development, Mobile App Development, SaaS Development, Cloud Solutions, API Integration, UI/UX Design, and Software Maintenance & Support.\n\nBrowse all at /services", 'keywords' => 'services, development, what do you do', 'category' => 'services', 'sort_order' => 70],
            ['question' => 'Do you develop customized software?', 'answer' => 'Yes. We build custom ERP, web, and mobile applications tailored to your specific business requirements. Request a quote at /contact.', 'keywords' => 'custom software, bespoke, tailored', 'category' => 'services', 'sort_order' => 71],
            ['question' => 'Do you provide support after project delivery?', 'answer' => 'Yes. We offer software maintenance, updates, enhancements, and long-term AMC/support contracts.', 'keywords' => 'after delivery, maintenance, amc, support', 'category' => 'services', 'sort_order' => 72],
            ['question' => 'How do I request a service quote?', 'answer' => 'Visit /services, open any service page, and click Request Quote — or use /contact. We respond within 24 business hours.', 'keywords' => 'quote, estimate, service price', 'category' => 'services', 'sort_order' => 73],
        ];

        foreach ($services as $i => $svc) {
            $faqs[] = [
                'question' => "What is {$svc['name']}?",
                'answer' => "{$svc['desc']}\n\nLearn more: /services/{$svc['slug']}",
                'keywords' => strtolower($svc['name']).', '.$svc['slug'].', service',
                'category' => 'services',
                'sort_order' => 74 + $i,
            ];
        }

        return $faqs;
    }

    /** @return list<array<string, mixed>> */
    private function pricingFaqs(): array
    {
        return [
            ['question' => 'How can I get pricing?', 'answer' => "Product pricing is on /pricing:\n• Study Point from ₹2,999/mo\n• Medical Store from ₹1,999/mo\n• Nursery School from ₹1,499/mo\n\nCustom projects: contact +91 7038452357 or support@softkatta.in", 'keywords' => 'pricing, price, cost, quote, how much', 'category' => 'pricing', 'sort_order' => 80],
            ['question' => 'Are prices inclusive of GST?', 'answer' => 'GST (typically 18%) is applied at checkout. GST invoices are generated automatically after purchase.', 'keywords' => 'gst, tax, inclusive, invoice', 'category' => 'pricing', 'sort_order' => 81],
            ['question' => 'Do you offer yearly billing discounts?', 'answer' => 'Yes. Yearly plans offer approximately 20% savings compared to paying monthly for 12 months.', 'keywords' => 'yearly, annual, discount, save', 'category' => 'pricing', 'sort_order' => 82],
            ['question' => 'Are there any discount coupons?', 'answer' => 'Promotional codes like SAVE20 (20% off first purchase) and WELCOME500 (₹500 off) may be available — enter at checkout if active.', 'keywords' => 'coupon, discount, promo, offer', 'category' => 'pricing', 'sort_order' => 83],
            ['question' => 'Can I cancel my subscription?', 'answer' => 'Yes. You can manage subscriptions from your client dashboard. Contact support for cancellation assistance.', 'keywords' => 'cancel, unsubscribe, stop', 'category' => 'billing', 'sort_order' => 84],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function salesFaqs(): array
    {
        return [
            ['question' => 'How do I book a demo?', 'answer' => 'Use the chatbot "Book Demo" button, fill the form with your name, phone, email, company, and preferred product. Or visit /contact / call +91 7038452357.', 'keywords' => 'book demo, schedule demo, live demo', 'category' => 'pricing', 'sort_order' => 90],
            ['question' => 'What payment methods do you accept?', 'answer' => 'Credit/debit cards, UPI, and net banking via Razorpay. GST invoices are auto-generated.', 'keywords' => 'payment, razorpay, card, upi', 'category' => 'billing', 'sort_order' => 91],
            ['question' => 'Do I need an account to buy?', 'answer' => 'Yes. Create a free account at /register before purchase. Profile photo is required for registration.', 'keywords' => 'account, register, signup, buy', 'category' => 'pricing', 'sort_order' => 92],
            ['question' => 'Can I buy for multiple branches?', 'answer' => 'Choose Enterprise or Professional plans for multi-branch limits, or contact sales for custom licensing.', 'keywords' => 'multiple branches, multi location', 'category' => 'pricing', 'sort_order' => 93],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function supportFaqs(): array
    {
        return [
            ['question' => 'How do I contact support?', 'answer' => "Email: support@softkatta.in\nPhone: +91 7038452357\nChatbot: Technical Support form\nLogged-in clients: /dashboard/support to raise tickets\n\nResponse within 24 business hours.", 'keywords' => 'support, help, contact support', 'category' => 'support', 'sort_order' => 100],
            ['question' => 'I forgot my password', 'answer' => 'Go to /login and click Forgot Password. Check your email for the reset link. If issues persist, email support@softkatta.in.', 'keywords' => 'forgot password, reset password, login', 'category' => 'support', 'sort_order' => 101],
            ['question' => 'I cannot login to my account', 'answer' => 'Verify email/password at /login. Try password reset. Clear browser cache. If 2FA is enabled, use your authenticator app. Still stuck? Contact support@softkatta.in.', 'keywords' => 'login issue, cant login, sign in problem', 'category' => 'support', 'sort_order' => 102],
            ['question' => 'My payment failed at checkout', 'answer' => 'Retry checkout. If charged but order not confirmed, email support@softkatta.in with Razorpay transaction ID. We resolve within 24 hours.', 'keywords' => 'payment failed, checkout error, razorpay', 'category' => 'support', 'sort_order' => 103],
            ['question' => 'How do I renew my subscription?', 'answer' => 'Login → /dashboard/subscriptions. Enable auto-renew or purchase a new plan before expiry. Contact support for manual renewal.', 'keywords' => 'renew, renewal, expiring, extend', 'category' => 'support', 'sort_order' => 104],
            ['question' => 'How do I raise a support ticket?', 'answer' => 'Login to your client account → /dashboard/support → Create ticket with subject and description. Track replies in the same portal.', 'keywords' => 'ticket, support ticket, raise ticket', 'category' => 'support', 'sort_order' => 105],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function billingFaqs(): array
    {
        return [
            ['question' => 'Will I get a GST invoice?', 'answer' => 'Yes. GST invoices are automatically generated and emailed after successful payment.', 'keywords' => 'gst invoice, bill, receipt', 'category' => 'billing', 'sort_order' => 110],
            ['question' => 'Can I get a refund?', 'answer' => 'Refund policy details are not published on the website yet. Contact support@softkatta.in with your order number for refund requests.', 'keywords' => 'refund, money back, cancel refund', 'category' => 'billing', 'sort_order' => 111],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function technicalFaqs(): array
    {
        return [
            ['question' => 'Where is my data hosted?', 'answer' => 'Our SaaS products use secure cloud hosting with encryption and daily backups. Custom hosting requirements can be discussed for enterprise projects.', 'keywords' => 'hosting, cloud, server, where data', 'category' => 'technical', 'sort_order' => 120],
            ['question' => 'Is my data secure?', 'answer' => 'Yes. We use role-based access, encryption, secure cloud infrastructure, and automatic daily backups on paid plans.', 'keywords' => 'security, secure, encryption, safe', 'category' => 'technical', 'sort_order' => 121],
            ['question' => 'Do you provide training?', 'answer' => 'Yes. Product onboarding and training can be arranged. Custom training is available as part of implementation or AMC contracts.', 'keywords' => 'training, onboarding, learn, teach', 'category' => 'technical', 'sort_order' => 122],
            ['question' => 'Can you integrate payment gateway?', 'answer' => 'Yes. Our products support online payments. Custom payment gateway integration is available via API Integration Services.', 'keywords' => 'payment gateway, razorpay, integration', 'category' => 'technical', 'sort_order' => 123],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function careerFaqs(): array
    {
        return [
            ['question' => 'Are you hiring?', 'answer' => 'Yes — check open positions at /careers. You can apply online for listed roles. If no role matches, send your resume through /contact and our HR team will review it.', 'keywords' => 'hiring, jobs, careers, vacancy, are you hiring, job opening, recruitment', 'category' => 'careers', 'sort_order' => 130],
            ['question' => 'How do I apply for a job?', 'answer' => 'Visit /careers → select a role → View role & apply. Submit resume and required details. Shortlisted candidates are contacted within 3–5 business days.', 'keywords' => 'apply, job application, resume', 'category' => 'careers', 'sort_order' => 131],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function conversationalTrainingFaqs(): array
    {
        return [
            ['question' => 'How do I contact you?', 'answer' => "Phone: +91 7038452357\nEmail: support@softkatta.in\nWebsite: https://softkatta.in\nContact form: /contact\n\nWe reply within 24 business hours.", 'keywords' => 'how to contact, contact you, reach you, get in touch, talk to you', 'category' => 'company', 'sort_order' => 8],
            ['question' => 'What is your phone number?', 'answer' => 'Call +91 7038452357 (Mon–Sat 9 AM – 7 PM IST) or email support@softkatta.in.', 'keywords' => 'phone number, mobile number, call you, whatsapp number', 'category' => 'company', 'sort_order' => 9],
            ['question' => 'Tell me about your products', 'answer' => "We offer:\n1. Study Point Management Software — schools & coaching\n2. Medical Store Management Software — pharmacy billing\n3. Nursery School Management Software — pre-primary schools\n\nSee /products for details and 14-day free trials.", 'keywords' => 'tell me products, show products, what do you sell, software list', 'category' => 'products', 'sort_order' => 38],
            ['question' => 'What are your prices?', 'answer' => "Monthly plans from /pricing:\n• Study Point from ₹2,999/mo\n• Medical Store from ₹1,999/mo\n• Nursery School from ₹1,499/mo\n\nYearly plans save ~20%. Custom software is quoted on request.", 'keywords' => 'what are your prices, how much does it cost, price details, rates', 'category' => 'pricing', 'sort_order' => 85],
            ['question' => 'I need technical help', 'answer' => "For technical support:\n1. Use chatbot → Technical Support form\n2. Email support@softkatta.in\n3. Call +91 7038452357\n4. Logged-in clients: /dashboard/support", 'keywords' => 'technical help, need help, software issue, problem with software', 'category' => 'support', 'sort_order' => 106],
            ['question' => 'Can I export my data?', 'answer' => 'Yes. Data export can be arranged for active subscribers. Email support@softkatta.in with your account details and preferred format (Excel/CSV/PDF).', 'keywords' => 'export data, download data, backup my data, data export', 'category' => 'technical', 'sort_order' => 124],
            ['question' => 'Can I get a refund?', 'answer' => 'For refund requests, email support@softkatta.in with your order number and reason. Our team reviews each case and responds within 3–5 business days.', 'keywords' => 'refund, money back, cancel and refund, return payment', 'category' => 'billing', 'sort_order' => 112],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fallbackFaqs(string $contact, string $hours): array
    {
        $questions = [
            ['Is SoftKatta better than Fedena?', 'We focus on affordable ERP for Indian schools and coaching with local support from Nanded. Contact us for a feature comparison demo.', 'comparison, fedena, competitor'],
            ['Is SoftKatta better than Vyapar for pharmacy?', 'Our Medical Store software includes pharmacy-specific inventory, expiry tracking, and GST billing. Book a demo to compare.', 'comparison, vyapar, pharmacy competitor'],
            ['Can you customize the ERP for my workflow?', 'Yes. Custom modules and workflows are available via Custom Software and ERP Development services.', 'customization, custom module, workflow'],
            ['Do you support biometric attendance?', 'Biometric integration is available via API Integration Services. Contact us with your device model.', 'biometric, attendance device, fingerprint'],
            ['Can parents pay fees online?', 'Yes. Study Point and Nursery School products support online fee payments.', 'online payment, fee payment, parents pay'],
            ['Do you support multiple languages in the software?', 'UI language customization can be discussed for enterprise plans. Chatbot supports English, Marathi, and Hindi.', 'multi language, regional language, marathi ui'],
            ['Can I use my own domain?', 'Custom domains are supported for websites we build. SaaS products use product subdomains — custom domain mapping available on request.', 'custom domain, subdomain, url'],
            ['Do you provide source code?', 'SaaS products are subscription-based. Source code licensing for custom projects is negotiable in enterprise contracts.', 'source code, ownership, code'],
            ['What technology stack do you use?', 'Laravel (backend), React (frontend), MySQL, secure cloud hosting. Ideal for scalable business applications.', 'technology, stack, laravel, react'],
            ['Can you build a hospital management system?', 'Yes via Custom ERP Development. We also have blog resources on hospital software — see /blog.', 'hospital, clinic, healthcare erp'],
            ['Do you build eCommerce websites?', 'Yes. Website Development covers eCommerce, business, corporate, and landing pages.', 'ecommerce, online shop, store website'],
            ['Can you integrate with Tally?', 'Accounting integrations including Tally can be built via API Integration Services. Share your Tally version and requirements.', 'tally, accounting, integration'],
            ['Is there a student mobile app?', 'Study Point is web-responsive on mobile. Dedicated student/parent apps can be built via Mobile App Development.', 'student app, parent app, mobile'],
            ['How long does custom software take?', 'Timeline depends on scope — typically 4–16 weeks for MVPs. Free consultation provides a project estimate.', 'timeline, how long, delivery time'],
            ['Do you sign NDA?', 'Yes. NDAs can be signed before detailed project discussions.', 'nda, confidentiality, agreement'],
            ['Can I pay offline or by bank transfer?', 'Product purchases use Razorpay online. Bank transfer may be arranged for enterprise deals — contact sales.', 'offline payment, bank transfer, neft'],
            ['Do you provide AMC after go-live?', 'Yes. Software Maintenance & Support covers bug fixes, updates, and enhancements.', 'amc, annual maintenance, support contract'],
            ['What happens when my trial ends?', 'After 14-day trial, subscribe to a paid plan to continue. Data is retained — contact support if you need extension.', 'trial end, trial expired, after trial'],
            ['Can I export my data?', 'Data export can be arranged. Contact support for export format and process.', 'export data, download data, backup export'],
            ['Do you comply with data privacy laws?', 'We follow security best practices. Read our Privacy Policy at /privacy or contact us for data requests.', 'privacy, gdpr, data protection, compliance'],
            ['Can you migrate from Excel?', 'Yes. We help migrate student, inventory, or billing data from Excel/legacy systems during implementation.', 'excel import, spreadsheet, migrate excel'],
            ['Do you offer white-label SaaS?', 'White-label and reseller arrangements available for SaaS projects — contact sales.', 'white label, reseller, partner'],
            ['Can I get a PDF fee receipt?', 'Fee receipts and GST invoices are generated within the products. Configuration depends on your plan.', 'receipt, pdf, fee receipt'],
            ['Does pharmacy software support barcode scanning?', 'Yes. Medical Store Management Software includes barcode support.', 'barcode, scanner, pharmacy scan'],
            ['Can I manage staff permissions?', 'Yes. Role-based access control is built into our ERP products and custom solutions.', 'roles, permissions, rbac, staff access'],
            ['Do you support SMS alerts?', 'SMS integration available via API Integration Services and product notification modules.', 'sms, text message, alert'],
            ['What is your refund policy?', 'Contact support@softkatta.in with your order number for refund inquiries. See /terms for subscription and cancellation terms.', 'refund policy, cancellation policy'],
            ['Can I schedule a callback?', 'Yes. Use chatbot Callback Request or call +91 7038452357 during business hours.', 'callback, call back, phone call'],
            ['Do you work with startups?', 'Yes. We serve startups with affordable SaaS products and scalable custom development.', 'startup, small business, new business'],
            ['Can I see a product screenshot?', 'Visit /products and open any product page for screenshots, demo video, and feature list.', 'screenshot, preview, image'],
        ];

        $faqs = [];
        foreach ($questions as $i => [$q, $a, $kw]) {
            $faqs[] = [
                'question' => $q,
                'answer' => $a."\n\nContact: {$contact}",
                'keywords' => $kw,
                'category' => 'general',
                'sort_order' => 200 + $i,
            ];
        }

        return $faqs;
    }

    private function seedPortalFaqs(): void
    {
        foreach ($this->portalFaqCatalog() as $faq) {
            $this->upsertFaq($faq);
        }
    }

    /** @return list<array<string, mixed>> */
    private function portalFaqCatalog(): array
    {
        return array_merge(
            $this->employeePortalFaqs(),
            $this->employeePortalFaqsMarathi(),
            $this->clientPortalFaqs(),
            $this->adminPortalFaqs(),
            $this->hrPortalFaqs(),
        );
    }

    /** @return list<array<string, mixed>> */
    private function employeePortalFaqs(): array
    {
        $base = ['category' => 'portal_employee', 'language' => 'en'];

        return [
            array_merge($base, [
                'question' => 'How do I view my attendance?',
                'answer' => "To view your attendance records:\n\n1. Login to the Employee portal\n2. Open the sidebar → Attendance (/employee/attendance)\n3. Your past attendance entries appear in the table below the form\n\nYou can see date, check-in, check-out, work mode, and status for each day.",
                'keywords' => 'topic:attendance_view, view attendance, get attendance, attendance record, check attendance, employee portal, attendance paha, attendance dis, attendance bagha',
                'sort_order' => 401,
            ]),
            array_merge($base, [
                'question' => 'How do I mark attendance?',
                'answer' => "To mark your daily attendance:\n\n1. Go to Employee portal → Attendance (/employee/attendance)\n2. Select Date (defaults to today)\n3. Choose Work mode — Office, Remote, or Hybrid\n4. Enter Check in and Check out times\n5. Add optional notes\n6. Click Submit\n\nHR reviews your records in the admin/HR portal.",
                'keywords' => 'topic:attendance_mark, mark attendance, submit attendance, check in, check out, daily attendance, attendance mark, hajeri, hajeri mark, attendance kas mark, attendance kase mark',
                'sort_order' => 402,
            ]),
            array_merge($base, [
                'question' => 'How do I create a task?',
                'answer' => "To create a personal task:\n\n1. Open Employee portal → My Tasks (/employee/tasks)\n2. Click the \"Add task\" button (top right)\n3. Fill in Title, Description, Status, Priority, and Due date\n4. Click Save\n\nYou can edit or delete tasks from the same page using the row actions.",
                'keywords' => 'topic:tasks, create task, add task, new task, manage tasks, my tasks',
                'sort_order' => 403,
            ]),
            array_merge($base, [
                'question' => 'How do I view my tasks?',
                'answer' => "All your tasks are listed at /employee/tasks.\n\nUse the Status and Priority filters to find tasks quickly.\n\nClick a row's edit icon to update a task, or the trash icon to delete it.",
                'keywords' => 'topic:tasks, view tasks, my tasks, task list, todo',
                'sort_order' => 404,
            ]),
            array_merge($base, [
                'question' => 'How do I apply for leave?',
                'answer' => "To apply for leave:\n\n1. Go to Employee portal → Leave application (/employee/leave)\n2. Click Apply for leave or New request\n3. Select leave type, start date, end date, and reason\n4. Submit the form\n\nTrack approval status on the same page. HR approves requests from the HR portal.",
                'keywords' => 'topic:leave, apply leave, leave application, request leave, submit leave',
                'sort_order' => 405,
            ]),
            array_merge($base, [
                'question' => 'How do I submit a timesheet?',
                'answer' => "To log your work hours:\n\n1. Open Employee portal → Timesheets (/employee/timesheets)\n2. Add a new entry with project/task, date, and hours worked\n3. Save the entry\n\nReview your submitted timesheets in the list on the same page.",
                'keywords' => 'topic:timesheets, timesheet, submit timesheet, log hours, time sheet',
                'sort_order' => 406,
            ]),
            array_merge($base, [
                'question' => 'How do I upload documents?',
                'answer' => "To upload employee documents:\n\n1. Go to Employee portal → Documents (/employee/documents)\n2. Click Upload or Add document\n3. Choose the file and document type\n4. Submit\n\nDownload or view uploaded documents from the documents list.",
                'keywords' => 'topic:documents, upload document, my documents, employee documents',
                'sort_order' => 407,
            ]),
            array_merge($base, [
                'question' => 'How do I raise a helpdesk ticket?',
                'answer' => "For IT or internal support:\n\n1. Open Employee portal → Help Desk (/employee/helpdesk)\n2. Click Create ticket or New request\n3. Enter subject, category, and description\n4. Submit\n\nTrack replies and status updates on the same page.",
                'keywords' => 'topic:helpdesk, help desk, raise ticket, it support, support request',
                'sort_order' => 408,
            ]),
            array_merge($base, [
                'question' => 'How do I apply for resignation?',
                'answer' => "To submit a resignation request:\n\n1. Go to Employee portal → Resignation (/employee/resignation)\n2. Fill in last working date and reason\n3. Submit the form\n\nHR will review and update the status. Contact HR for any questions.",
                'keywords' => 'topic:resignation, resignation, apply resignation, leave job',
                'sort_order' => 409,
            ]),
            array_merge($base, [
                'question' => 'How do I view my projects?',
                'answer' => "Your assigned projects are at /employee/projects.\n\nOpen the page from the sidebar to see project name, status, timeline, and your role on each project.",
                'keywords' => 'topic:projects, my projects, view projects, employee projects',
                'sort_order' => 410,
            ]),
            array_merge($base, [
                'question' => 'How do I change my password in the employee portal?',
                'answer' => "To change your password:\n\n1. Open Employee portal → Change Password (/employee/change-password)\n2. Enter current password and new password\n3. Confirm and save\n\nFor 2FA settings, go to Security (/employee/security).",
                'keywords' => 'topic:password, change password, reset password, employee login',
                'sort_order' => 411,
            ]),
            array_merge($base, [
                'question' => 'How do I use the chatbot?',
                'answer' => "To chat with SoftKatta Mind:\n\n1. Type your question in the message box at the bottom\n2. Press Send or Enter\n3. You can ask in English or Marathi — e.g. \"attendance kase mark karu?\", \"task kas create karu?\"\n4. Tap quick topics on the home screen (Attendance, Tasks, Leave, etc.)\n5. Use the ← back button to return to topics\n\nI'm here to guide you through the Employee portal step by step.",
                'keywords' => 'topic:chat_help, use chatbot, how to chat, chat kas, chat kase, chatbot kas, chat karu, chatbot cha use, mi chat',
                'sort_order' => 412,
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function employeePortalFaqsMarathi(): array
    {
        $base = ['category' => 'portal_employee', 'language' => 'mr'];

        return [
            array_merge($base, [
                'question' => 'Attendance kase mark karu?',
                'answer' => "दैनिक attendance mark करण्यासाठी:\n\n1. Employee portal → Attendance (/employee/attendance) उघडा\n2. Date निवडा (default: आज)\n3. Work mode — Office, Remote किंवा Hybrid\n4. Check in आणि Check out वेळ भरा\n5. Submit दाबा\n\nHR admin/HR portal मध्ये records review करते.",
                'keywords' => 'topic:attendance_mark, attendance mark, hajeri, hajeri mark, attendance kas, attendance kase, attendance ghalaycha',
                'sort_order' => 413,
            ]),
            array_merge($base, [
                'question' => 'Attendance kase pahu?',
                'answer' => "तुमचे attendance records पाहण्यासाठी:\n\n1. Employee portal login करा\n2. Sidebar → Attendance (/employee/attendance)\n3. Form खाली table मध्ये मागील entries दिसतील\n\nDate, check-in, check-out, work mode आणि status पाहता येईल.",
                'keywords' => 'topic:attendance_view, attendance paha, attendance dis, attendance bagha, attendance record',
                'sort_order' => 414,
            ]),
            array_merge($base, [
                'question' => 'Task kas create karu?',
                'answer' => "Task तयार करण्यासाठी:\n\n1. Employee portal → My Tasks (/employee/tasks)\n2. \"Add task\" button दाबा\n3. Title, Description, Status, Priority, Due date भरा\n4. Save करा\n\nEdit/Delete साठी row actions वापरा.",
                'keywords' => 'topic:tasks, task create, task add, task kas, task kase, task tayar',
                'sort_order' => 415,
            ]),
            array_merge($base, [
                'question' => 'Chat kas karu / chatbot kas vapru?',
                'answer' => "SoftKatta Mind chatbot वापरण्यासाठी:\n\n1. खाली message box मध्ये प्रश्न टाइप करा\n2. Send किंवा Enter दाबा\n3. Marathi किंवा English मध्ये विचारा — \"attendance kase mark karu?\", \"leave kas apply karu?\"\n4. Home screen वरून topic निवडा (Attendance, Tasks, Leave)\n5. ← back button ने topics कडे परत जा\n\nमी Employee portal step-by-step समजावतो.",
                'keywords' => 'topic:chat_help, chat kas, chat kase, chatbot kas, chat karu, mi chat, chatbot vapru',
                'sort_order' => 416,
            ]),
            array_merge($base, [
                'question' => 'Leave kas apply karu?',
                'answer' => "Leave apply करण्यासाठी:\n\n1. Employee portal → Leave application (/employee/leave)\n2. Apply for leave / New request दाबा\n3. Leave type, start date, end date, reason भरा\n4. Submit करा\n\nStatus त्याच page वर track करा. HR HR portal मधून approve करते.",
                'keywords' => 'topic:leave, leave apply, leave kas, leave kase, rja, raja',
                'sort_order' => 417,
            ]),
            array_merge($base, [
                'question' => 'Timesheet kas submit karu?',
                'answer' => "Work hours log करण्यासाठी:\n\n1. Employee portal → Timesheets (/employee/timesheets)\n2. नवीन entry add करा — project/task, date, hours\n3. Save करा\n\nSubmitted timesheets list मध्ये review करा.",
                'keywords' => 'topic:timesheets, timesheet kas, timesheet kase, vel register, kaamacha vel',
                'sort_order' => 418,
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function clientPortalFaqs(): array
    {
        $base = ['category' => 'portal_client', 'language' => 'en'];

        return [
            array_merge($base, [
                'question' => 'How do I view my orders?',
                'answer' => "To view your purchase history:\n\n1. Login to the Client dashboard\n2. Open Orders (/dashboard/orders)\n3. Browse order number, product, amount, date, and status\n\nClick an order for full details including payment status.",
                'keywords' => 'topic:orders, my orders, view orders, order history, purchase history',
                'sort_order' => 421,
            ]),
            array_merge($base, [
                'question' => 'How do I manage my subscriptions?',
                'answer' => "Manage subscriptions at /dashboard/subscriptions:\n\n• View active and expired plans\n• See renewal dates\n• Upgrade or renew before expiry\n\nContact support@softkatta.in if you need help changing plans.",
                'keywords' => 'topic:subscriptions, manage subscription, renew subscription, my subscription, subscription plan',
                'sort_order' => 422,
            ]),
            array_merge($base, [
                'question' => 'How do I download invoices?',
                'answer' => "To get GST invoices:\n\n1. Go to Client dashboard → Invoices (/dashboard/invoices)\n2. Find the invoice for your order\n3. Click Download or View\n\nInvoices are also emailed after successful payment.",
                'keywords' => 'topic:invoices, download invoice, my invoices, gst invoice, get invoice',
                'sort_order' => 423,
            ]),
            array_merge($base, [
                'question' => 'How do I view license keys?',
                'answer' => "Your product license keys are at /dashboard/licenses.\n\nAfter purchase, each active subscription shows its license/activation key. Copy the key to activate your software product.",
                'keywords' => 'topic:licenses, license key, license keys, activation key, product license',
                'sort_order' => 424,
            ]),
            array_merge($base, [
                'question' => 'How do I raise a support ticket as a client?',
                'answer' => "To get help with your subscription:\n\n1. Open Client dashboard → Support (/dashboard/support)\n2. Click Create ticket\n3. Enter subject and describe your issue\n4. Submit\n\nTrack replies in the same portal. We respond within 24 business hours.",
                'keywords' => 'topic:support, support ticket, raise ticket, create ticket, client support',
                'sort_order' => 425,
            ]),
            array_merge($base, [
                'question' => 'How do I update my client profile?',
                'answer' => "Update your account details at /dashboard/profile:\n\n• Edit name, phone, and company info\n• Upload or change profile photo\n• Save changes\n\nPassword changes are at /dashboard/change-password.",
                'keywords' => 'topic:profile, update profile, my profile, client profile, account settings',
                'sort_order' => 426,
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function adminPortalFaqs(): array
    {
        $base = ['category' => 'portal_admin', 'language' => 'en'];

        return [
            array_merge($base, [
                'question' => 'How do I manage users in admin?',
                'answer' => "To manage platform users:\n\n1. Login as Admin → Users (/admin/users)\n2. View all users with role, status, and contact info\n3. Click Add user to create a new account\n4. Assign role (Client, Employee, HR, Admin) and permissions\n5. Edit or deactivate users from row actions",
                'keywords' => 'topic:users, manage users, add user, create user, user management, admin users',
                'sort_order' => 441,
            ]),
            array_merge($base, [
                'question' => 'How do I add a product in admin?',
                'answer' => "To add or edit products:\n\n1. Go to Admin → Products (/admin/products)\n2. Click Add product\n3. Fill name, slug, description, pricing, and features\n4. Save and publish\n\nManage categories at /admin/categories and plans at /admin/plans.",
                'keywords' => 'topic:products, add product, manage products, create product, admin products',
                'sort_order' => 442,
            ]),
            array_merge($base, [
                'question' => 'How do I manage subscriptions as admin?',
                'answer' => "Admin subscription management is at /admin/subscriptions:\n\n• View all active and expired subscriptions\n• Filter by tenant, product, or status\n• Extend, cancel, or update subscription details\n\nLicense keys are managed at /admin/licenses.",
                'keywords' => 'topic:subscriptions, manage subscriptions, admin subscriptions, subscription management',
                'sort_order' => 443,
            ]),
            array_merge($base, [
                'question' => 'How do I view orders and payments?',
                'answer' => "Sales overview in admin:\n\n• Orders: /admin/orders — all customer orders\n• Payments: /admin/payments — Razorpay transactions\n• Invoices: /admin/invoices — GST invoices\n\nUse filters and search to find specific records.",
                'keywords' => 'topic:orders, admin orders, manage orders, view payments, order management, payments',
                'sort_order' => 444,
            ]),
            array_merge($base, [
                'question' => 'How do I manage chatbot settings?',
                'answer' => "To configure the website chatbot:\n\n1. Go to Admin → Chatbot (/admin/chatbot)\n2. Edit welcome message, business hours, and quick replies\n3. Manage FAQ entries and categories\n4. Enable/disable the widget\n\nRun ChatbotKnowledgeSeeder after bulk FAQ updates.",
                'keywords' => 'topic:chatbot, chatbot settings, manage chatbot, chatbot faq, train chatbot',
                'sort_order' => 445,
            ]),
            array_merge($base, [
                'question' => 'How do I broadcast notifications?',
                'answer' => "To send announcements to users:\n\n1. Admin → Broadcasts (/admin/notifications)\n2. Create a new broadcast with title and message\n3. Select target audience (all users, clients, employees, etc.)\n4. Send or schedule\n\nEmployees see announcements at /employee/announcements.",
                'keywords' => 'topic:notifications, broadcast, send notification, broadcast notification, announce',
                'sort_order' => 446,
            ]),
            array_merge($base, [
                'question' => 'How do I manage roles and permissions?',
                'answer' => "Access control in admin:\n\n• Roles: /admin/roles — define role names\n• Permissions: /admin/permissions — granular access rules\n• Assign roles when creating/editing users at /admin/users\n\nPortal menu visibility can be customized at /admin/portal-menus.",
                'keywords' => 'topic:roles, manage roles, permissions, role management, assign role',
                'sort_order' => 447,
            ]),
            array_merge($base, [
                'question' => 'How do I manage tenants?',
                'answer' => "Multi-tenant management at /admin/tenants:\n\n• Create tenants for organizations\n• Link subscriptions and users to tenants\n• View tenant usage and status\n\nUseful for enterprise or multi-branch deployments.",
                'keywords' => 'topic:tenants, manage tenants, add tenant, tenant management',
                'sort_order' => 448,
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function hrPortalFaqs(): array
    {
        $base = ['category' => 'portal_hr', 'language' => 'en'];

        return [
            array_merge($base, [
                'question' => 'How do I manage employees in HR portal?',
                'answer' => "HR employee management:\n\n1. Login to HR portal → Employees (/hr/employees)\n2. View all employee records\n3. Add new employees or update existing profiles\n4. Link employees to company roles and departments\n\nUser accounts are managed at /hr/users.",
                'keywords' => 'topic:employees, manage employees, employee list, add employee, hr employees',
                'sort_order' => 461,
            ]),
            array_merge($base, [
                'question' => 'How do I approve leave requests?',
                'answer' => "To review and approve leave:\n\n1. Go to HR portal → Leave Requests (/hr/leave)\n2. View pending requests with dates and reason\n3. Click Approve or Reject\n4. Employee sees updated status at /employee/leave",
                'keywords' => 'topic:leave, approve leave, leave requests, leave approval, review leave',
                'sort_order' => 462,
            ]),
            array_merge($base, [
                'question' => 'How do I review employee attendance?',
                'answer' => "HR attendance review:\n\n1. Open HR portal → Attendance (/hr/attendance)\n2. Filter by employee, date range, or status\n3. Review check-in/out times and work mode\n4. Approve or flag records as needed\n\nEmployees mark attendance at /employee/attendance.",
                'keywords' => 'topic:attendance, review attendance, employee attendance, attendance report, hr attendance',
                'sort_order' => 463,
            ]),
            array_merge($base, [
                'question' => 'How do I post job openings?',
                'answer' => "To create a job vacancy:\n\n1. HR portal → Job Openings (/hr/openings)\n2. Click Add opening\n3. Enter title, department, description, and requirements\n4. Publish — the role appears on /careers\n\nManage applications at /hr/applications.",
                'keywords' => 'topic:openings, job opening, post job, create opening, vacancy, recruitment',
                'sort_order' => 464,
            ]),
            array_merge($base, [
                'question' => 'How do I review job applications?',
                'answer' => "Application review workflow:\n\n1. Go to HR portal → Applications (/hr/applications)\n2. View applicants for each opening\n3. Open resume and application details\n4. Update status — Shortlisted, Interview, Rejected, Hired\n\nCandidates apply via /careers on the public website.",
                'keywords' => 'topic:applications, job applications, review applications, applicant, resume review',
                'sort_order' => 465,
            ]),
        ];
    }

    private function seedMultilingualFaqs(): void
    {
        foreach ($this->multilingualFaqCatalog() as $faq) {
            $this->upsertFaq($faq);
        }
    }

    /** @return list<array<string, mixed>> */
    private function multilingualFaqCatalog(): array
    {
        $phone = '+91 7038452357';
        $email = 'support@softkatta.in';

        $mr = [
            ['SoftKatta Solutions म्हणजे काय?', "SoftKatta ही Talni, Nanded, Maharashtra मधील custom software development company आहे. आम्ही ERP, web apps आणि mobile apps तयार करतो.", 'softkatta, company, माहिती', 'company', 300],
            ['SoftKatta कोणती उत्पादने देते?', "Study Point Management Software, Medical Store Management Software, Nursery School Management Software आणि Custom Software Development.", 'उत्पादने, products, erp', 'products', 301],
            ['Study Point software कशासाठी आहे?', 'शाळा, coaching institutes आणि training centers साठी — admissions, attendance, fees, batches, exams.', 'study point, शाळा, coaching', 'products', 302],
            ['Medical Store software किती किंमतीत?', '₹1,999/महिना पासून Starter plan. Professional ₹4,999/महिना. /pricing पहा.', 'medical store, pharmacy, किंमत', 'pricing', 303],
            ['Nursery School software मध्ये काय आहे?', 'Admissions, attendance, fees, parent portal, notifications आणि reports.', 'nursery, preschool', 'products', 304],
            ['विनामूल्य trial आहे का?', 'होय! सर्व उत्पादांवर 14-दिवस विनामूल्य trial — credit card नको. /register वर account तयार करा.', 'trial, free, विनामूल्य', 'products', 305],
            ['किंमत कशी मिळेल?', "Study Point: ₹2,999/महिना पासून\nMedical Store: ₹1,999/महिना\nNursery School: ₹1,499/महिना\n\n/pricing", 'pricing, किंमत, price', 'pricing', 306],
            ['डेमो कसा बुक करू?', 'Chatbot मध्ये "Book Demo" निवडा किंवा /contact वर form भरा. किंवा '.$phone.' वर call करा.', 'demo, डेमो', 'pricing', 307],
            ['व्यवसायाचे वेळ?', "सोमवार – शनिवार: सकाळी 9 – संध्याकाळी 7\nरविवार: बंद", 'वेळ, hours, timing', 'company', 308],
            ['संपर्क कसा करू?', "फोन: {$phone}\nEmail: {$email}\n/contact", 'contact, संपर्क, phone', 'company', 309],
            ['Custom software development करता का?', 'होय. Custom ERP, web आणि mobile apps. /services/custom-software-development', 'custom, development', 'services', 310],
            ['Support कसा मिळेल?', "Email: {$email}\nPhone: {$phone}\nLogged-in users: /dashboard/support", 'support, मदत', 'support', 311],
            ['Payment methods?', 'Razorpay — card, UPI, net banking. GST invoice auto-generate.', 'payment, razorpay, gst', 'billing', 312],
            ['Data backup आहे का?', 'होय. Paid plans वर automatic daily backup.', 'backup, data', 'technical', 313],
            ['Services कोणत्या?', 'Custom Software, ERP, Website, Mobile App, SaaS, Cloud, API Integration, UI/UX, Maintenance. /services', 'services, सेवा', 'services', 314],
        ];

        $hi = [
            ['SoftKatta Solutions क्या है?', 'SoftKatta Talni, Nanded, Maharashtra में एक custom software development company है। हम ERP, web apps और mobile apps बनाते हैं।', 'softkatta, company, कंपनी', 'company', 320],
            ['SoftKatta कौन से उत्पाद देता है?', 'Study Point Management Software, Medical Store Management Software, Nursery School Management Software और Custom Software Development।', 'उत्पाद, products', 'products', 321],
            ['Study Point software किसके लिए है?', 'Schools, coaching institutes — admissions, attendance, fees, batches, exams।', 'study point, school', 'products', 322],
            ['Medical Store software की कीमत?', '₹1,999/माह से Starter plan। Professional ₹4,999/माह। /pricing देखें।', 'medical store, pharmacy, price', 'pricing', 323],
            ['Nursery School software में क्या है?', 'Admissions, attendance, fees, parent portal, notifications, reports।', 'nursery, preschool', 'products', 324],
            ['Free trial है?', 'हाँ! सभी products पर 14-दिन free trial — credit card नहीं चाहिए। /register', 'trial, free', 'products', 325],
            ['Pricing क्या है?', "Study Point: ₹2,999/माह से\nMedical Store: ₹1,999/माह\nNursery School: ₹1,499/माह\n\n/pricing", 'pricing, price, कीमत', 'pricing', 326],
            ['Demo कैसे book करें?', 'Chatbot में "Book Demo" चुनें या /contact form भरें। Call: '.$phone, 'demo, डेमो', 'pricing', 327],
            ['Business hours?', "सोम – शनि: 9 AM – 7 PM IST\nरविवार: बंद", 'hours, timing, समय', 'company', 328],
            ['Contact कैसे करें?', "Phone: {$phone}\nEmail: {$email}\n/contact", 'contact, संपर्क', 'company', 329],
            ['Custom software development?', 'हाँ। Custom ERP, web, mobile apps। /services/custom-software-development', 'custom, development', 'services', 330],
            ['Support कैसे मिले?', "Email: {$email}\nPhone: {$phone}\n/dashboard/support (logged in)", 'support, help', 'support', 331],
            ['Payment methods?', 'Razorpay — card, UPI, net banking। GST invoice auto।', 'payment, razorpay', 'billing', 332],
            ['Data backup included?', 'हाँ। Paid plans पर automatic daily backup।', 'backup, data', 'technical', 333],
            ['Services list?', 'Custom Software, ERP, Website, Mobile, SaaS, Cloud, API, UI/UX, Maintenance। /services', 'services, सेवाएं', 'services', 334],
        ];

        $faqs = [];
        foreach ($mr as $row) {
            $faqs[] = ['question' => $row[0], 'answer' => $row[1], 'keywords' => $row[2], 'category' => $row[3], 'sort_order' => $row[4], 'language' => 'mr'];
        }
        foreach ($hi as $row) {
            $faqs[] = ['question' => $row[0], 'answer' => $row[1], 'keywords' => $row[2], 'category' => $row[3], 'sort_order' => $row[4], 'language' => 'hi'];
        }

        return $faqs;
    }
}
