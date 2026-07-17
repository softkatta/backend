<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case Probation = 'probation';
    case OnNotice = 'on_notice';
    case Exited = 'exited';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
