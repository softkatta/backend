<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case InApp = 'in_app';
}
