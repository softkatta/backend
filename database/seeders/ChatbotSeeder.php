<?php

namespace Database\Seeders;

use App\Models\ChatbotCategory;
use App\Models\ChatbotFaq;
use App\Models\ChatbotSetting;
use App\Services\ChatbotSettingsService;
use Illuminate\Database\Seeder;

class ChatbotSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = app(ChatbotSettingsService::class)->defaults();

        foreach ($defaults as $key => $value) {
            ChatbotSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
            );
        }

        $categories = [
            ['name' => 'Products', 'slug' => 'products', 'sort_order' => 1],
            ['name' => 'Pricing', 'slug' => 'pricing', 'sort_order' => 2],
            ['name' => 'Support', 'slug' => 'support', 'sort_order' => 3],
            ['name' => 'General', 'slug' => 'general', 'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            ChatbotCategory::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['is_active' => true]),
            );
        }

        $faqs = [
            [
                'question' => 'What products does SoftKatta offer?',
                'answer' => 'We offer Study Point Management Software, Medical Store Management Software, Nursery School Management Software, and Custom Software Development.',
                'keywords' => 'products, erp, software, school, medical, gym',
                'language' => 'en',
                'category' => 'products',
                'sort_order' => 1,
            ],
            [
                'question' => 'How can I get pricing?',
                'answer' => "Product pricing is on /pricing:\n• Study Point from ₹2,999/mo\n• Medical Store from ₹1,999/mo\n• Nursery School from ₹1,499/mo\n\nCustom projects: contact +91 7038452357 or support@softkatta.in",
                'keywords' => 'pricing, price, cost, quote',
                'language' => 'en',
                'category' => 'pricing',
                'sort_order' => 2,
            ],
            [
                'question' => 'Do you provide custom software development?',
                'answer' => 'Yes. SoftKatta builds custom ERP, web, and mobile applications tailored to your business needs.',
                'keywords' => 'custom, development, software, app',
                'language' => 'en',
                'category' => 'products',
                'sort_order' => 3,
            ],
            [
                'question' => 'What are your business hours?',
                'answer' => "Monday – Saturday: 9:00 AM – 7:00 PM\nSunday: Closed",
                'keywords' => 'hours, timing, open, closed',
                'language' => 'en',
                'category' => 'general',
                'sort_order' => 4,
            ],
            [
                'question' => 'SoftKatta कोणती उत्पादने देते?',
                'answer' => 'आम्ही Study Point Management Software, Medical Store Management Software, Nursery School Management Software आणि Custom Software Development solutions देतो.',
                'keywords' => 'उत्पादने, erp, software',
                'language' => 'mr',
                'category' => 'products',
                'sort_order' => 1,
            ],
            [
                'question' => 'SoftKatta कौन से उत्पाद प्रदान करता है?',
                'answer' => 'हम Study Point Management Software, Medical Store Management Software, Nursery School Management Software और Custom Software Development solutions प्रदान करते हैं।',
                'keywords' => 'उत्पाद, erp, software',
                'language' => 'hi',
                'category' => 'products',
                'sort_order' => 1,
            ],
        ];

        foreach ($faqs as $faq) {
            ChatbotFaq::updateOrCreate(
                [
                    'question' => $faq['question'],
                    'language' => $faq['language'],
                ],
                array_merge($faq, ['is_active' => true]),
            );
        }
    }
}
