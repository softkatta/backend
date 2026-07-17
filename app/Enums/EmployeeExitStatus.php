<?php

namespace App\Enums;

enum EmployeeExitStatus: string
{
    case Initiated = 'initiated';
    case AcceptanceSent = 'acceptance_sent';
    case ExitInterview = 'exit_interview';
    case NoDues = 'no_dues';
    case AssetHandover = 'asset_handover';
    case FullAndFinal = 'full_and_final';
    case Completed = 'completed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
