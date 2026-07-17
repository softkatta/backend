<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;

class DisableAdminTwoFa extends Command
{
    protected $signature = 'admin:disable-2fa';

    protected $description = 'Disable login 2FA platform-wide and clear 2FA methods on the super admin user';

    public function handle(): int
    {
        $adminEmail = strtolower(trim((string) config('softkatta.super_admin.email', 'admin@softkatta.com')));
        $admin = User::query()->whereRaw('LOWER(email) = ?', [$adminEmail])->first();

        if (! $admin) {
            $this->error('Super admin user not found!');

            return 1;
        }

        $admin->update([
            'two_factor_email_enabled' => false,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        Setting::updateOrCreate(
            ['key' => 'two_factor_login_enabled'],
            ['value' => 'false', 'group' => 'security'],
        );

        Setting::query()
            ->where('key', 'demo_account_email')
            ->whereRaw('LOWER(value) = ?', [$adminEmail])
            ->update(['value' => '']);

        $this->info('Super admin 2FA disabled: '.$admin->email);
        $this->line('   - two_factor_login_enabled = false');
        $this->line('   - admin user 2FA methods cleared');
        $this->line('   - demo_account_email cleared when it matched super admin');

        return self::SUCCESS;
    }
}
