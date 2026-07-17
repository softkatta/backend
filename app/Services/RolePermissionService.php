<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionService
{
    public function syncCatalog(): void
    {
        $guard = 'web';

        foreach (PermissionCatalogService::permissionNames() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        foreach (PermissionCatalogService::roleOptions() as $roleOption) {
            $role = Role::firstOrCreate([
                'name' => $roleOption['name'],
                'guard_name' => $guard,
            ]);

            $role->syncPermissions(PermissionCatalogService::defaultPermissionsForRole($roleOption['name']));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array{
     *     catalog: list<array{group: string, permissions: list<array{name: string, label: string}>}>,
     *     roles: list<array{name: string, label: string, permissions: list<string>, users_count: int}>
     * }
     */
    public function snapshot(): array
    {
        $userCounts = DB::table(config('permission.table_names.model_has_roles'))
            ->select('role_id', DB::raw('count(*) as aggregate'))
            ->where('model_type', User::class)
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id');

        $roles = Role::query()
            ->with('permissions')
            ->get()
            ->sortBy(function (Role $role): int {
                $order = array_flip(array_column(PermissionCatalogService::roleOptions(), 'name'));

                return $order[$role->name] ?? 99;
            })
            ->values()
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'label' => collect(PermissionCatalogService::roleOptions())
                    ->firstWhere('name', $role->name)['label'] ?? ucwords(str_replace('_', ' ', $role->name)),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'users_count' => (int) ($userCounts[$role->id] ?? 0),
            ])
            ->values()
            ->all();

        return [
            'catalog' => PermissionCatalogService::groupedCatalog(),
            'roles' => $roles,
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    public function updateRolePermissions(string $roleName, array $permissions): Role
    {
        $guard = 'web';
        $known = PermissionCatalogService::permissionNames();

        $permissions = array_values(array_unique(array_filter(
            $permissions,
            fn (string $permission) => in_array($permission, $known, true),
        )));

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => $guard,
        ]);

        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->load('permissions');
    }
}
