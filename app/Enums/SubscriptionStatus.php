<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspend = 'suspend';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
}
