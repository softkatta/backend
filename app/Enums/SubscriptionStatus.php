<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active       = 'active';
    case Trial        = 'trial';
    case Pending      = 'pending';
    case Suspend      = 'suspend';
    case Cancelled    = 'cancelled';
    case ExpiringSoon = 'expiring_soon';
    case Expired      = 'expired';
}
