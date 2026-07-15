<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class FixAdminRole extends Command
{
    protected $signature = 'admin:fix-role';

    protected $description = 'Fix admin user role to super_admin';

    public function handle(): int
    {
        $admin = User::where('email', 'admin@softkatta.com')->first();
        if (!$admin) {
            $this->error('Admin user not found!');
            return 1;
        }

        $this->info('Fixing admin role...');
        $this->line('Current role: ' . ($admin->role instanceof UserRole ? $admin->role->value : (string) $admin->role));

        $admin->update(['role' => UserRole::SuperAdmin]);
        $admin->refresh();

        $this->newLine();
        $this->info('✅ Admin role fixed!');
        $this->line('New role: ' . ($admin->role instanceof UserRole ? $admin->role->value : (string) $admin->role));
        
        return self::SUCCESS;
    }
}
