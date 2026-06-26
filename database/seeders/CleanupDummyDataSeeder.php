<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;

class CleanupDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()->delete();
        ProductCategory::query()->delete();
        Service::query()->delete();
        Testimonial::query()->delete();
        Faq::query()->delete();
        HeroSlide::query()->delete();
        User::where('email', 'admin@softkatta.com')->delete();

        $keys = [
            'company_name', 'company_tagline', 'company_address', 'company_phone', 'company_website',
            'company_initials', 'billing_email', 'gst_number', 'invoice_account_no', 'invoice_account_name',
            'invoice_ifsc_code', 'upi_vpa', 'invoice_branch', 'invoice_terms', 'invoice_signatory',
            'support_email', 'maintenance_badge', 'maintenance_message',
        ];

        foreach ($keys as $key) {
            Setting::where('key', $key)->update(['value' => '']);
        }
    }
}
