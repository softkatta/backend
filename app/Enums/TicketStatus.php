<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingOnClient = 'waiting_on_client';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
