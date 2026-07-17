<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case HrManager = 'hr_manager';
    case Client = 'client';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Founder / Owner',
            self::HrManager => 'HR Manager',
            self::Client => 'Client',
            self::Employee => 'Employee',
        };
    }

    public function loginPortalPath(): string
    {
        return match ($this) {
            self::SuperAdmin => '/admin',
            self::HrManager => '/hr',
            self::Employee => '/employee',
            self::Client => '/login',
        };
    }
}
