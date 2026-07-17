<?php

namespace App\Services;

use App\Models\CompanyRole;
use App\Models\CompanyRoleMenu;
use App\Models\PortalMenu;
use Illuminate\Support\Facades\Schema;

class CompanyRoleMenuService
{
    /** Hardcoded fallback when DB table is empty / not migrated yet. */
    public const FALLBACK_MENUS = [
        'dashboard' => ['label' => 'Dashboard', 'route' => '/employee', 'icon' => 'LayoutDashboard', 'permission' => 'employee.dashboard.view', 'sort_order' => 10],
        'profile' => ['label' => 'My Profile', 'route' => '/employee/profile', 'icon' => 'UserRound', 'permission' => 'employee.profile.view', 'sort_order' => 20],
        'leave' => ['label' => 'Leave Application', 'route' => '/employee/leave', 'icon' => 'CalendarDays', 'permission' => 'employee.leave.view', 'sort_order' => 30],
        'attendance' => ['label' => 'Attendance', 'route' => '/employee/attendance', 'icon' => 'ClipboardList', 'permission' => 'employee.attendance.view', 'sort_order' => 40],
        'documents' => ['label' => 'Documents', 'route' => '/employee/documents', 'icon' => 'FileText', 'permission' => 'employee.documents.view', 'sort_order' => 50],
        'resignation' => ['label' => 'Resignation', 'route' => '/employee/resignation', 'icon' => 'LogOut', 'permission' => 'employee.resignation.view', 'sort_order' => 60],
        'notifications' => ['label' => 'Notifications', 'route' => '/employee/notifications', 'icon' => 'Bell', 'permission' => null, 'sort_order' => 70],
        'tasks' => ['label' => 'My Tasks', 'route' => '/employee/tasks', 'icon' => 'ListTodo', 'permission' => 'employee.tasks.view', 'sort_order' => 80],
        'projects' => ['label' => 'My Projects', 'route' => '/employee/projects', 'icon' => 'FolderKanban', 'permission' => 'employee.projects.view', 'sort_order' => 90],
        'timesheets' => ['label' => 'Timesheets', 'route' => '/employee/timesheets', 'icon' => 'Clock', 'permission' => 'employee.timesheets.view', 'sort_order' => 100],
        'calendar' => ['label' => 'Calendar', 'route' => '/employee/calendar', 'icon' => 'Calendar', 'permission' => 'employee.calendar.view', 'sort_order' => 110],
        'announcements' => ['label' => 'Announcements', 'route' => '/employee/announcements', 'icon' => 'Megaphone', 'permission' => 'employee.announcements.view', 'sort_order' => 120],
        'assets' => ['label' => 'Assets', 'route' => '/employee/assets', 'icon' => 'Laptop', 'permission' => 'employee.assets.view', 'sort_order' => 130],
        'training' => ['label' => 'Training', 'route' => '/employee/training', 'icon' => 'GraduationCap', 'permission' => 'employee.training.view', 'sort_order' => 140],
        'performance' => ['label' => 'Performance Reviews', 'route' => '/employee/performance', 'icon' => 'TrendingUp', 'permission' => 'employee.performance.view', 'sort_order' => 150],
        'helpdesk' => ['label' => 'Help Desk', 'route' => '/employee/helpdesk', 'icon' => 'LifeBuoy', 'permission' => 'employee.helpdesk.view', 'sort_order' => 160],
    ];

    /**
     * Default menus for every company role when no custom assignment exists.
     * Includes the full employee portal catalog (role-wise all pages visible by default).
     */
    public const CORE_MENU_KEYS = [
        'dashboard',
        'profile',
        'leave',
        'attendance',
        'documents',
        'resignation',
        'notifications',
        'tasks',
        'projects',
        'timesheets',
        'calendar',
        'announcements',
        'assets',
        'training',
        'performance',
        'helpdesk',
    ];

