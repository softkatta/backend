<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

class EmployeeRoleService
{
    public const ROLE = 'employee';

    /**
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        return PermissionCatalogService::defaultPermissionsForRole(self::ROLE);
    }

    public static function seedRoleAndPermissions(): Role
    {
        app(RolePermissionService::class)->syncCatalog();

        return Role::query()->where('name', self::ROLE)->firstOrFail();
    }

    public function ensureAssigned(User $user): void
    {
        if ($user->role !== UserRole::Employee) {
            return;
        }

        if (! $user->hasRole(self::ROLE)) {
            $user->assignRole(self::ROLE);
        }
    }

    public function syncAllEmployeeUsers(): int
    {
        $count = 0;

        User::query()
            ->where('role', UserRole::Employee)
            ->each(function (User $user) use (&$count): void {
                $this->ensureAssigned($user);
                $count++;
            });

        return $count;
    }
}
