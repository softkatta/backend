<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class CouponOfferSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::query()->updateOrCreate(
            ['code' => 'SAVE20'],
            [
                'name' => '20% Off Launch Offer',
                'type' => 'percent',
                'value' => 20,
                'min_order_amount' => 999,
                'max_uses' => 500,
                'max_uses_per_user' => 1,
                'is_active' => true,
                'description' => 'Get 20% off on your first purchase. Min order ₹999.',
            ]
        );

        Coupon::query()->updateOrCreate(
            ['code' => 'WELCOME500'],
            [
                'name' => 'Welcome ₹500 Off',
                'type' => 'fixed',
                'value' => 500,
                'min_order_amount' => 1999,
                'max_uses' => 200,
                'max_uses_per_user' => 1,
                'is_active' => true,
                'description' => 'Flat ₹500 off on orders above ₹1999.',
            ]
        );

        Setting::query()->updateOrCreate(
            ['key' => 'site_offers'],
            [
                'group' => 'content',
                'value' => json_encode([
                    [
                        'id' => '1',
                        'text' => 'Launch offer: Use code SAVE20 for 20% off your first purchase',
                        'cta_label' => 'Shop now',
                        'cta_href' => '/products',
                        'active' => true,
                        'priority' => 1,
                    ],
                    [
                        'id' => '2',
                        'text' => 'Flat ₹500 off with WELCOME500 on orders above ₹1999',
                        'cta_label' => 'Browse',
                        'cta_href' => '/products',
                        'active' => true,
                        'priority' => 2,
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ]
        );
    }
}