    /** @deprecated Use FALLBACK_MENUS — kept for BC callers expecting key=>path map */
    public const MENUS = [
        'dashboard' => '/employee',
        'profile' => '/employee/profile',
        'leave' => '/employee/leave',
        'attendance' => '/employee/attendance',
        'documents' => '/employee/documents',
        'resignation' => '/employee/resignation',
        'notifications' => '/employee/notifications',
        'tasks' => '/employee/tasks',
        'projects' => '/employee/projects',
        'timesheets' => '/employee/timesheets',
        'calendar' => '/employee/calendar',
        'announcements' => '/employee/announcements',
        'assets' => '/employee/assets',
        'training' => '/employee/training',
        'performance' => '/employee/performance',
        'helpdesk' => '/employee/helpdesk',
    ];

    /**
     * @return list<array{key: string, label: string, path: string, route: string, icon: ?string, permission: ?string, sort_order: int, is_active: bool}>
     */
    public static function menuCatalog(string $portal = 'employee'): array
    {
        if (self::portalMenusTableReady()) {
            $rows = PortalMenu::query()
                ->where('portal', $portal)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows->map(fn (PortalMenu $menu) => [
                    'key' => $menu->key,
                    'label' => $menu->label,
                    'path' => $menu->route,
                    'route' => $menu->route,
                    'icon' => $menu->icon,
                    'permission' => $menu->permission,
                    'sort_order' => (int) $menu->sort_order,
                    'is_active' => (bool) $menu->is_active,
                ])->values()->all();
            }
        }

