<?php

namespace App\Console\Commands;

use App\Services\EmployeeRoleService;
use Illuminate\Console\Command;

class SyncEmployeeRoles extends Command
{
    protected $signature = 'employees:sync-roles';

    protected $description = 'Create employee Spatie role, permissions, and assign them to employee users';

    public function handle(EmployeeRoleService $employeeRoles): int
    {
        EmployeeRoleService::seedRoleAndPermissions();
        $this->info('Employee role and permissions synced.');

        $count = $employeeRoles->syncAllEmployeeUsers();
        $this->info("Assigned employee role to {$count} user(s).");

        return self::SUCCESS;
    }
}
