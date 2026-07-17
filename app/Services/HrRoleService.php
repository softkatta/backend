<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class HrRoleService
{
    public const ROLE = 'hr_manager';

    public static function seedRoleFromCatalog(): Role
    {
        app(RolePermissionService::class)->syncCatalog();

        return Role::query()->where('name', self::ROLE)->firstOrFail();
    }

    public function ensureAssigned(User $user): void
    {
        if ($user->role !== UserRole::HrManager) {
            return;
        }

        if (! $user->hasRole(self::ROLE)) {
            $user->assignRole(self::ROLE);
        }
    }

    public function syncAllHrUsers(): int
    {
        $count = 0;

        User::query()
            ->where('role', UserRole::HrManager)
            ->each(function (User $user) use (&$count): void {
                $this->ensureAssigned($user);
                $count++;
            });

        return $count;
    }

    public function createManager(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'initial_login_password' => $password,
            'role' => UserRole::HrManager,
            'two_factor_enabled' => false,
            'two_factor_email_enabled' => false,
            'is_active' => true,
        ]);

        $this->ensureAssigned($user);

        return $user->fresh(['roles']);
    }
}
