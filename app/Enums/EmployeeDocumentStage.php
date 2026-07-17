<?php

namespace App\Enums;

enum EmployeeDocumentStage: string
{
    case Joining = 'joining';
    case Employment = 'employment';
    case Exit = 'exit';

    public function label(): string
    {
        return match ($this) {
            self::Joining => 'Joining documents',
            self::Employment => 'During employment',
            self::Exit => 'Resignation / exit documents',
        };
    }
}
