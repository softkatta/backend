<?php

namespace Database\Seeders;

use App\Models\PortalMenu;
use App\Services\CompanyRoleMenuService;
use Illuminate\Database\Seeder;

class PortalMenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CompanyRoleMenuService::FALLBACK_MENUS as $key => $meta) {
            PortalMenu::updateOrCreate(
                [
                    'portal' => 'employee',
                    'key' => $key,
                ],
                [
                    'label' => $meta['label'],
                    'route' => $meta['route'],
                    'icon' => $meta['icon'],
                    'parent_key' => null,
                    'sort_order' => $meta['sort_order'],
                    'permission' => $meta['permission'],
                    'is_active' => true,
                    'badge_enabled' => $key === 'notifications',
                ],
            );
        }
    }
}
