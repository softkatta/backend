<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class CheckAdminRole extends Command
{
    protected $signature = 'admin:check-role {--email= : Optional email to inspect}';

    protected $description = 'Check admin / candidate users and their roles';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));

        $query = User::query()->with('roles');

        if ($email !== '') {
            $query->where('email', $email);
        } else {
            $query->where(function ($inner): void {
                $inner->whereIn('email', array_filter([
                    env('SUPER_ADMIN_EMAIL'),
                    'admin@softkatta.com',
                    'admin@softkatta.in',
                ]))->orWhere('role', UserRole::SuperAdmin->value)
                    ->orWhere('email', 'like', '%admin%');
            });
        }

        $users = $query->orderBy('id')->get(['id', 'name', 'email', 'role', 'is_active']);

        if ($users->isEmpty()) {
            $this->error('No matching users found.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
            $this->line(str_repeat('-', 48));
            $this->info("{$user->email} (id {$user->id})");
            $this->line("  name: {$user->name}");
            $this->line("  role column: {$role}");
            $this->line('  spatie roles: '.($user->getRoleNames()->implode(', ') ?: '(none)'));
            $this->line('  is_active: '.($user->is_active ? 'yes' : 'no'));
            $this->line('  isSuperAdmin(): '.($user->isSuperAdmin() ? 'yes' : 'no'));
            $this->line('  portal expects frontend role: '.($role === 'super_admin' ? 'admin' : $role));
        }

        return self::SUCCESS;
    }
}