        return self::fallbackCatalog();
    }

    /**
     * @return list<string>
     */
    public static function allMenuKeys(): array
    {
        return array_column(self::menuCatalog(), 'key');
    }

    /**
     * Resolve enabled menu keys for a company role.
     * Order: pivot rows → JSON override → category defaults.
     *
     * @param  list<string>|null  $override
     * @return list<string>
     */
    public static function menuKeysFor(
        ?string $slug,
        ?string $category,
        ?array $override = null,
        ?CompanyRole $role = null,
    ): array {
        if ($role !== null) {
            $fromPivot = self::keysFromPivot($role);
            if ($fromPivot !== null) {
                return self::sanitizeMenuKeys($fromPivot);
            }
            $override = $role->employee_portal_menus;
        }

        if (is_array($override) && $override !== []) {
            return self::sanitizeMenuKeys($override);
        }

        return self::defaultMenuKeysFor($slug, $category);
    }

    /**
     * @return list<string>
     */
    public static function defaultMenuKeysFor(?string $slug, ?string $category): array
    {
        $keys = self::allMenuKeys();
        if ($keys === []) {
            $keys = self::CORE_MENU_KEYS;
        }

        if ($slug === 'intern-trainee' || $category === 'Internship') {
            return array_values(array_diff($keys, ['resignation']));
        }

        if (in_array($category, ['Administration'], true)) {
            return array_values(array_diff($keys, ['resignation']));
        }

        return array_values($keys);
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function sanitizeMenuKeys(array $keys): array
    {
        $allowed = self::allMenuKeys();
        $sanitized = array_values(array_unique(array_filter(
            $keys,
            fn (string $key) => in_array($key, $allowed, true),
        )));

        if (! in_array('dashboard', $sanitized, true)) {
            array_unshift($sanitized, 'dashboard');
        }

        return $sanitized;
    }

    /**
     * @param  list<string>|null  $override
     * @return list<string>
     */
    public static function pathsFor(
        ?string $slug,
        ?string $category,
        ?array $override = null,
        ?CompanyRole $role = null,
    ): array {
        $map = self::keyToRouteMap();
        $keys = self::menuKeysFor($slug, $category, $override, $role);

        return array_values(array_filter(array_map(
            fn (string $key) => $map[$key] ?? null,
            $keys,
        )));
    }

    /**
     * @param  list<string>|null  $override
     */
    public static function canAccessPath(
        ?string $slug,
        ?string $category,
        string $path,
        ?array $override = null,
        ?CompanyRole $role = null,
    ): bool {
        $normalized = rtrim(explode('?', $path)[0], '/') ?: '/';

        foreach (self::pathsFor($slug, $category, $override, $role) as $allowed) {
            if ($normalized === $allowed) {
                return true;
            }

            if ($allowed !== '/employee' && str_starts_with($normalized, $allowed.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>|null  $override
     */
    public static function canAccessMenuKey(
        ?string $slug,
        ?string $category,
        string $menuKey,
        ?array $override = null,
        ?CompanyRole $role = null,
    ): bool {
        return in_array($menuKey, self::menuKeysFor($slug, $category, $override, $role), true);
    }

    public static function menuKeyFromRequestPath(string $path): ?string
    {
        $map = [
            '/employee/profile' => 'profile',
            '/employee/leave' => 'leave',
            '/employee/attendance' => 'attendance',
            '/employee/documents' => 'documents',
            '/employee/resignation' => 'resignation',
            '/employee/notifications' => 'notifications',
            '/employee/tasks' => 'tasks',
            '/employee/projects' => 'projects',
            '/employee/timesheets' => 'timesheets',
            '/employee/calendar' => 'calendar',
            '/employee/announcements' => 'announcements',
            '/employee/assets' => 'assets',
            '/employee/training' => 'training',
            '/employee/performance' => 'performance',
            '/employee/helpdesk' => 'helpdesk',
        ];

        foreach ($map as $needle => $key) {
            if (str_contains($path, $needle)) {
                return $key;
            }
        }

        if (str_contains($path, '/employee/dashboard') || preg_match('#/employee/?$#', $path) || preg_match('#employee/?$#', $path)) {
            return 'dashboard';
        }

        return null;
    }

    /**
     * Sync pivot + JSON mirror for a company role.
     * Pass null to clear custom menus (use category defaults).
     *
     * @param  list<string>|null  $keys
     */
    public static function syncRoleMenus(CompanyRole $role, ?array $keys): void
    {
        if ($keys === null) {
            $role->roleMenus()->delete();
            $role->employee_portal_menus = null;
            $role->save();

            return;
        }

        $sanitized = self::sanitizeMenuKeys($keys);
        $role->employee_portal_menus = $sanitized;
        $role->save();

        if (! self::portalMenusTableReady() || ! Schema::hasTable('company_role_menus')) {
            return;
        }

        $menus = PortalMenu::query()
            ->where('portal', 'employee')
            ->whereIn('key', $sanitized)
            ->get()
            ->keyBy('key');

        $role->roleMenus()->delete();

        foreach ($sanitized as $index => $key) {
            $menu = $menus->get($key);
            if (! $menu) {
                continue;
            }

            CompanyRoleMenu::create([
                'company_role_id' => $role->id,
                'portal_menu_id' => $menu->id,
                'is_enabled' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    /**
     * @return list<string>|null  null means "no pivot customization"
     */
    private static function keysFromPivot(CompanyRole $role): ?array
    {
        if (! Schema::hasTable('company_role_menus')) {
            return null;
        }

        $role->loadMissing(['roleMenus.portalMenu']);

        $enabled = $role->roleMenus
            ->filter(fn (CompanyRoleMenu $row) => $row->is_enabled && $row->portalMenu)
            ->sortBy('sort_order')
            ->values();

        if ($enabled->isEmpty()) {
            return null;
        }

        return $enabled->map(fn (CompanyRoleMenu $row) => $row->portalMenu->key)->all();
    }

    /**
     * @return array<string, string>
     */
    private static function keyToRouteMap(): array
    {
        $map = [];
        foreach (self::menuCatalog() as $item) {
            $map[$item['key']] = $item['route'] ?? $item['path'];
        }

        return $map !== [] ? $map : self::MENUS;
    }

    /**
     * @return list<array{key: string, label: string, path: string, route: string, icon: ?string, permission: ?string, sort_order: int, is_active: bool}>
     */
    private static function fallbackCatalog(): array
    {
        $items = [];
        foreach (self::FALLBACK_MENUS as $key => $meta) {
            $items[] = [
                'key' => $key,
                'label' => $meta['label'],
                'path' => $meta['route'],
                'route' => $meta['route'],
                'icon' => $meta['icon'],
                'permission' => $meta['permission'],
                'sort_order' => $meta['sort_order'],
                'is_active' => true,
            ];
        }

        return $items;
    }

    private static function portalMenusTableReady(): bool
    {
        try {
            return Schema::hasTable('portal_menus');
        } catch (\Throwable) {
            return false;
        }
    }
}
