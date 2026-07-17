<?php

namespace Database\Seeders;

use App\Services\EmployeeRoleService;
use App\Services\HrRoleService;
use App\Services\RolePermissionService;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(RolePermissionService::class)->syncCatalog();
        app(EmployeeRoleService::class)->syncAllEmployeeUsers();
        app(HrRoleService::class)->syncAllHrUsers();
    }
}
