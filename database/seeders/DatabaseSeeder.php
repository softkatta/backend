<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Integration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Super Admin first (after roles exist) so it never stays as default "client".
        $this->seedSuperAdmin();

        $this->call(CompanyRoleSeeder::class);
        $this->call(PortalMenuSeeder::class);
        $this->call(CompanyRoleMenuSeeder::class);
        $this->call(SeoContentSeeder::class);
        $this->call(ContentSeeder::class);
        $this->call(CouponOfferSeeder::class);
        $this->call(ChatbotSeeder::class);

        // Repair again after other seeders in case anything touched the admin row.
        $this->seedSuperAdmin();

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
            ['key' => 'social_facebook', 'value' => '', 'group' => 'general'],
            ['key' => 'social_instagram', 'value' => '', 'group' => 'general'],
            ['key' => 'social_linkedin', 'value' => '', 'group' => 'general'],
            ['key' => 'social_twitter', 'value' => '', 'group' => 'general'],
            ['key' => 'social_youtube', 'value' => '', 'group' => 'general'],
            ['key' => 'social_whatsapp', 'value' => '', 'group' => 'general'],
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
            ['key' => 'recaptcha_enabled', 'value' => 'false', 'group' => 'security'],
            ['key' => 'recaptcha_site_key', 'value' => '', 'group' => 'security'],
            ['key' => 'recaptcha_secret_key', 'value' => '', 'group' => 'security'],
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

    private function seedSuperAdmin(): void
    {
        // Use config() so this works even when `php artisan config:cache` is active.
        $email = strtolower(trim((string) config('softkatta.super_admin.email', 'admin@softkatta.com')));
        $name = trim((string) config('softkatta.super_admin.name', 'Super Admin')) ?: 'Super Admin';
        $password = trim((string) config('softkatta.super_admin.password', ''));
        if ($password === '') {
            $password = 'Admin@123';
        }

        if ($email === '') {
            $this->command?->error('SUPER_ADMIN_EMAIL is empty — set it in .env before seeding.');

            return;
        }

        Role::findOrCreate('super_admin', 'web');

        $superAdmin = User::query()->firstOrNew(['email' => $email]);
        $wasNew = ! $superAdmin->exists;

        // Plain password — User model casts password as "hashed".
        $superAdmin->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'two_factor_email_enabled' => $superAdmin->two_factor_email_enabled ?? false,
        ])->save();

        $superAdmin->syncRoles(['super_admin']);
        $superAdmin->refresh();

        $roleValue = $superAdmin->role instanceof UserRole
            ? $superAdmin->role->value
            : (string) $superAdmin->role;

        $this->command?->info(sprintf(
            'Super Admin %s: %s (role=%s, active=%s)',
            $wasNew ? 'created' : 'repaired',
            $superAdmin->email,
            $roleValue,
            $superAdmin->is_active ? 'yes' : 'no',
        ));

        if ($roleValue !== UserRole::SuperAdmin->value) {
            $this->command?->error('Super Admin role failed to persist as super_admin — check users.role column.');
        }
    }
}
