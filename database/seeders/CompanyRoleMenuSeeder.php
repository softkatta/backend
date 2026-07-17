<?php

namespace Database\Seeders;

use App\Models\CompanyRole;
use App\Services\CompanyRoleMenuService;
use Illuminate\Database\Seeder;

class CompanyRoleMenuSeeder extends Seeder
{
    /**
     * Reset every company role to category defaults (full portal catalog).
     * Passing null clears JSON + pivot so new menus auto-appear later.
     */
    public function run(): void
    {
        foreach (CompanyRole::query()->orderBy('id')->get() as $role) {
            // Clear custom overrides → resolve via defaultMenuKeysFor (all menus).
            CompanyRoleMenuService::syncRoleMenus($role, null);

            // Persist the resolved full default set so admin UI / APIs show menus immediately.
            $keys = CompanyRoleMenuService::defaultMenuKeysFor($role->slug, $role->category);
            CompanyRoleMenuService::syncRoleMenus($role->fresh(), $keys);
        }
    }
}
