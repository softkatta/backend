<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;

class DisableAdminTwoFa extends Command
{
    protected $signature = 'admin:disable-2fa';

    protected $description = 'Disable 2FA for admin demo account';

    public function handle(): int
    {
        $admin = User::where('email', 'admin@softkatta.com')->first();
        if (!$admin) {
            $this->error('Admin user not found!');
            return 1;
        }

        $this->info('Admin user: ' . $admin->name . ' (' . $admin->email . ')');

        Setting::updateOrCreate(
            ['key' => 'demo_account_email'],
            ['value' => 'admin@softkatta.com']
        );

        Setting::updateOrCreate(
            ['key' => 'demo_account_2fa_enabled'],
            ['value' => '0']
        );

        $this->newLine();
        $this->info('✅ Settings updated:');
        $this->line('   - demo_account_email: admin@softkatta.com');
        $this->line('   - demo_account_2fa_enabled: false');
        $this->newLine();
        $this->info('✅ Admin login will now bypass 2FA verification!');

        return self::SUCCESS;
    }
}
