<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Monthly    => 'Monthly',
            self::Quarterly  => 'Quarterly',
            self::HalfYearly => 'Half Yearly',
            self::Yearly     => 'Yearly',
            self::Lifetime   => 'Lifetime',
            self::Enterprise => 'Enterprise',
        };
    }

    /** Duration in months (null = lifetime/unlimited) */
    public function months(): ?int
    {
        return match ($this) {
            self::Monthly    => 1,
            self::Quarterly  => 3,
            self::HalfYearly => 6,
            self::Yearly     => 12,
            self::Lifetime   => null,
            self::Enterprise => null,
        };
    }
}
