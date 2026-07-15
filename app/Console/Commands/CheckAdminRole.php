<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class CheckAdminRole extends Command
{
    protected $signature = 'admin:check-role';

    protected $description = 'Check admin user role';

    public function handle(): int
    {
        $admin = User::where('email', 'admin@softkatta.com')->first();
        if (!$admin) {
            $this->error('Admin user not found!');
            return 1;
        }

        $this->info('Admin user found:');
        $this->line('Email: ' . $admin->email);
        $this->line('Name: ' . $admin->name);
        $this->line('Role: ' . ($admin->role instanceof UserRole ? $admin->role->value : (string) $admin->role));
        $this->line('Role type: ' . get_class($admin->role));
        
        // Check Spatie roles
        $this->newLine();
        $this->info('Spatie roles:');
        if ($admin->roles->count() > 0) {
            $admin->roles->each(fn($role) => $this->line('  - ' . $role->name));
        } else {
            $this->line('  (none)');
        }

        $this->newLine();
        $this->info('Is Super Admin: ' . ($admin->isSuperAdmin() ? 'Yes' : 'No'));
        
        return self::SUCCESS;
    }
}
