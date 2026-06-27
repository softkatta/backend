<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $erpCategory = ProductCategory::query()->firstOrCreate(
            ['slug' => 'erp'],
            [
                'name' => 'ERP',
                'description' => 'Business operations and accounting tools.',
                'icon' => 'building-2',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $crmCategory = ProductCategory::query()->firstOrCreate(
            ['slug' => 'crm'],
            [
                'name' => 'CRM',
                'description' => 'Sales and customer relationship management.',
                'icon' => 'users',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        $billingProduct = Product::query()->firstOrCreate(
            ['slug' => 'softkatta-billing'],
            [
                'category_id' => $erpCategory->id,
                'name' => 'SoftKatta Billing',
                'description' => 'GST billing, inventory and purchase tracking for SMEs.',
                'overview' => 'All-in-one billing and inventory software built for Indian businesses.',
                'is_active' => true,
                'has_free_trial' => true,
                'trial_days' => 14,
                'sort_order' => 1,
                'meta' => [
                    'tagline' => 'Fast billing with GST-ready invoices',
                ],
            ],
        );

        $crmProduct = Product::query()->firstOrCreate(
            ['slug' => 'softkatta-crm'],
            [
                'category_id' => $crmCategory->id,
                'name' => 'SoftKatta CRM',
                'description' => 'Leads, follow-ups and sales pipeline management.',
                'overview' => 'Track every lead and improve conversion with a simple CRM workflow.',
                'is_active' => true,
                'has_free_trial' => false,
                'trial_days' => 0,
                'sort_order' => 2,
                'meta' => [
                    'tagline' => 'Close more deals with less follow-up effort',
                ],
            ],
        );

        Plan::query()->firstOrCreate(
            ['product_id' => $billingProduct->id, 'slug' => 'starter-monthly'],
            [
                'name' => 'Starter',
                'description' => 'Best for small shops and startups.',
                'price' => 999,
                'billing_cycle' => 'monthly',
                'features' => ['GST invoices', 'Inventory tracking', 'Basic reports'],
                'is_popular' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Plan::query()->firstOrCreate(
            ['product_id' => $crmProduct->id, 'slug' => 'growth-monthly'],
            [
                'name' => 'Growth',
                'description' => 'For teams managing active sales pipelines.',
                'price' => 1499,
                'billing_cycle' => 'monthly',
                'features' => ['Lead pipeline', 'Task reminders', 'Team dashboard'],
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Service::query()->firstOrCreate(
            ['slug' => 'implementation-support'],
            [
                'name' => 'Implementation Support',
                'description' => 'Setup assistance, onboarding and team training.',
                'icon' => 'wrench',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Service::query()->firstOrCreate(
            ['slug' => 'custom-integration'],
            [
                'name' => 'Custom Integrations',
                'description' => 'Connect your existing tools and workflows with SoftKatta.',
                'icon' => 'plug',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        HeroSlide::query()->updateOrCreate(
            ['sort_order' => 1],
            [
                'title' => 'Run Your Business on One Platform',
                'image' => 'https://picsum.photos/seed/softkatta-hero-1/1600/1000',
                'alt_text' => 'SoftKatta dashboard preview',
                'is_active' => true,
            ],
        );

        HeroSlide::query()->updateOrCreate(
            ['sort_order' => 2],
            [
                'title' => 'Invoice Faster, Track Better',
                'image' => 'https://picsum.photos/seed/softkatta-hero-2/1600/1000',
                'alt_text' => 'Billing and reports overview',
                'is_active' => true,
            ],
        );

        HeroSlide::query()
            ->where('image', 'not like', 'http%')
            ->get()
            ->each(function (HeroSlide $slide): void {
                $seed = $slide->sort_order > 0 ? $slide->sort_order : $slide->id;
                $slide->update([
                    'image' => 'https://picsum.photos/seed/softkatta-hero-'.$seed.'/1600/1000',
                ]);
            });

        Testimonial::query()->firstOrCreate(
            ['name' => 'Rahul Patil', 'company' => 'Patil Traders'],
            [
                'designation' => 'Owner',
                'content' => 'SoftKatta made billing and stock tracking very simple for our team.',
                'rating' => 5,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Testimonial::query()->firstOrCreate(
            ['name' => 'Sneha Kulkarni', 'company' => 'SK Distribution'],
            [
                'designation' => 'Operations Manager',
                'content' => 'The CRM and invoice modules helped us reduce manual follow-up work.',
                'rating' => 5,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        Faq::query()->firstOrCreate(
            ['question' => 'Can I start with a trial?'],
            [
                'category' => 'Billing',
                'answer' => 'Yes, trial-enabled products can be started instantly after signup.',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        Faq::query()->firstOrCreate(
            ['question' => 'Do I get GST invoices?'],
            [
                'category' => 'Billing',
                'answer' => 'Yes, GST invoices are generated automatically when GST is configured.',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );
    }
}
