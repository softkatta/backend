<?php

namespace Database\Seeders;

use App\Models\Integration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(CompanyRoleSeeder::class);
        $this->call(PortalMenuSeeder::class);
        $this->call(CompanyRoleMenuSeeder::class);
        $this->call(SeoContentSeeder::class);
        $this->call(ContentSeeder::class);
        $this->call(CouponOfferSeeder::class);
        $this->call(ChatbotSeeder::class);

        // Create / repair Super Admin from env
        $superAdminEmail = (string) env('SUPER_ADMIN_EMAIL', 'admin@softkatta.com');
        $superAdminName = (string) env('SUPER_ADMIN_NAME', 'Super Admin');
        $superAdminPassword = trim((string) env('SUPER_ADMIN_PASSWORD', ''));
        if ($superAdminPassword === '') {
            $superAdminPassword = 'Admin@123';
        }

        $superAdmin = User::firstOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => $superAdminName,
                'password' => Hash::make($superAdminPassword),
                'role' => \App\Enums\UserRole::SuperAdmin,
                'is_active' => true,
            ]
        );

        // Repair role/active. Also reset password when env provides one (or account was just created with blank env).
        $repair = [
            'name' => $superAdmin->name ?: $superAdminName,
            'role' => \App\Enums\UserRole::SuperAdmin,
            'is_active' => true,
        ];

        if (trim((string) env('SUPER_ADMIN_PASSWORD', '')) !== '') {
            $repair['password'] = Hash::make($superAdminPassword);
        }

        $superAdmin->forceFill($repair)->save();
        $superAdmin->syncRoles(['super_admin']);

        $settings = [
            ['key' => 'company_name', 'value' => '', 'group' => 'general'],
            ['key' => 'company_tagline', 'value' => '', 'group' => 'general'],
            ['key' => 'company_address', 'value' => '', 'group' => 'general'],
            ['key' => 'company_phone', 'value' => '', 'group' => 'general'],
            ['key' => 'company_website', 'value' => '', 'group' => 'general'],
            ['key' => 'company_description', 'value' => '', 'group' => 'general'],
            ['key' => 'brand_short_name', 'value' => '', 'group' => 'general'],
            ['key' => 'company_logo', 'value' => '', 'group' => 'general'],
            ['key' => 'favicon', 'value' => '', 'group' => 'general'],
            ['key' => 'company_initials', 'value' => '', 'group' => 'general'],
            ['key' => 'billing_email', 'value' => '', 'group' => 'invoice'],
            ['key' => 'gst_number', 'value' => '', 'group' => 'invoice'],
            ['key' => 'gst_rate', 'value' => '18', 'group' => 'invoice'],
            ['key' => 'invoice_prefix', 'value' => 'INV', 'group' => 'invoice'],
            ['key' => 'invoice_number_start', 'value' => '1', 'group' => 'invoice'],
            ['key' => 'invoice_account_no', 'value' => '', 'group' => 'invoice'],
            ['key' => 'invoice_account_name', 'value' => '', 'group' => 'invoice'],
            ['key' => 'invoice_ifsc_code', 'value' => '', 'group' => 'invoice'],
            ['key' => 'upi_vpa', 'value' => '', 'group' => 'invoice'],
            ['key' => 'invoice_branch', 'value' => '', 'group' => 'invoice'],
            ['key' => 'invoice_terms', 'value' => '', 'group' => 'invoice'],
            ['key' => 'invoice_signatory', 'value' => '', 'group' => 'invoice'],
            ['key' => 'invoice_signature', 'value' => '', 'group' => 'invoice'],
            ['key' => 'support_email', 'value' => '', 'group' => 'general'],
            ['key' => 'default_currency', 'value' => 'INR', 'group' => 'general'],
            ['key' => 'maintenance_mode', 'value' => 'false', 'group' => 'maintenance'],
            ['key' => 'maintenance_page_type', 'value' => 'launch', 'group' => 'maintenance'],
            ['key' => 'maintenance_badge', 'value' => '', 'group' => 'maintenance'],
            ['key' => 'maintenance_message', 'value' => '', 'group' => 'maintenance'],
            ['key' => 'maintenance_image', 'value' => '', 'group' => 'maintenance'],
            ['key' => 'session_timeout_minutes', 'value' => '30', 'group' => 'security'],
            ['key' => 'ip_whitelisting', 'value' => 'false', 'group' => 'security'],
            ['key' => 'ip_whitelist', 'value' => '', 'group' => 'security'],
            ['key' => 'two_factor_login_enabled', 'value' => 'false', 'group' => 'security'],
            ['key' => 'allow_email_otp', 'value' => 'true', 'group' => 'security'],
            ['key' => 'allow_authenticator', 'value' => 'true', 'group' => 'security'],
            ['key' => 'allow_passkeys', 'value' => 'true', 'group' => 'security'],
            ['key' => 'enforce_2fa_all', 'value' => 'false', 'group' => 'security'],
            ['key' => 'enforce_2fa_roles', 'value' => '', 'group' => 'security'],
            ['key' => 'enforce_2fa_admins', 'value' => 'false', 'group' => 'security'],
            ['key' => 'enforce_2fa_clients', 'value' => 'false', 'group' => 'security'],
            ['key' => 'allow_users_disable_2fa', 'value' => 'true', 'group' => 'security'],
            ['key' => 'login_2fa_priority', 'value' => 'passkey,authenticator,email', 'group' => 'security'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        Integration::firstOrCreate(
            ['provider' => 'razorpay'],
            [
                'name' => 'Razorpay',
                'credentials' => ['key_id' => '', 'api_secret' => ''],
                'is_active' => false,
            ]
        );

        Integration::firstOrCreate(
            ['provider' => 'email_smtp'],
            [
                'name' => 'Email (SMTP)',
                'credentials' => [
                    'host' => '',
                    'port' => '587',
                    'username' => '',
                    'password' => '',
                    'encryption' => 'tls',
                    'from_address' => '',
                    'from_name' => '',
                ],
                'is_active' => false,
            ]
        );

        Integration::firstOrCreate(
            ['provider' => 'whatsapp'],
            [
                'name' => 'WhatsApp Business',
                'credentials' => [
                    'phone_number_id' => '',
                    'access_token' => '',
                    'api_version' => 'v21.0',
                ],
                'is_active' => false,
            ]
        );

        Integration::firstOrCreate(
            ['provider' => 'pusher'],
            [
                'name' => 'Pusher',
                'credentials' => [
                    'app_id' => '',
                    'key' => '',
                    'secret' => '',
                    'cluster' => 'ap2',
                    'scheme' => 'https',
                ],
                'is_active' => false,
            ]
        );

        Integration::firstOrCreate(
            ['provider' => 'stripe'],
            [
                'name' => 'Stripe',
                'credentials' => [
                    'publishable_key' => '',
                    'secret_key' => '',
                ],
                'is_active' => false,
            ]
        );
    }
}
