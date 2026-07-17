<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class FixAdminRole extends Command
{
    protected $signature = 'admin:fix-role
                            {--email= : Admin email (defaults to SUPER_ADMIN_EMAIL / common admins)}
                            {--password= : Optional password reset}
                            {--all-admins : Promote every user matching admin emails}';

    protected $description = 'Repair super admin role + Spatie permissions for production/local admin login';

    public function handle(): int
    {
        Role::findOrCreate('super_admin');

        $emails = $this->resolveEmails();
        if ($emails === []) {
            $this->error('No admin email provided. Pass --email= or set SUPER_ADMIN_EMAIL in .env');

            return self::FAILURE;
        }

        $fixed = 0;

        foreach ($emails as $email) {
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $this->warn("User not found: {$email}");
                continue;
            }

            $before = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

            $payload = [
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ];

            if ($this->option('password')) {
                $payload['password'] = Hash::make((string) $this->option('password'));
            }

            $user->forceFill($payload)->save();
            $user->syncRoles(['super_admin']);
            $user->refresh();

            $after = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

            $this->info("Fixed: {$user->email}");
            $this->line("  role: {$before} → {$after}");
            $this->line('  spatie: '.$user->getRoleNames()->implode(', '));
            $this->line('  is_active: '.($user->is_active ? 'yes' : 'no'));
            $this->line('  isSuperAdmin(): '.($user->isSuperAdmin() ? 'yes' : 'no'));

            $fixed++;
        }

        if ($fixed === 0) {
            $this->error('No admin users were updated.');
            $this->line('Create one with: php artisan admin:create --email=admin@softkatta.in --password=YourPassword');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Done. {$fixed} admin account(s) ready for /admin login.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveEmails(): array
    {
        if ($email = trim((string) $this->option('email'))) {
            return [strtolower($email)];
        }

        $candidates = array_values(array_unique(array_filter([
            strtolower((string) env('SUPER_ADMIN_EMAIL', '')),
            'admin@softkatta.com',
            'admin@softkatta.in',
        ])));

        if ($this->option('all-admins')) {
            return $candidates;
        }

        // Prefer env email when present; otherwise try both common addresses.
        if (($candidates[0] ?? '') !== '') {
            return [$candidates[0]];
        }

        return ['admin@softkatta.com', 'admin@softkatta.in'];
    }
}
