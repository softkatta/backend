<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case Active    = 'active';
    case Suspended = 'suspended';
    case Expired   = 'expired';
    case Revoked   = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active    => 'Active',
            self::Suspended => 'Suspended',
            self::Expired   => 'Expired',
            self::Revoked   => 'Revoked',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
