<?php

namespace App\Console\Commands;

use App\Services\EmployeeRoleService;
use App\Services\HrRoleService;
use App\Services\RolePermissionService;
use Illuminate\Console\Command;

class SyncRolePermissions extends Command
{
    protected $signature = 'permissions:sync {--assign-users : Assign Spatie roles to existing users}';

    protected $description = 'Seed/sync Spatie permissions and default role assignments';

    public function handle(RolePermissionService $permissions, EmployeeRoleService $employeeRoles, HrRoleService $hrRoles): int
    {
        $permissions->syncCatalog();
        $this->info('Permissions catalog synced.');

        if ($this->option('assign-users')) {
            $employeeCount = $employeeRoles->syncAllEmployeeUsers();
            $hrCount = $hrRoles->syncAllHrUsers();
            $this->info("Assigned employee role to {$employeeCount} user(s).");
            $this->info("Assigned HR manager role to {$hrCount} user(s).");
        }

        return self::SUCCESS;
    }
}
