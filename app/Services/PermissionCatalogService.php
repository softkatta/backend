<?php

namespace App\Services;

class PermissionCatalogService
{
    /**
     * @return list<array{name: string, label: string, group: string, roles: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            // Employee portal
            ['name' => 'employee.dashboard.view', 'label' => 'View dashboard', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.profile.view', 'label' => 'View profile', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.profile.update', 'label' => 'Update profile', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.documents.view', 'label' => 'View documents', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.documents.upload', 'label' => 'Upload documents', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.documents.download', 'label' => 'Download documents', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.leave.view', 'label' => 'View leave requests', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.leave.apply', 'label' => 'Apply for leave', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.leave.cancel', 'label' => 'Cancel leave request', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.attendance.view', 'label' => 'View attendance', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.attendance.submit', 'label' => 'Submit attendance', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.resignation.view', 'label' => 'View resignation status', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.resignation.submit', 'label' => 'Submit resignation', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.tasks.view', 'label' => 'View tasks', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.tasks.create', 'label' => 'Create tasks', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.tasks.update', 'label' => 'Update tasks', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.tasks.delete', 'label' => 'Delete tasks', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.projects.view', 'label' => 'View projects', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.projects.create', 'label' => 'Create projects', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.projects.update', 'label' => 'Update projects', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.projects.delete', 'label' => 'Delete projects', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.timesheets.view', 'label' => 'View timesheets', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.timesheets.create', 'label' => 'Create timesheet entries', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.timesheets.update', 'label' => 'Update timesheet entries', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.timesheets.delete', 'label' => 'Delete timesheet entries', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.calendar.view', 'label' => 'View calendar', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.calendar.create', 'label' => 'Create calendar events', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.calendar.update', 'label' => 'Update calendar events', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.calendar.delete', 'label' => 'Delete calendar events', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.announcements.view', 'label' => 'View announcements', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.assets.view', 'label' => 'View assets', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.training.view', 'label' => 'View training', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.training.update', 'label' => 'Update training progress', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.performance.view', 'label' => 'View performance reviews', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.performance.acknowledge', 'label' => 'Acknowledge performance reviews', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.helpdesk.view', 'label' => 'View help desk', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.helpdesk.create', 'label' => 'Create help desk tickets', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],
            ['name' => 'employee.helpdesk.update', 'label' => 'Update help desk tickets', 'group' => 'Employee Portal', 'roles' => ['employee', 'super_admin']],

            // HR management
            ['name' => 'hr.announcements.view', 'label' => 'View announcements admin', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.announcements.manage', 'label' => 'Manage announcements', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.assets.view', 'label' => 'View company assets', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.assets.manage', 'label' => 'Manage company assets', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.training.view', 'label' => 'View training admin', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.training.manage', 'label' => 'Manage training assignments', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.performance.view', 'label' => 'View performance reviews admin', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.performance.manage', 'label' => 'Manage performance reviews', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.helpdesk.view', 'label' => 'View help desk admin', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.helpdesk.manage', 'label' => 'Manage help desk tickets', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.careers.view', 'label' => 'View job openings', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.careers.manage', 'label' => 'Manage job openings', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.applications.view', 'label' => 'View applications', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.applications.manage', 'label' => 'Update application status', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.applications.export', 'label' => 'Export applications', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.employees.view', 'label' => 'View employees', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.employees.manage', 'label' => 'Manage employees', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.employees.delete', 'label' => 'Delete employees', 'group' => 'HR Management', 'roles' => ['super_admin']],
            ['name' => 'hr.employees.documents', 'label' => 'Manage employee documents', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.employees.portal', 'label' => 'Manage employee portal access', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.employees.exit', 'label' => 'Manage employee exit process', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.leave.view', 'label' => 'View leave requests', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.leave.manage', 'label' => 'Approve or reject leave', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.attendance.view', 'label' => 'View attendance records', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.attendance.manage', 'label' => 'Manage attendance records', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.company-roles.view', 'label' => 'View company roles master', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.company-roles.manage', 'label' => 'Manage company roles master', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.users.view', 'label' => 'View users directory (read-only)', 'group' => 'HR Management', 'roles' => ['hr_manager', 'super_admin']],
            ['name' => 'hr.permissions.view', 'label' => 'View roles & permissions', 'group' => 'HR Management', 'roles' => ['super_admin']],
            ['name' => 'hr.permissions.manage', 'label' => 'Manage roles & permissions', 'group' => 'HR Management', 'roles' => ['super_admin']],

            // Client portal
            ['name' => 'client.dashboard.view', 'label' => 'View client dashboard', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],
            ['name' => 'client.profile.view', 'label' => 'View profile', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],
            ['name' => 'client.profile.update', 'label' => 'Update profile', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],
            ['name' => 'client.orders.view', 'label' => 'View orders', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],
            ['name' => 'client.invoices.view', 'label' => 'View invoices', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],
            ['name' => 'client.subscriptions.view', 'label' => 'View subscriptions', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],
            ['name' => 'client.support.view', 'label' => 'View support tickets', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],
            ['name' => 'client.support.create', 'label' => 'Create support tickets', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],
            ['name' => 'client.licenses.view', 'label' => 'View licenses', 'group' => 'Client Portal', 'roles' => ['client', 'super_admin']],

            // Website chatbot
            ['name' => 'chatbot.view', 'label' => 'View chatbot module', 'group' => 'Website Chatbot', 'roles' => ['super_admin']],
            ['name' => 'chatbot.create', 'label' => 'Create chatbot content', 'group' => 'Website Chatbot', 'roles' => ['super_admin']],
            ['name' => 'chatbot.edit', 'label' => 'Edit chatbot content', 'group' => 'Website Chatbot', 'roles' => ['super_admin']],
            ['name' => 'chatbot.delete', 'label' => 'Delete chatbot content', 'group' => 'Website Chatbot', 'roles' => ['super_admin']],
            ['name' => 'chatbot.analytics', 'label' => 'View chatbot analytics', 'group' => 'Website Chatbot', 'roles' => ['super_admin']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        return array_values(array_unique(array_column(self::definitions(), 'name')));
    }

    /**
     * @return list<string>
     */
    public static function defaultPermissionsForRole(string $role): array
    {
        if ($role === 'super_admin') {
            return self::permissionNames();
        }

        $permissions = [];

        foreach (self::definitions() as $definition) {
            if (in_array($role, $definition['roles'], true)) {
                $permissions[] = $definition['name'];
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return list<array{group: string, permissions: list<array{name: string, label: string}>}>
     */
    public static function groupedCatalog(): array
    {
        $groups = [];

        foreach (self::definitions() as $definition) {
            $group = $definition['group'];
            $groups[$group] ??= ['group' => $group, 'permissions' => []];
            $groups[$group]['permissions'][] = [
                'name' => $definition['name'],
                'label' => $definition['label'],
            ];
        }

        return array_values($groups);
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    public static function roleOptions(): array
    {
        return [
            ['name' => 'super_admin', 'label' => 'Super Admin'],
            ['name' => 'hr_manager', 'label' => 'HR Manager'],
            ['name' => 'employee', 'label' => 'Employee'],
            ['name' => 'client', 'label' => 'Client'],
        ];
    }
}
