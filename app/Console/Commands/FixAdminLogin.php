<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixAdminLogin extends Command
{
    protected $signature = 'admin:fix-login';

    protected $description = 'Fix superadmin login by disabling all 2FA methods';

    public function handle(): int
    {
        $admin = User::where('email', 'admin@softkatta.com')->first();
        if (!$admin) {
            $this->error('Admin user not found!');
            return 1;
        }

        $this->info('Fixing admin login...');
        $this->line('User: ' . $admin->name . ' (' . $admin->email . ')');

        // Disable all 2FA methods on the admin user
        $admin->update([
            'two_factor_email_enabled' => false,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_backup_codes' => null,
        ]);

        $this->newLine();
        $this->info('✅ Admin account fixed:');
        $this->line('   - two_factor_email_enabled: false');
        $this->line('   - two_factor_enabled: false');
        $this->line('   - two_factor_secret: cleared');
        $this->newLine();
        $this->info('✅ Admin can now login without OTP!');

        return self::SUCCESS;
    }
}
